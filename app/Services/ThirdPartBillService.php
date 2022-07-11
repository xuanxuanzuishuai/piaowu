<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 11:04
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\QingChen;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AgentModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\ParamMapModel;
use App\Models\ThirdPartBillModel;
use App\Services\Queue\ThirdPartBillTopic;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ThirdPartBillService
{
    /**
     * 检查过滤表格数据
     * @param $filename
     * @param $operatorId
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function checkDuplicate($filename, $operatorId, $params)
    {
        try {
            $fileType = ucfirst(pathinfo($filename)["extension"]);
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $now = time();
            $data = [];
            foreach ($sheetData as $k => $v) {
                if ($k == 1) { // 忽略表头和空白行
                    continue;
                }
                $A = trim($v['A']);
                $B = trim($v['B']);
                $C = trim($v['C']);
                $D = trim($v['D']);
                $E = trim($v['E']);
                //四项必填数据全部为空，忽略不处理
                if (empty($A) && empty($B) && empty($C) && empty($D) && empty($E)) {
                    continue;
                }
                if ($E != ThirdPartBillModel::IGNORE) {
                    $data[] = [
                        'country_code' => $A,
                        'mobile'       => $B,
                        'trade_no'     => $C,
                        'operator_id'  => $operatorId,
                        'pay_time'     => $now,
                        'create_time'  => $now,
                        'dss_amount'   => ($D == '') ? 0 : $D,
                    ];
                }
            }
            //检测课包价格是否正确
            $packageData = DssErpPackageV1Model::getRecord(['id' => $params['package_id']],['price_json']);
            if(empty($packageData)){
                throw new RunTimeException(['package_not_available','import']);
            }
            $priceJSON = json_decode($packageData['price_json'], true);
            $packagePriceMoney = empty($priceJSON[ErpStudentAccountModel::SUB_TYPE_CNY]) ? 0 : $priceJSON[ErpStudentAccountModel::SUB_TYPE_CNY];
            // 检查所有的手机号/订单号/实付金额是否合法, 并返回所有错误的记录
            $invalidMobiles = $invalidTradeNo = $invalidDssAmount = [];
            foreach ($data as &$v) {
                if (empty($v['country_code'])) {
                    $invalidCountryCode[] = $v;
                } elseif ($v['country_code'] == CommonServiceForApp::DEFAULT_COUNTRY_CODE) {
                    if (!Util::isChineseMobile($v['mobile'])) {
                        $invalidMobiles[] = $v;
                    }
                } else {
                    if (!Util::validPhoneNumber($v['mobile'], $v['country_code'])) {
                        $invalidMobiles[] = $v;
                    }
                }

                if (empty($v['trade_no'])) {
                    $invalidTradeNo[] = $v;
                }
                if (($v['dss_amount'] < 0) || ($v['dss_amount'] == '') || !is_numeric($v['dss_amount']) || ($v['dss_amount'] > 5000)) {
                    $invalidDssAmount[] = $v;
                }
                if ($v['dss_amount'] == 0) {
                    $v['dss_amount'] = 1;
                } else {
                    $v['dss_amount'] *= 100;
                    if ((int)$v['dss_amount'] > $packagePriceMoney) {
                        $invalidDssAmount[] = $v;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new RunTimeException(['excel_factory_error','import']);
        }

        if (count($invalidMobiles) > 0) {
            throw new RunTimeException(['invalid_mobile', 'import'], ['list' => $invalidMobiles]);
        }
        if (count($invalidTradeNo) > 0) {
            throw new RunTimeException(['trade_no_can_not_be_empty', 'import'], ['list' => $invalidTradeNo]);
        }
        if (!empty($invalidCountryCode)) {
            throw new RunTimeException(['country_code_is_required', 'import'], ['list' => $invalidCountryCode]);
        }

        $recordNum = count($data);
        // 检查数据是否为空
        if ($recordNum == 0) {
            throw new RunTimeException(['data_can_not_be_empty', 'import']);
        } else {
            $maxRecord = [
                Constants::SMART_APP_ID => 100,
                Constants::QC_APP_ID    => 200,
            ];
            if ($recordNum > $maxRecord[$params['target_business_id']]) {
                throw new RunTimeException(['over_max_allow_num', 'import']);
            }
        }

        //针对不同业务线的条件检查
        self::checkoutBusinessCondition($params,$data);

        if (count($invalidDssAmount) > 0) {
            throw new RunTimeException(['bill_dss_amount_error', 'import'], ['list' => $invalidDssAmount]);
        }
        // 学生手机号重复
        if ($recordNum != count(array_unique(array_column($data, 'mobile')))) {
            throw new RunTimeException(['mobile_repeat', 'import']);
        }

        $params['third_identity_type'] = $params['third_identity_id'] = 0;
        //检测渠道是否为合作代理&检测代理商数据
        if (!empty($params['agent_id'])) {
            $agentInfo = AgentModel::getAgentParentData([$params['agent_id']])[0];
            $agentChannelIds = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_dict');
            if (!in_array($params['channel_id'], json_decode($agentChannelIds, true)) || ($agentInfo['p_id'] !== null)) {
                throw new RunTimeException(['agent_info_error'], []);
            }
            $params['third_identity_type'] = ThirdPartBillModel::THIRD_IDENTITY_TYPE_AGENT;
            $params['third_identity_id'] = $params['agent_id'];
        }
        foreach ($data as &$v) {
            $v['parent_channel_id'] = $params['parent_channel_id'];
            $v['channel_id'] = $params['channel_id'];
            $v['package_id'] = $params['package_id'];
            $v['business_id'] = $params['business_id'];
            $v['target_business_id'] = $params['target_business_id'];
            $v['third_identity_id'] = $params['third_identity_id'];
            $v['third_identity_type'] = $params['third_identity_type'];
            $v['package_v1'] = ThirdPartBillModel::PACKAGE_V1;
            $v['operator_system_id'] = UserCenter::AUTH_APP_ID_OP;
        }
        // 表格内容发送至消息队列
        self::thirdBillPush($data);
        return $data;
    }

    /**
     * 业务线特殊检查要求
     * @param $params
     * @param $recordData
     * @return bool
     * @throws RunTimeException
     */
    public static function checkoutBusinessCondition($params,$recordData)
    {
        switch ($params['target_business_id']){
            case Constants::SMART_APP_ID:
                return self::smartCondition($params['package_id'],$recordData);
            case Constants::QC_APP_ID:
                return self::qcCondition($recordData);
            default:
                return false;
        }
    }

    /**
     * 智能陪练检查
     * @param $packageId
     * @param $recordData
     * @return bool
     * @throws RunTimeException
     */
    public static function smartCondition($packageId,$recordData)
    {
        $package = ErpPackageV1Model::packageDetail($packageId);
        if ($package['sub_type'] != DssCategoryV1Model::DURATION_TYPE_NORMAL) {
            // 检查是否已经有发货记录
            $records = PayServices::trialedUserByMobile(array_column($recordData, 'mobile'));
            if (!empty($records)) {
                throw new RunTimeException(['has_trialed_records', 'import'], ['list' => $records]);
            }
            //检查用户是否已是年卡用户
            $mobiles = DssStudentModel::getRecords(['mobile' => array_column($recordData, 'mobile'),'has_review_course'=> DssStudentModel::REVIEW_COURSE_1980], ['mobile']);
            if (!empty($mobiles)) {
                throw new RunTimeException(['has_vip_student', 'import'], ['list' => $mobiles]);
            }
        }
        return true;
    }

    /**
     * 根据手机号（批量）检查是否购买过体验课
     * @param $recordData
     * @return bool
     * @throws RunTimeException
     */
    public static function qcCondition($recordData)
    {
        $mobiles = array_column($recordData, 'mobile');
        $data = (new QingChen())->isHaveTrial($mobiles);
        if (!empty($data['have_trial'])){
            throw new RunTimeException(['has_trialed_records', 'import'], ['list' => $data['have_trial']]);
        }
        return true;
    }

    /**
     * 推送消息队列消息
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function thirdBillPush($data)
    {
        try {
            //计算延时时间:由于dss订单支付成功消费者出现并发问题，此处强制每个消息间隔3秒投递
            $defer = 1;
            $queue = new ThirdPartBillTopic();
            foreach ($data as $v) {
                $queue->import($v)->publish($defer);
                $defer += 3;
            }
        } catch (\Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }
        return true;
    }

    /**
     * 第三方导入订单列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function thirdBillList($params, $page, $count)
    {
        $where = ' 1=1 ';
        $map = [];
        $data = ['total_count' => 0, 'records' => []];
        if (!empty($params['mobile'])) {
            $where .= ' and t.mobile = :mobile ';
            $map[':mobile'] = $params['mobile'];
        }
        if (!empty($params['uuid'])) {
            $where .= ' and s.uuid = :uuid ';
            $map[':uuid'] = $params['uuid'];
        }
        if (!empty($params['trade_no'])) {
            $where .= ' and t.trade_no = :trade_no ';
            $map[':trade_no'] = $params['trade_no'];
        }
        if (!empty($params['status'])) {
            $where .= ' and t.status = :status ';
            $map[':status'] = $params['status'];
        }
        if (!empty($params['start_pay_time'])) {
            $where .= ' and t.pay_time >= :start_pay_time ';
            $map[':start_pay_time'] = $params['start_pay_time'];
        }
        if (!empty($params['end_pay_time'])) {
            $where .= ' and t.pay_time <= :end_pay_time ';
            $map[':end_pay_time'] = $params['end_pay_time'];
        }
        //操作后台兼容之前dss后台创建的数据
        if (!empty($params['operator_name'])) {
            $dssEmployeeIds = DssEmployeeModel::getRecords(['name[~]' => $params['operator_name']], ['id']);
            $opEmployeeIds = EmployeeModel::getRecords(['name[~]' => $params['operator_name']], ['id']);
            $employeeIds = array_column(array_merge($dssEmployeeIds, $opEmployeeIds), 'id');
            if (empty($employeeIds)) {
                return $data;
            }
            $where .= ' and t.operator_id in (' . implode(',', $employeeIds) . ')';
        }
        if (!empty($params['parent_channel_id'])) {
            $where .= ' and t.parent_channel_id = :parent_channel_id ';
            $map[':parent_channel_id'] = $params['parent_channel_id'];
        }
        if (!empty($params['channel_id'])) {
            $where .= ' and t.channel_id = :channel_id ';
            $map[':channel_id'] = $params['channel_id'];
        }
        if (!empty($params['package_id'])) {
            $where .= ' and t.package_id = :package_id ';
            $map[':package_id'] = $params['package_id'];
        }
        if (isset($params['package_v1'])) {
            $packageV1 = !empty($params['package_v1']) ? ThirdPartBillModel::PACKAGE_V1 : ThirdPartBillModel::PACKAGE_V1_NOT;
            $where .= ' and t.package_v1 = ' . $packageV1;
        }
        if (!empty($params['is_new'])) {
            $where .= ' and t.is_new = :is_new ';
            $map[':is_new'] = $params['is_new'];
        }
        //数据导入管理后台业务线ID
        if (!empty($params['business_id'])) {
            $where .= ' and t.business_id = :business_id ';
            $map[':business_id'] = $params['business_id'];
        }

        //订单导入-目标业务线ID
        if (!empty($params['target_business_id'])) {
            $where .= ' and t.target_business_id = :target_business_id ';
            $map[':target_business_id'] = $params['target_business_id'];
        }

        //根据第三方角色搜索
        $thirdIdentityTableName = '';
        if (!empty($params['third_identity_id']) && !empty($params['third_identity_type'])) {
            $where .= ' and t.third_identity_id = :third_identity_id ';
            $where .= ' and t.third_identity_type = :third_identity_type ';
            $map[':third_identity_id'] = $params['third_identity_id'];
            $map[':third_identity_type'] = $params['third_identity_type'];
            if ($params['third_identity_type'] == ThirdPartBillModel::THIRD_IDENTITY_TYPE_AGENT) {
                $thirdIdentityTableName = AgentModel::getTableNameWithDb();
            }
        }
        $billList = ThirdPartBillModel::list($where, $map, $page, $count, $thirdIdentityTableName);
        if (!empty($billList['records'])) {
            $statusDict = DictConstants::getSet(DictConstants::THIRD_PART_BILL_STATUS);
            $appIdDict = DictService::getTypeMap('import_target_app_id');
            foreach ($billList['records'] as $k => &$v) {
                $v['status_zh'] = $statusDict[$v['status']];
                $v['mobile'] = $v['country_code'] . '-' . Util::hideUserMobile($v['mobile']);
                $v['target_business_name'] = $appIdDict[$v['target_business_id']] ?? '未知';
                $records[$k] = $v;
            }
        }
        return $billList;
    }


    /**
     * 第三方订单导入消费者
     * @param $params
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function handleImport($params)
    {
        switch ($params['target_business_id']){
            case Constants::SMART_APP_ID;
                return self::handleSmartImport($params);
            case Constants::QC_APP_ID;
                return self::handleQcImport($params);
        }
        return true;
    }

    /**
     * 智能订单导入消费逻辑
     * @param $params
     * @return bool|int|mixed|string|null
     * @throws RunTimeException
     */
    public static function handleSmartImport($params)
    {
        //应用ID
        $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        //基础数据
        $data = [
            'country_code' => $params['country_code'],
            'mobile' => $params['mobile'],
            'trade_no' => $params['trade_no'],
            'pay_time' => $params['pay_time'],
            'package_id' => $params['package_id'],
            'parent_channel_id' => $params['parent_channel_id'],
            'channel_id' => $params['channel_id'],
            'operator_id' => $params['operator_id'],
            'package_v1' => $params['package_v1'],
            'is_new' => ThirdPartBillModel::NOT_NEW,
            'create_time' => time(),
            'business_id' => empty($params['business_id']) ? $appId : $params['business_id'],
            'target_business_id' => empty($params['target_business_id']) ? 0 : $params['target_business_id'],
            'third_identity_id' => (int)$params['third_identity_id'],
            'third_identity_type' => (int)$params['third_identity_type'],
            'paid_in_price' => $params['dss_amount'],
        ];
        //当第三方角色是代理商时候获取代理商转介绍二维码
        $paramMapInfo = [];
        if (!empty($params['third_identity_id']) && ($params['third_identity_type'] == ThirdPartBillModel::THIRD_IDENTITY_TYPE_AGENT)) {
            $paramMapInfo = MiniAppQrService::getSmartQRAliOss($params['third_identity_id'], ParamMapModel::TYPE_AGENT, ['c' => $params['channel_id']]);
        }
        //检测学生数据是否存在：不存在时注册新用户
        $student = DssStudentModel::getRecord(['mobile' => $data['mobile']]);
        if (empty($student)) {
            $result = UserService::studentRegisterBound($appId, $data['mobile'], $data['channel_id'], null, null, null, $paramMapInfo['qr_ticket'], $data['country_code']);
            if (empty($result)) {
                $data['status'] = ThirdPartBillModel::STATUS_FAIL;
                $data['reason'] = 'register student failed';
                $data['student_id'] = 0;
                $data['uuid'] = '';
                return ThirdPartBillModel::insertRecord($data);
            } else {
                $data['student_id'] = $result['student_id'];
                $data['uuid'] = $result['uuid'];
                $data['is_new'] = $result['is_new'] ? ThirdPartBillModel::IS_NEW : ThirdPartBillModel::NOT_NEW;
                $student = DssStudentModel::getById($result['student_id']);
            }
        } else {
            $data['student_id'] = $student['id'];
            $data['uuid'] = $student['uuid'];
        }
        //如果从库数据没有同步成功，使用接口返回值替代
        if (empty($student)) {
            $student = ['id' => $result['student_id'], 'uuid' => $result['uuid']];
        }

        //去重检测
        $checkIsExists = ThirdPartBillModel::getRecord(['student_id' => $data['student_id'], 'trade_no' => $params['trade_no'], 'status' => ThirdPartBillModel::STATUS_SUCCESS], ['id']);
        if (!empty($checkIsExists)) {
            SimpleLogger::error('third part bill have exists', ['data' => $checkIsExists]);
            return true;
        }
        //通过一级渠道ID确认支付方式
        $channelPayMapData = DictConstants::getTypesMap([DictConstants::CHANNEL_PAY_TYPE_MAP['type']])[DictConstants::CHANNEL_PAY_TYPE_MAP['type']];
        //区分操作后台
        $description = "DSS系统表格导入订单";
        if ($params['operator_system_id'] == UserCenter::AUTH_APP_ID_OP) {
            $description = "运营系统表格导入订单";
        }
        //通知ERP创建订单
        $erp = new Erp();
        [$result, $body] = $erp->manCreateDeliverBillV1([
            'uuid' => $student['uuid'],
            'package_id' => $params['package_id'],
            'pay_time' => $params['pay_time'],
            'description' => $description,
            'trade_no' => $params['trade_no'],
            'pay_channel' => $params['pay_channel'],
            'app_id' => $appId,
            'dss_amount' => $params['dss_amount'],
            'sub_type' => empty($channelPayMapData[$params['parent_channel_id']]) ? $channelPayMapData[0]['value'] : $channelPayMapData[$params['parent_channel_id']]['value'],
        ]);
        //记录请求结果
        if ($result === false) {
            $data['reason'] = $body;
            $data['status'] = ThirdPartBillModel::STATUS_FAIL;
        } else {
            $data['status'] = ThirdPartBillModel::STATUS_SUCCESS;
        }

        //如果是代理商创建的体验课订单，记录订单与代理的映射关系
        if (($data['status'] == ThirdPartBillModel::STATUS_SUCCESS)) {
            $billMapRes = BillMapService::mapDataRecord(['param_id' => (int)$paramMapInfo['id'], 'c' => $params['channel_id']], $result['data']['order_id'], $data['student_id']);
            if ($billMapRes) {
                //补发奖励
                $packageInfo = DssErpPackageV1Model::getPackageById($data['package_id']);
                UserRefereeService::buyDeal($student, $packageInfo, $appId, $result['data']['order_id']);
            }
        }
        return ThirdPartBillModel::insertRecord($data);
    }

    /**
     * 清晨订单导入消费逻辑
     * @param $params
     * @return false|int|mixed|string|null
     */
    public static function handleQcImport($params)
    {
        //应用ID
        $appId = Constants::SELF_APP_ID;
        $data = [
            'country_code' => $params['country_code'],
            'mobile' => $params['mobile'],
            'trade_no' => $params['trade_no'],
            'pay_time' => $params['pay_time'],
            'package_id' => $params['package_id'],
            'parent_channel_id' => $params['parent_channel_id'],
            'channel_id' => $params['channel_id'],
            'operator_id' => $params['operator_id'],
            'package_v1' => $params['package_v1'],
            'is_new' => ThirdPartBillModel::NOT_NEW,
            'create_time' => time(),
            'business_id' => empty($params['business_id']) ? $appId : $params['business_id'],
            'target_business_id' => empty($params['target_business_id']) ? 0 : $params['target_business_id'],
            'third_identity_id' => (int)$params['third_identity_id'],
            'third_identity_type' => (int)$params['third_identity_type'],
            'paid_in_price' => $params['dss_amount'],
        ];

        //去重检测
        $checkIsExists = ThirdPartBillModel::getRecord(['mobile' => $data['mobile'], 'trade_no' => $params['trade_no'], 'status' => ThirdPartBillModel::STATUS_SUCCESS], ['id']);
        if (!empty($checkIsExists)) {
            SimpleLogger::error('qc third part bill have exists', ['data' => $checkIsExists]);
            return false;
        }

        $result = (new QingChen())->registerAndOrder($params);
        if ($result['code'] == 1) {
            if ($result['error']['type'] == 'SERVER_ERROR'){
                $data['reason'] = '清晨系统异常';
            }else{
                $data['reason'] = $result['error']['description'];
            }
            $data['status'] = ThirdPartBillModel::STATUS_FAIL;
        } else {
            $data['uuid'] = $result['data']['uuid'] ?? '';
            $data['status'] = ThirdPartBillModel::STATUS_SUCCESS;
        }

        //数据写入数据表
        return ThirdPartBillModel::insertRecord($data);
    }
}

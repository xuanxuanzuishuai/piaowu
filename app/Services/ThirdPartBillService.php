<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 11:04
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AgentBillMapModel;
use App\Models\AgentModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
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
                if (empty($v['A']) || $k == 1) { // 忽略表头和空白行
                    continue;
                }
                $A = trim($v['A']);
                if (trim($v['C']) != ThirdPartBillModel::IGNORE) {
                    $data[] = [
                        'mobile' => $A,
                        'trade_no' => trim($v['B']),
                        'operator_id' => $operatorId,
                        'pay_time' => $now,
                        'create_time' => $now,
                    ];
                }
            }
            // 检查所有的手机号是否合法, 并返回所有错误的记录
            $invalidMobiles = [];
            foreach ($data as $v) {
                if (!Util::isChineseMobile($v['mobile'])) {
                    $invalidMobiles[] = $v;
                }
            }
            if (count($invalidMobiles) > 0) {
                throw new RunTimeException(['invalid_mobile', 'import'], ['list' => $invalidMobiles]);
            }
        } catch (\Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }

        // 检查数据是否为空
        if (count($data) == 0) {
            throw new RunTimeException(['data_can_not_be_empty', 'import']);
        }

        // 学生手机号重复
        if (count($data) != count(array_unique(array_column($data, 'mobile')))) {
            throw new RunTimeException(['mobile_repeat', 'import']);
        }
        // 检查是否已经有发货记录
        $records = PayServices::trialedUserByMobile(array_column($data, 'mobile'));
        if (!empty($records)) {
            throw new RunTimeException(['has_trialed_records', 'import'], ['list' => $records]);
        }
        $params['third_identity_type'] = $params['third_identity_id'] = 0;
        //检测渠道是否为合作代理&检测代理商数据
        if (!empty($params['agent_id'])) {
            $agentInfo = AgentModel::getAgentParentData($params['agent_id']);
            $agentChannelIds = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_dict');
            if (!in_array($params['channel_id'], json_decode($agentChannelIds, true)) || ($agentInfo['p_id'] !== null)) {
                throw new RunTimeException(['agent_info_error'], []);
            }
            $params['third_identity_type'] = ThirdPartBillModel::THIRD_IDENTITY_TYPE_AGENT;
            $params['third_identity_id'] = $params['agent_id'];
        }
        foreach ($data as $k => $v) {
            $v['parent_channel_id'] = $params['parent_channel_id'];
            $v['channel_id'] = $params['channel_id'];
            $v['package_id'] = $params['package_id'];
            $v['business_id'] = $params['business_id'];
            $v['third_identity_id'] = $params['third_identity_id'];
            $v['third_identity_type'] = $params['third_identity_type'];
            $v['package_v1'] = ThirdPartBillModel::PACKAGE_V1;
            $data[$k] = $v;
        }
        // 表格内容发送至消息队列
        self::thirdBillPush($data);
        return $data;
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
            $queue = new ThirdPartBillTopic();
            foreach ($data as $v) {
                $queue->import($v)->publish();
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
            foreach ($billList['records'] as $k => &$v) {
                $v['status_zh'] = $statusDict[$v['status']];
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
        //应用ID
        $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        //基础数据
        $data = [
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
            'third_identity_id' => (int)$params['third_identity_id'],
            'third_identity_type' => (int)$params['third_identity_type'],
        ];

        //检测学生数据是否存在：不存在时注册新用户
        $student = DssStudentModel::getRecord(['mobile' => $data['mobile']]);
        if (empty($student)) {
            $result = UserService::studentRegisterBound($appId, $data['mobile'], $data['channel_id'], null, null, null, $paramMapInfo['qr_ticket']);
            if (empty($result)) {
                $data['status'] = ThirdPartBillModel::STATUS_FAIL;
                $data['reason'] = 'register student failed';
                $data['student_id'] = 0;
                return ThirdPartBillModel::insertRecord($data);
            } else {
                $data['student_id'] = $result['student_id'];
                $data['is_new'] = $result['is_new'] ? ThirdPartBillModel::IS_NEW : ThirdPartBillModel::NOT_NEW;
                $student = DssStudentModel::getById($result['student_id']);
            }
        } else {
            $data['student_id'] = $student['id'];
        }
        //通知ERP创建订单
        $erp = new Erp();
        list($result, $body) = $erp->manCreateDeliverBillV1([
            'uuid' => $student['uuid'],
            'package_id' => $params['package_id'],
            'pay_time' => $params['pay_time'],
            'description' => 'DSS表格导入订单',
            'trade_no' => $params['trade_no'],
            'pay_channel' => $params['pay_channel'],
            'app_id' => $appId,
        ]);
        //记录请求结果
        if ($result === false) {
            $data['reason'] = $body;
            $data['status'] = ThirdPartBillModel::STATUS_FAIL;
        } else {
            $data['status'] = ThirdPartBillModel::STATUS_SUCCESS;
        }

        //如果是代理商创建的体验课订单，记录订单与代理的映射关系
        if (($data['status'] == ThirdPartBillModel::STATUS_SUCCESS) && ($params['third_identity_type'] == ThirdPartBillModel::THIRD_IDENTITY_TYPE_AGENT)) {
            //当第三方角色是代理商时候获取代理商转介绍二维码
            $paramMapInfo = MiniAppQrService::getSmartQRAliOss($params['third_identity_id'], ParamMapModel::TYPE_AGENT);
            $billMapRes = AgentBillMapModel::add($paramMapInfo['qr_ticket'], $result['data']['order_id'], $data['student_id']);
            if ($billMapRes) {
                //补发奖励
                $packageInfo = DssErpPackageV1Model::getPackageById($data['package_id']);
                UserRefereeService::buyDeal($student, $packageInfo, $appId, $result['data']['order_id']);
            }
        }
        return ThirdPartBillModel::insertRecord($data);
    }
}
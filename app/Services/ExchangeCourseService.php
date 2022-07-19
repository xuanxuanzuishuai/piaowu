<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 11:04
 */

namespace App\Services;

use App\Libs\Boss;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\PhpMail;
use App\Libs\QingChen;
use App\Libs\SimpleLogger;
use App\Libs\Spreadsheet;
use App\Libs\Util;
use App\Models\Dss\DssChannelModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\ExchangeCourseModel;
use App\Services\Queue\ThirdPartBillTopic;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExchangeCourseService
{
    const MAX_RECORDS = 10000;

    /**
     * 检查过滤表格数据
     * @param $filename
     * @param $operatorUuid
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function analysisData($filename, $operatorUuid, $params)
    {
        try {
            $data = self::extractFromTemplate($filename);
            if (empty($data)) {
                return $data;
            }
            self::checkData($data);
            $batchId = Util::randString(32);
            $operatorInfo = self::getImportOperatorInfo($operatorUuid);
            foreach ($data as &$v) {
                $v['batch_id'] = $batchId;
                $v['target_app_id'] = $params['target_app_id'];
                $v['import_source'] = $params['import_source'];
                $v['channel_id'] = $params['channel_id'];
                $v['import_uuid'] = $operatorInfo['uuid'] ?? '';
                $v['operator_uuid'] = $operatorInfo['uuid'] ?? '';
                $v['operator_name'] = $operatorInfo['name'] ?? '';
            }
            self::exchangePush($data);
            self::exchangePushFinish($batchId);
            return $data;
        } catch (\Exception $e) {
            throw new RunTimeException(['excel_factory_error', 'import']);
        }
    }

    /**
     * 从模板提取数据
     * @param $filename
     * @return array
     * @throws RunTimeException
     */
    public static function extractFromTemplate($filename)
    {
        try {
            $fileType = ucfirst(pathinfo($filename)["extension"]);
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            foreach ($sheetData as $k => $v) {
                if ($k == 1) { // 忽略表头和空白行
                    continue;
                }
                $A = trim($v['A']);
                $B = trim($v['B']);
                $C = trim($v['C']);
                $D = trim($v['D']);
                //四项必填数据全部为空，忽略不处理
                if (empty($A) && empty($B) && empty($C) && empty($D)) {
                    continue;
                }
                if ($D != ExchangeCourseModel::IGNORE) {
                    $data[] = [
                        'country_code' => $A,
                        'mobile'       => $B,
                        'tag'          => $C
                    ];
                }
            }
            return $data ?? [];
        } catch (\Exception $e) {
            throw new RunTimeException(['excel_factory_error', 'import']);
        }
    }


    /**
     * 导入模板数据共用检查方法
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function checkData(&$data)
    {
        $recordNum = count($data);
        // 检查数据是否为空
        if ($recordNum == 0) {
            throw new RunTimeException(['data_can_not_be_empty', 'import']);
        }
        //检查是够超出最大处理能力
        if ($recordNum > self::MAX_RECORDS) {
            throw new RunTimeException(['over_max_allow_num', 'import']);
        }
        //检查手机号
        $invalidMobiles = self::checkMobile($data);
        if (count($invalidMobiles) > 0) {
            throw new RunTimeException(['invalid_mobile', 'import'], ['list' => $invalidMobiles]);
        }

        // 学生手机号重复
        if ($recordNum != count(array_unique(array_column($data, 'mobile')))) {
            throw new RunTimeException(['mobile_repeat', 'import']);
        }

        //检查标签
        $invalidTag = self::checkTag($data);
        if (count($invalidMobiles) > 0) {
            throw new RunTimeException(['invalid_tag', 'import'], ['list' => $invalidTag]);
        }

        //检查手机号是否被导入过
        $existMobile = self::checkoutExistMobile($data);
        if (count($existMobile) > 0) {
            throw new RunTimeException(['exist_mobile', 'import'], ['list' => $existMobile]);
        }

        return $data;
    }

    /**
     * 检查手机号格式
     * @param $data
     * @return array
     */
    public static function checkMobile($data)
    {
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
        }
        return $invalidMobiles ?? [];
    }

    /**
     * 标签转换边检查是否存在异常标签
     * @param $data
     * @return array
     */
    public static function checkTag(&$data)
    {
        $tagInfo = DictConstants::getSet(DictConstants::EXCHANGE_TAG);
        $tagFlip = array_flip($tagInfo);
        foreach ($data as $key => $value) {
            if (empty($value['tag'])) {
                $data[$key]['tag'] = [];
                continue;
            }
            $data[$key]['tag'] = explode('/', $value['tag']);

            foreach ($data[$key]['tag'] as $k => $tagName) {
                if (in_array($tagName, $tagInfo)) {
                    $data[$key]['tag'][$k] = $tagFlip[$tagName];
                } else {
                    $invalidMobiles[] = $value;
                }
            }
        }
        return $invalidMobiles ?? [];
    }

    /**
     * 检查手机号是否已经导入
     * 且状态不能是处理中或者处理成功状态
     * @param $data
     * @return array
     */
    public static function checkoutExistMobile($data)
    {
        $mobiles = array_column($data, 'mobile');
        $mobileList = array_chunk($mobiles, 1000);
        $filterState = [
            ExchangeCourseModel::STATUS_READY_HANDLE,
            ExchangeCourseModel::STATUS_READY_EXCHANGE,
            ExchangeCourseModel::STATUS_EXCHANGE_SUCCESS
        ];
        foreach ($mobileList as $value) {
            $exist = ExchangeCourseModel::getRecords(['mobile' => $value, 'status[!]' => $filterState], ['mobile']);
            if (empty($exist)) {
                foreach ($exist as $mobile) {
                    $existMobile[] = ['mobile' => $mobile];
                }
            }
        }
        return $existMobile ?? [];
    }

    /**
     * 推送消息队列消息
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function exchangePush($data)
    {
        try {
            $queue = new ThirdPartBillTopic();
            foreach ($data as $v) {
                $defer = round(1, 600);
                $queue->exchangeImport($v)->publish($defer);
            }
        } catch (\Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }
        return true;
    }

    /**
     * 消息通知结束
     * @param $batchId
     * @param int $times
     * @return bool
     * @throws RunTimeException
     */
    public static function exchangePushFinish($batchId, $times = 0)
    {
        $data = [
            'batch_id' => $batchId,
            'times'    => $times
        ];
        try {
            $queue = new ThirdPartBillTopic();
            $queue->exchangeImportFinish($data)->publish(660);
        } catch (\Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }
        return true;
    }

    /**
     * 消费兑课导入的用户
     * @param $msg
     * @return bool
     * @throws RunTimeException
     */
    public static function handleExchangePush($msg)
    {
        $id = self::insertExchangeRecord($msg);
        //检查注册信息
        $userInfo = ErpStudentModel::getUserInfoByMobile($msg['mobile']);
        $existAppId = array_column($userInfo, 'app_id');
        if (!in_array(Constants::SMART_APP_ID, $existAppId)) {
            //注册智能用户
            $studentInfo = (new Dss())->studentRegisterBound([
                'mobile'       => $msg['mobile'],
                'channel_id'   => $msg['channel_id'],
                'country_code' => $msg['country_code'],
            ]);
            $uuid = $studentInfo['uuid'];
        } else {
            $uuid = $userInfo[0]['uuid'];
        }

        //检查在任意系统是否付费
        $payInfo = self::checkPay($uuid, $existAppId);

        //判断处理结果并更新
        self::updateHandleResult($id, $uuid, $existAppId, $payInfo);
        return true;
    }

    /**
     * 兑课用户入表
     * @param $msg
     * @return int|mixed|string|null
     */
    public static function insertExchangeRecord($msg)
    {
        $time = time();
        $insert = [
            'batch_id'      => $msg['batch_id'],
            'target_app_id' => $msg['target_app_id'],
            'import_source' => $msg['import_source'],
            'channel_id'    => $msg['channel_id'],
            'country_code'  => $msg['country_code'],
            'mobile'        => $msg['mobile'],
            'tag'           => implode(',', $msg['tag']),
            'import_uuid'   => $msg['import_uuid'],
            'operator_uuid' => $msg['operator_uuid'],
            'operator_name' => $msg['operator_name'],
            'create_time'   => $time,
            'update_time'   => $time,
        ];
        return ExchangeCourseModel::insertRecord($insert);
    }

    /**
     * 检查用户的付费情况
     * @param $uuid
     * @param $existAppId
     * @return array
     * @throws RunTimeException
     */
    public static function checkPay($uuid, $existAppId)
    {
        if (empty($uuid) || empty($existAppId)) {
            return [];
        }

        //查询智能的付费情况
        if (in_array(Constants::SMART_APP_ID, $existAppId)) {
            $payStatus = [24, 25, 26, 27];
            $res = (new Erp())->getStudentLifeCycle(Constants::SMART_APP_ID, $uuid);
            if (in_array($res['data'][$uuid], $payStatus)) {
                return [
                    'is_pay'   => true,
                    'app_id'   => Constants::SMART_APP_ID,
                    'app_name' => '智能'
                ];
            }
        }

        //查询真人的付费情况
        if (in_array(Constants::REAL_APP_ID, $existAppId)) {
            $payStatus = [16, 17, 18, 19];
            $res = (new Erp())->getStudentLifeCycle(Constants::REAL_APP_ID, $uuid);
            if (in_array($res['data'][$uuid], $payStatus)) {
                return [
                    'is_pay'   => true,
                    'app_id'   => Constants::REAL_APP_ID,
                    'app_name' => '真人'
                ];
            }
        }

        //查询清晨的付费情况
        if (in_array(Constants::QC_APP_ID, $existAppId)) {
            $payStatus = [4, 5];
            $res = (new QingChen())->profileList([$uuid]);
            if (in_array($res['data'][0]['status'], $payStatus)) {
                return [
                    'is_pay'   => true,
                    'app_id'   => Constants::QC_APP_ID,
                    'app_name' => '清晨'
                ];
            }
        }
        return [];
    }

    /**
     * 将兑课用户的处理结果更新到数据表
     * @param $id
     * @param $uuid
     * @param $existAppId
     * @param $payInfo
     * @return int|null
     */
    public static function updateHandleResult($id, $uuid, $existAppId, $payInfo)
    {
        $update = [
            'uuid'        => $uuid,
            'status'      => ExchangeCourseModel::STATUS_READY_EXCHANGE,
            'update_time' => time()
        ];
        if (empty($existAppId)) {
            $update['result_desc'] = '新学员，已导入';
            return ExchangeCourseModel::updateRecord($id, $update);
        }

        if (!empty($payInfo['is_pay']) && $payInfo['is_pay'] == true) {
            $update['status'] = ExchangeCourseModel::STATUS_EXCHANGE_FAIL;
            $update['result_desc'] = '在'.$payInfo['app_name'].'已付费，不可导入';
            return ExchangeCourseModel::updateRecord($id, $update);
        }

        if (in_array(Constants::SMART_APP_ID, $existAppId)) {
            $update['result_desc'] = '未付费学员，已导入';
        } else {
            $update['result_desc'] = '非智能业务线未付费学员，已导入';
        }
        ExchangeCourseModel::updateRecord($id, $update);
    }

    /**
     * 消费兑课批次结束消息
     * @param $msg
     * @return bool
     * @throws RunTimeException
     */
    public static function handleExchangePushFinish($msg)
    {
        $exist = ExchangeCourseModel::getRecord([
            'batch_id' => $msg['batch_id'],
            'status'   => ExchangeCourseModel::STATUS_READY_HANDLE
        ], ['id']);
        if (empty($exist)) {
            //发送邮件
            self::sentResultEmail($msg['batch_id']);
        } else {
            if ($msg['times'] > 2) {
                return false;
            }
            self::exchangePushFinish($msg['batch_id'], $msg['times']++);
        }
        return true;
    }

    /**
     * 将兑课处理结果发送到导入人
     * @param $batchId
     * @return bool
     */
    public static function sentResultEmail($batchId)
    {
        $importUuid = ExchangeCourseModel::getRecord(['batch_id' => $batchId], ['import_uuid']);
        $importInfo = self::getImportOperatorInfo($importUuid['import_uuid']);
        if (empty($importInfo['email'])) {
            SimpleLogger::info('email is empty', ['batch_id' => $batchId]);
            return false;
        }
        $fileName = $importInfo['name'] . '-' . date('Y年m月d日H:i:s');
        // 发放结果的数据保存到excel
        $excelLocalPath = '/tmp/' . $fileName . '.csv';
        try {
            $excelTitle = ['手机号区号', '手机号', 'UUID', '渠道号', '处理结果'];
            $resultData = ExchangeCourseModel::getRecords(['batch_id' => $batchId],
                ['country_code', 'mobile', 'uuid', 'channel_id', 'result_desc']);
            Spreadsheet::createXml($excelLocalPath, $excelTitle, $resultData);
            $emailTitle = '兑课学员导入结果-' . $fileName;
            $content = '本次三方用户导入处理完成' . '总共' . count($resultData) . '条数据，成功处理，可下载附件，查看详细数据';
            PhpMail::sendEmail($importInfo['email'], $emailTitle, $content, $excelLocalPath);
        } catch (\Exception $e) {
            SimpleLogger::info($e->getMessage(), []);
        } finally {
            //删除临时文件
            unlink($excelLocalPath);
        }
        return true;
    }

    /**
     * 获取导入的基本信息
     * @param $uuid
     * @return array|false|mixed
     */
    public static function getImportOperatorInfo($uuid)
    {
        if (empty($uuid)){
            return [];
        }
        return (new Boss())->getEmployeeInfo($uuid);
    }

    /**
     * 导入列表查询
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function importList($params, $page, $count)
    {
        $where = [];
        if (!empty($params['mobile'])) {
            $where['mobile'] = $params['mobile'];
        }
        if (!empty($params['uuid'])) {
            $where['uuid'] = $params['uuid'];
        }
        if (!empty($params['status'])) {
            $where['status'] = $params['status'];
        } else {
            $where['status'] = [
                ExchangeCourseModel::STATUS_READY_EXCHANGE,
                ExchangeCourseModel::STATUS_EXCHANGE_SUCCESS,
                ExchangeCourseModel::STATUS_DELETE,
                ExchangeCourseModel::STATUS_EXCHANGE_FAIL,
            ];
        }
        if (!empty($params['import_start_time']) && !empty($params['import_end_time'])) {
            $where['import_start_time[>=]'] = $params['import_start_time'];
            $where['import_end_time[<=]'] = $params['import_end_time'];
        }
        if (!empty($params['update_start_time']) && !empty($params['update_end_time'])) {
            $where['update_start_time[>=]'] = $params['update_start_time'];
            $where['update_end_time[<=]'] = $params['update_end_time'];
        }
        if (!empty($params['import_source'])) {
            $where['import_source'] = $params['import_source'];
        }

        $list = ExchangeCourseModel::list($where, $page, $count);
        if (empty($list['list'])) {
            return $list;
        }

        $channelIds = array_unique(array_column($list['list'], 'channel_id'));
        $channelPathName = self::getChannelPathName($channelIds);
        $statusMap = DictService::getTypeMap('exchange_status');
        $importSource = DictService::getTypeMap('exchange_import_source');
        foreach ($list['list'] as &$value) {
            $value['channel_path_name'] = $channelPathName[$value['channel_id']];
            $value['import_source_name'] = $importSource[$value['import_source']];
            $value['status_name'] = $statusMap[$value['status']];
            $value['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
            $value['update_time'] = date('Y-m-d H:i:s', $value['update_time']);
        }
        return $list;
    }

    /**
     * 获取渠道路径名称
     * @param $channelIds
     * @return array
     */
    public static function getChannelPathName($channelIds)
    {
        if (empty($channelIds)) {
            return [];
        }
        $channelMap = DssChannelModel::getChannelAndParentInfo($channelIds);
        if (empty($channelMap)) {
            return [];
        }
        foreach ($channelMap as $value) {
            if ($value['app_id'] == Constants::REAL_APP_ID) {
                $appIdName = '真人';
            } elseif ($value['app_id'] == Constants::SMART_APP_ID) {
                $appIdName = '智能';
            } elseif ($value['app_id'] == Constants::QC_APP_ID) {
                $appIdName = '清晨';
            } else {
                $appIdName = '未知';
            }
            $data[$value['id']] = $appIdName . '-' . $value['parent_name'] . '-' . $value['name'];
        }
        return $data ?? [];
    }

    /**
     * 删除指定记录
     * @param $idList
     * @param $uuid
     * @return int|null
     */
    public static function deleteList($idList, $uuid)
    {
        $where = [
            'id'        => $idList,
            'status[!]' => [
                ExchangeCourseModel::STATUS_EXCHANGE_SUCCESS,
                ExchangeCourseModel::STATUS_DELETE,
            ]
        ];

        $operatorInfo = self::getImportOperatorInfo($uuid);
        $update = [
            'status'        => ExchangeCourseModel::STATUS_DELETE,
            'operator_uuid' => $operatorInfo['uuid'] ?? '',
            'operator_name' => $operatorInfo['name'] ?? '',
            'update_time'   => time() ?? '',
        ];
        return ExchangeCourseModel::batchUpdateRecord($update, $where);
    }

    public static function activateSms()
    {

    }

    /**
     * 确认兑换
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function exchangeConfirm($params)
    {
        $where = [
            'mobile'=>$params['mobile'],
            'status'=>ExchangeCourseModel::STATUS_READY_EXCHANGE,
            'ORDER'=>[
                'id'=>'DESC'
            ],
        ];
        $record = ExchangeCourseModel::getRecord($where, ['id','status']);
        if (empty($record)) {
            throw new RuntimeException(['exchange_no_qualification']);
        }

        //再次检查任意渠道是否付费
        $userInfo = ErpStudentModel::getUserInfoByMobile($params['mobile']);
        $existAppId = array_column($userInfo, 'app_id');
        $uuid = $userInfo[0]['uuid'];
        $payInfo = self::checkPay($uuid, $existAppId);
        if (!empty($payInfo)) {
            throw new RuntimeException(['exchange_payed_user']);
        }

        //创建订单
        $update['exchange_time'] = time();
        list($result,$body) =  self::exchangeCreateBill($uuid);
        //记录请求结果
        if ($result === false) {
            $update['status'] = ExchangeCourseModel::STATUS_EXCHANGE_FAIL;
            ExchangeCourseModel::updateRecord($record['id'],$update);
            throw new RuntimeException(['exchange_create_bill_fail']);
        } else {
            $update['status'] = ExchangeCourseModel::STATUS_EXCHANGE_SUCCESS;
            ExchangeCourseModel::updateRecord($record['id'],$update);
        }
        return true;
    }

    /**
     * 确认兑换创建订单
     * @param $uuid
     * @return array
     */
    public static function exchangeCreateBill($uuid)
    {
        $packageId = DictConstants::get(DictConstants::EXCHANGE_CONFIG,'package_id');
         return (new Erp())->manCreateDeliverBillV1([
            'uuid' => $uuid,
            'package_id' => $packageId,
            'pay_time' => time(),
            'description' => '兑课赠送',
            'trade_no' => 'virtual',
            'app_id' => Constants::SMART_APP_ID,
            'sub_type' => 4026
        ]);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\AgentAwardBillExtModel;
use App\Models\AgentOrganizationModel;
use App\Models\AgentPreStorageRefundModel;
use App\Models\AgentPreStorageReviewLogModel;
use App\Models\AgentModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\EmployeeModel;
use App\Libs\SimpleLogger;
use App\Models\AgentPreStorageDetailModel;
use App\Models\AgentPreStorageModel;
use App\Models\AgentPreStorageProcessLogModel;

class AgentStorageService
{
    /**
     * 新增退费申请
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function addRefund(array $params, int $employeeId)
    {
        $time = time();
        //refund数据
        $refundInsertData = [
            'agent_id' => $params['agent_id'],
            'amount' => Util::fen($params['amount']),
            'remark' => $params['remark'] ?? '',
            'employee_id' => $employeeId,
            'create_time' => $time,
            'update_time' => $time,
            'type' => AgentPreStorageRefundModel::TYPE_REFUND_AMOUNT,
        ];


        self::checkAmount($refundInsertData['agent_id'], $refundInsertData['amount']);

        //log数据
        $log = [
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_REFUND,
            'type' => AgentPreStorageReviewLogModel::LOG_TYPE_SUBMIT,
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = AgentPreStorageRefundModel::add($refundInsertData, $log);

        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        } else {
            $db->commit();
        }
        return true;
    }


    /**
     * 校验可退款金额
     *
     * @param int $agentId
     * @param int $refundAmount
     * @return int
     * @throws RunTimeException
     */
    private static function checkAmount(int $agentId, int $refundAmount): int
    {
        $amount = AgentOrganizationModel::getRecord(['agent_id' => $agentId], ['amount']);

        if ($amount['amount'] < $refundAmount) {
            throw new RunTimeException(['agent_amount_not_enough']);
        }

        return $amount['amount'];
    }

    /**
     * 编辑退款申请
     *
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function updateRefund(array $params, int $employeeId)
    {
        $time = time();

        $refundUpdateData = [
            'where' => [
                'id' => $params['refund_id'],
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_REBUT,
            ],
            'data' => [
                'amount' => Util::fen($params['amount']),
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_WAIT,
                'remark' => $params['remark'] ?? '',
                'update_time' => $time,
            ],
        ];
        $refund = AgentPreStorageRefundModel::getById($refundUpdateData['where']['id']);

        if (empty($refund)) {
            throw new RunTimeException(['agent_refund_not_exist']);
        }

        self::checkAmount($refund['agent_id'], $refundUpdateData['data']['amount']);

        //log数据
        $log = [
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_REFUND,
            'data_id' => $refund['id'],
            'type' => AgentPreStorageReviewLogModel::TYPE_RESET_PUSH,
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentPreStorageRefundModel::update($refundUpdateData, [], $log);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        return true;
    }


    /**
     * 代理请款审核
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function verifyRefund(array $params, int $employeeId): bool
    {

        $time = time();

        $refundUpdateData = [
            'where' => [
                'id' => $params['refund_id'],
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_WAIT,
            ]
        ];
        $refundUpdateData['data']['update_time'] = $time;

        $refund = AgentPreStorageRefundModel::getById($refundUpdateData['where']['id']);
        if (empty($refund)) {
            throw new RunTimeException(['agent_refund_not_exist']);
        }

        $log = [
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_REFUND,
            'data_id' => $refund['id'],
            'remark' => $params['remark'] ?? '',
        ];


        $agentAmountData = [];

        if ($params['operation'] == AgentPreStorageRefundModel::STATUS_VERIFY_PASS) {
            $refundUpdateData['data']['status'] = AgentPreStorageRefundModel::STATUS_VERIFY_PASS;

            $log['type'] = AgentPreStorageRefundModel::STATUS_VERIFY_PASS;

            $amount = self::checkAmount($refund['agent_id'],$refund['amount']);

            //agent数据
            $agentAmountData = [
                'where' => [
                    'agent_id' => $refund['agent_id'],
                    'amount' => $amount,
                ],
                'data' => [
                    'amount' => $amount - $refund['amount']
                ]
            ];

        } elseif ($params['operation'] == AgentPreStorageRefundModel::STATUS_VERIFY_REBUT) {
            $refundUpdateData['data']['status'] = AgentPreStorageRefundModel::STATUS_VERIFY_PASS;

            $log['type'] = AgentPreStorageRefundModel::STATUS_VERIFY_REBUT;

        } else {
            return false;
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentPreStorageRefundModel::update($refundUpdateData, $agentAmountData, $log);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        return true;
    }

    /**
     * 新增代理商预存订单数据
     * @param $params
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function addAgentPreStorage($params, $employeeId)
    {
        //检测代理商是否是一级代理商
        $agentData = AgentModel::getAgentParentData([$params['agent_id']]);
        if (empty($agentData) || !empty($agentData['p_id'])) {
            throw new RunTimeException(['agent_info_error']);
        }
        $params['payment_serial_number'] = trim($params['payment_serial_number']);
        //检测流水号是否存在
        $paymentSerialNumber = AgentPreStorageModel::getRecords(['payment_serial_number' => $params['payment_serial_number']], ['id']);
        if (!empty($paymentSerialNumber)) {
            throw new RunTimeException(['payment_serial_number_is_exits']);
        }
        $time = time();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $storageId = AgentPreStorageModel::insertRecord([
            'agent_id' => $params['agent_id'],
            'package_amount' => $params['package_amount'],
            'package_unit_price' => $params['package_unit_price'] * 100,
            'payment_serial_number' => $params['payment_serial_number'],
            'payment_mode' => $params['payment_mode'],
            'payment_time' => $params['payment_time'],
            'payment_screen_shot' => $params['payment_screen_shot'],
            'remark' => empty($params['remark']) ? '' : trim($params['remark']),
            'creater_uid' => $employeeId,
            'create_time' => $time,
        ]);
        if (empty($storageId)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        }
        $logId = AgentPreStorageReviewLogModel::insertRecord([
            'data_id' => $storageId,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_PRE_STORAGE,
            'type' => AgentPreStorageReviewLogModel::LOG_TYPE_SUBMIT,
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
        ]);
        if (empty($logId)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        }
        $db->commit();
        return true;
    }

    /**
     * 编辑代理商预存订单数据
     * @param $params
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function updateAgentPreStorage($params, $employeeId)
    {
        //检测数据是否存在
        $agentStorageData = AgentPreStorageModel::getById($params['storage_id']);
        if (empty($agentStorageData)) {
            throw new RunTimeException(['agent_storage_data_error']);
        }
        //检测当前状态是否允许编辑
        if ($agentStorageData['status'] == AgentPreStorageModel::STATUS_APPROVED) {
            throw new RunTimeException(['agent_storage_data_status_stop_update']);
        }
        $params['payment_serial_number'] = trim($params['payment_serial_number']);
        //检测流水号是否存在
        $paymentSerialNumber = AgentPreStorageModel::getRecords(['id[!]' => $params['storage_id'], 'payment_serial_number' => $params['payment_serial_number']], ['id']);
        if (!empty($paymentSerialNumber)) {
            throw new RunTimeException(['payment_serial_number_is_exits']);
        }
        //检测代理商是否是一级代理商
        $agentData = AgentModel::getAgentParentData([$params['agent_id']]);
        if (empty($agentData) || !empty($agentData['p_id'])) {
            throw new RunTimeException(['agent_info_error']);
        }
        $time = time();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $storageUpdateRes = AgentPreStorageModel::updateRecord($params['storage_id'], [
            'package_amount' => $params['package_amount'],
            'package_unit_price' => $params['package_unit_price'] * 100,
            'payment_serial_number' => $params['payment_serial_number'],
            'payment_mode' => $params['payment_mode'],
            'payment_time' => $params['payment_time'],
            'payment_screen_shot' => $params['payment_screen_shot'],
            'remark' => trim($params['remark']),
            'update_time' => $time,
            'status' => AgentPreStorageModel::STATUS_WAIT,
        ]);
        if (empty($storageUpdateRes)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        }
        $logId = AgentPreStorageReviewLogModel::insertRecord([
            'data_id' => $params['storage_id'],
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_PRE_STORAGE,
            'type' => AgentPreStorageReviewLogModel::TYPE_RESET_PUSH,
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
        ]);
        if (empty($logId)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        }
        $db->commit();
        return true;
    }

    /**
     * 代理商预存订单详细数据
     * @param $storageId
     * @return array
     */
    public static function getAgentPreStorageDetail($storageId)
    {
        $detailData = AgentPreStorageModel::getRecords(['id' => $storageId]);
        if (empty($detailData)) {
            return [];
        }
        $data = self::formatPreStorageSearchResult($detailData)[0];
        $data['review_log'] = self::getAgentPreStorageReviewLog($storageId);
        return $data;
    }


    /**
     * 获取预存年卡消费与进帐日志
     * @param $params
     * @return array
     */
    public static function getAgentPreStorageProcessLog($params)
    {
        $data = [
            'total_count' => 0,
            'list' => []
        ];
        $count = AgentPreStorageProcessLogModel::getCount(['agent_id' => $params['agent_id']]);
        if (empty($count)) {
            return $data;
        }
        $data['total_count'] = $count;
        $processLogData = AgentPreStorageProcessLogModel::getRecords(
            [
                'agent_id' => $params['agent_id'],
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [($params['page'] - 1) * $params['count'], $params['count']]
            ],
            [
                'bill_id', 'amount', 'type', 'create_time'
            ]);
        if (empty($processLogData)) {
            return $data;
        }
        $dictData = DictConstants::getSet(DictConstants::AGENT_STORAGE_PROCESS_LOG_TYPE);
        foreach ($processLogData as &$rv) {
            $rv['type_name'] = $dictData[$rv['type']];
            $rv['amount_show'] = (($rv['type'] == AgentPreStorageProcessLogModel::TYPE_NORMAL_CARD_STORAGE) ? '+' : '-') . $rv['amount'];
            $rv['create_time_show'] = date('Y-m-d H:i:s', $rv['create_time']);
        }
        $data['list'] = $processLogData;
        return $data;
    }


    /**
     * 获取预存订单审核日志
     * @param $storageId
     * @return array
     */
    private static function getAgentPreStorageReviewLog($storageId)
    {
        $reviewLogData = AgentPreStorageReviewLogModel::getRecords(
            [
                'data_id' => $storageId,
                'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_PRE_STORAGE,
                'ORDER' => ['id' => 'DESC']
            ],
            [
                'type', 'remark', 'reviewer_uid', 'create_time'
            ]);
        if (empty($reviewLogData)) {
            return [];
        }
        $dictData = DictConstants::getSet(DictConstants::AGENT_STORAGE_APPROVED_ACTION);
        $employeeData = array_column(EmployeeModel::getRecords(['id' => array_column($reviewLogData, 'reviewer_uid')], ['id', 'name']), null, 'id');
        foreach ($reviewLogData as &$rv) {
            $rv['type_show'] = $dictData[$rv['type']];
            $rv['reviewer_name'] = $employeeData[$rv['reviewer_uid']]['name'];
            $rv['create_time_show'] = date('Y-m-d H:i:s', $rv['create_time']);
        }
        return $reviewLogData;
    }


    /**
     * 代理商预存订单列表数据
     * @param $params
     * @return array
     */
    public static function getAgentPreStorageList($params)
    {
        //组合搜索条件
        $where = self::formatPreStorageSearchWhere($params);
        $data = [
            'total_count' => 0,
            'list' => []
        ];
        if (empty($where)) {
            return $data;
        }
        $count = AgentPreStorageModel::getCount($where);
        if (empty($count)) {
            return $data;
        }
        $data['total_count'] = $count;
        $where['LIMIT'] = [($params['page'] - 1) * $params['count'], $params['count']];
        $where['ORDER'] = ['id' => 'DESC'];
        $data['list'] = self::formatPreStorageSearchResult(AgentPreStorageModel::getRecords($where));
        return $data;
    }

    /**
     * 格式化代理商预存订单查询条件
     * @param $params
     * @return array
     */
    private static function formatPreStorageSearchWhere($params)
    {
        $where = ['id[>]' => 0];
        $agentIds = [];
        //预存单号
        if ($params['storage_id']) {
            $where['id'] = (int)$params['storage_id'];
        }
        //代理商ID与代理商名称
        if ($params['agent_name']) {
            $agentIds = AgentModel::getRecords(['name[~]' => $params['agent_name']], 'id');
            if (empty($agentIds)) {
                return [];
            }
        }
        if (!empty($params['agent_id'])) {
            $agentIds[] = (int)$params['agent_id'];
        }
        if (!empty($agentIds)) {
            $where['agent_id'] = $agentIds;
        }
        //流水号
        if ($params['payment_serial_number']) {
            $where['payment_serial_number'] = $params['payment_serial_number'];
        }
        //付款时间
        if ($params['payment_time_start']) {
            $where['payment_time[>=]'] = (int)$params['payment_time_start'];
        }
        if ($params['payment_time_end']) {
            $where['payment_time[<=]'] = (int)$params['payment_time_end'];
        }
        //支付方式
        if ($params['payment_mode']) {
            $where['payment_mode'] = (int)$params['payment_mode'];
        }
        //当前状态
        if ($params['status']) {
            $where['status'] = (int)$params['status'];
        }
        return $where;
    }

    /**
     * 格式化代理商预存订单查询条件
     * @param $list
     * @return array
     */
    private static function formatPreStorageSearchResult($list)
    {
        if (empty($list)) {
            return [];
        }
        //获取代理商数据
        $agentData = array_column(AgentModel::getRecords(['id' => array_column($list, 'agent_id')], ['id', 'name']), null, 'id');
        $dictData = DictConstants::getTypesMap([DictConstants::PAYMENT_MODE['type'], DictConstants::CHECK_STATUS['type']]);
        foreach ($list as &$value) {
            $value['agent_name'] = $agentData[$value['agent_id']]['name'];
            $value['payment_mode_show'] = $dictData[DictConstants::PAYMENT_MODE['type']][$value['payment_mode']]['value'];
            $value['status_show'] = $dictData[DictConstants::CHECK_STATUS['type']][$value['status']]['value'];
            $value['total_amount'] = Util::yuan(($value['package_amount'] * $value['package_unit_price']));
            $value['package_unit_price'] = Util::yuan($value['package_unit_price'], 0);
            $value['payment_time_show'] = date('Y-m-d H:i:s', $value['payment_time']);
            $value['payment_screen_shot_oss_url'] = AliOSS::signUrls($value['payment_screen_shot']);
        }
        return $list;
    }

    /**
     * 预存订单审核
     * @param $storageId
     * @param $status
     * @param $remark
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function approvalAgentPreStorage($storageId, $status, $remark, $employeeId)
    {
        //获取预存订单数据
        $agentStorageData = AgentPreStorageModel::getById($storageId);
        if (empty($agentStorageData)) {
            throw new RunTimeException(['agent_storage_data_error']);
        }
        if ($agentStorageData['status'] == $status) {
            throw new RunTimeException(['nothing_change']);
        }
        //检测当前状态是否允许编辑
        if ($agentStorageData['status'] == AgentPreStorageModel::STATUS_APPROVED) {
            throw new RunTimeException(['agent_storage_data_status_stop_update']);
        }
        //拒绝通过，必须填写备注原因
        if (($status == AgentPreStorageModel::STATUS_REJECT) && empty($remark)) {
            throw new RunTimeException(['reject_reason_is_required']);
        }
        $time = time();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //第一步：修改数据状态
        $statusRes = AgentPreStorageModel::updateRecord($storageId, ['status' => $status, 'update_time' => $time]);
        if (empty($statusRes)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        }
        //第二步：审核结果日志记录
        $logId = AgentPreStorageReviewLogModel::insertRecord([
            'data_id' => $storageId,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_PRE_STORAGE,
            'type' => $status,
            'remark'=> (string)$remark,
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
        ]);
        if (empty($logId)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        }
        //第三步区分审核结果：如果审核通过，记录预存订单审核成功日志
        if ($status == AgentPreStorageModel::STATUS_APPROVED) {
            $processLogId = AgentPreStorageProcessLogModel::insertRecord([
                'agent_id' => $agentStorageData['agent_id'],
                'bill_id' => $storageId,
                'amount' => $agentStorageData['package_amount'],
                'type' => AgentPreStorageProcessLogModel::TYPE_NORMAL_CARD_STORAGE,
                'create_time' => $time,
            ]);
            if (empty($processLogId)) {
                $db->rollBack();
                throw new RunTimeException(['insert_failure']);
            }
            //拆分预存订单为多个子年卡记录
            $detailRes = AgentPreStorageDetailModel::batchInsert(array_fill(0, $agentStorageData['package_amount'], [
                'pre_storage_id' => $storageId,
                'status' => AgentPreStorageDetailModel::STATUS_NOT_CONSUMED,
                'create_time' => $time
            ]));
            if (empty($detailRes)) {
                $db->rollBack();
                throw new RunTimeException(['insert_failure']);
            }
            //更新代理商扩展信息表中预存订单年卡剩余数量
            $infoRes = AgentOrganizationModel::batchUpdateRecord(["quantity[+]" => $agentStorageData['package_amount']], ['agent_id' => $agentStorageData['agent_id']]);
            if (empty($infoRes)) {
                $db->rollBack();
                throw new RunTimeException(['update_failure']);
            }
        }
        $db->commit();
        //将支付时间开始，截止到当前时间，此代理商作为成单代理商角色的订单与预存订单拆分的子年卡记录进行一一对应消费
        self::agentStorageConsumer($agentStorageData['agent_id']);
        return true;
    }

    /**
     * 处理代理商预存订单的消费逻辑
     * @param $agentId
     * @return bool
     */
    public static function agentStorageConsumer($agentId)
    {
        //检测代理商剩余年卡数量是否大于0
        $agentData = AgentOrganizationModel::getRecord(['agent_id' => $agentId], ['quantity[Int]', 'id', 'amount']);
        if ($agentData['quantity'] <= 0) {
            SimpleLogger::info('agent storage quantity eq or lt 0 ', $agentData);
            return true;
        }
        //订单观察期
        $intervalTimeDays = DictConstants::get(DictConstants::AGENT_STORAGE_CONFIG, 'interval_time_days');
        //获取代理商预存订单信息
        $canConsumerStorageData = array_column(AgentPreStorageModel::getPreStorageRemainingNormalCardData($agentId, $intervalTimeDays), null, 'id');
        if (empty($canConsumerStorageData)) {
            SimpleLogger::info('agent no can consumer storage ', []);
            return true;
        };
        //获取支付时间后并且此代理商作为成单代理商角色的订单
        $agentBillData = AgentAwardBillExtModel::getAgentAsSignerNormalBill($agentId, array_column($canConsumerStorageData, 'payment_time')[0], strtotime('-' . $intervalTimeDays . ' days'));
        if (empty($agentBillData)) {
            SimpleLogger::info('agent no recommend bill ', []);
            return true;
        }
        //检测订单状态是否正常：指定的时间段内没有退款
        $normalsStatusBill = DssGiftCodeModel::getRecords(['parent_bill_id' => array_column($agentBillData, 'parent_bill_id'), 'code_status[!]' => DssGiftCodeModel::CODE_STATUS_INVALID], ['parent_bill_id']);
        if (empty($normalsStatusBill)) {
            SimpleLogger::info('bill status id refund', [$agentBillData]);
            return true;
        }
        $billCount = count($normalsStatusBill);
        //获取预存订单拆分的年卡订单数据
        $storageDetailList = AgentPreStorageDetailModel::getRecords(
            [
                'pre_storage_id' => array_column($canConsumerStorageData, 'id'),
                'status' => AgentPreStorageDetailModel::STATUS_NOT_CONSUMED,
                'ORDER' => ['id' => 'ASC'],
                'LIMIT' => [0, $billCount]
            ],
            ['pre_storage_id', 'id']);
        $time = time();
        $storageDetailUpdateData = $processLogInsertData = $refundInsertData = [];
        //消耗的年卡数量/退款金额
        $consumerAmount = $refundAmount = 0;
        foreach ($normalsStatusBill as $cv) {
            if (empty($storageDetailList)) {
                break;
            }
            //预存订单的年卡消耗数据
            $preStorageDetail = array_shift($storageDetailList);
            $storageDetailUpdateData[$preStorageDetail['id']]['id'] = $preStorageDetail['id'];
            $storageDetailUpdateData[$preStorageDetail['id']]['data'] = [
                'status' => AgentPreStorageDetailModel::STATUS_CONSUMED,
                'parent_bill_id' => $cv['parent_bill_id'],
                'update_time' => $time,
            ];
            //记录预存订单消费日志
            $processLogInsertData[] = [
                'agent_id' => $agentId,
                'bill_id' => $cv['parent_bill_id'],
                'amount' => 1,
                'type' => AgentPreStorageProcessLogModel::TYPE_PROMOTION_CONSUMPTION,
                'create_time' => $time,
            ];

            //记录代理商退款日志
            $refundInsertData[] = [
                'agent_id' => $agentId,
                'amount' => $canConsumerStorageData[$preStorageDetail['pre_storage_id']]['package_unit_price'],
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_PASS,
                'bill_id' => $cv['parent_bill_id'],
                'create_time' => $time,
            ];
            $consumerAmount += 1;
            $refundAmount += $canConsumerStorageData[$preStorageDetail['pre_storage_id']]['package_unit_price'];
        }
        //操作数据库
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        foreach ($storageDetailUpdateData as $suk => $suv) {
            $detailUpdateRes = AgentPreStorageDetailModel::updateRecord($suv['id'], $suv['data']);
            if (empty($detailUpdateRes)) {
                SimpleLogger::info('agent storage detail consumer update fail ', []);
                $db->rollBack();
                return false;
            }
        }
        $processLogRes = AgentPreStorageProcessLogModel::batchInsert($processLogInsertData);
        $refundRes = AgentPreStorageRefundModel::batchInsert($refundInsertData);
        $agentInfoRes = AgentOrganizationModel::batchUpdateRecord(['quantity[-]' => $consumerAmount, 'amount[+]' => $refundAmount], ['agent_id' => $agentId, 'quantity' => $agentData['quantity'], 'amount' => $agentData['amount']]);
        if (empty($processLogRes) || empty($refundRes) || empty($agentInfoRes)) {
            SimpleLogger::info('agent storage log data update fail ', []);
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }

    /**
     * 退款列表
     * @param array $params
     * @param int $employeeId
     * @return array
     */
    public static function listRefund(array $params, int $employeeId): array
    {
        $where = [AgentModel::$table . '.parent_id' => 0];

        if ($params['data_type'] != 'record') {
            $where[AgentPreStorageRefundModel::$table . '.type'] = AgentPreStorageRefundModel::TYPE_REFUND_AMOUNT;
            //数据权限
            if ($params['only_read_self']) {
                $where[AgentPreStorageRefundModel::$table . '.employee_id'] = $employeeId;
            }
        }else{
            $where[AgentPreStorageRefundModel::$table . '.status'] = AgentPreStorageRefundModel::STATUS_VERIFY_PASS;
        }

        if (!empty($params['agent_id'])) {
            $where[AgentPreStorageRefundModel::$table . '.agent_id'] = $params['agent_id'];
        }
        if (!empty($params['id'])) {
            $where[AgentPreStorageRefundModel::$table . '.id'] = $params['id'];
        }
        if (!empty($params['status'])) {
            $where[AgentPreStorageRefundModel::$table . '.status'] = $params['status'];
        }
        if (!empty($params['create_start_time'])) {
            $where[AgentPreStorageRefundModel::$table . '.create_time[>=]'] = $params['create_start_time'];
        }
        if (!empty($params['create_end_time'])) {
            $where[AgentPreStorageRefundModel::$table . '.create_time[<=]'] = $params['create_end_time'];
        }
        if (!empty($params['name'])) {
            $where[AgentModel::$table . '.name[~]'] = $params['name'];
        }

        $data =  AgentPreStorageRefundModel::list($where, $params['page'], $params['count']);

        $dictData = DictConstants::getTypesMap([DictConstants::CHECK_STATUS['type']]);

        foreach ($data['list'] as &$value) {
            $value['status_show']      = $dictData[DictConstants::CHECK_STATUS['type']][$value['status']]['value'] ?? '';
            $value['type_show']        = AgentPreStorageRefundModel::TYPE_MAP[$value['type']];
            $value['amount']           = Util::yuan($value['amount']);
            $value['amount_show']      = ($value['type'] == AgentPreStorageRefundModel::TYPE_REFUND_AMOUNT ? '-' : '+') . $value['amount'];
            $value['bill_id']          = $value['bill_id'] ?: $value['id'];
            $value['create_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
        }

        return $data;
    }

    /**
     * 退款详情
     * @param int $refundId
     * @return array
     */
    public static function detailRefund(int $refundId): array
    {
        $detail = AgentPreStorageRefundModel::detail($refundId);
        if (!empty($detail)) {
            $dictData = DictConstants::getTypesMap([
                DictConstants::AGENT_STORAGE_APPROVED_ACTION['type'],
                DictConstants::CHECK_STATUS['type']
            ]);

            $detail['status_show']      = $dictData[DictConstants::CHECK_STATUS['type']][$detail['status']]['value'];
            $detail['amount']           = (int)Util::yuan($detail['amount']);
            $detail['create_time_show'] = date('Y-m-d H:i:s', $detail['create_time']);
            $detail['operation_log']    = AgentPreStorageReviewLogModel::getLogList($refundId);

            foreach ($detail['operation_log'] as &$value) {
                $value['type_show']        = AgentPreStorageReviewLogModel::REFUND_TYPE_MAP[$value['type']];
                $value['create_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
            }
        }
        return $detail;
    }


}
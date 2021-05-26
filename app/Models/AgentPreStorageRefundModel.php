<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:52
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class AgentPreStorageRefundModel extends Model
{
    public static $table = "agent_pre_storage_refund";
    //状态：1待审核 2审核通过 3 审核驳回
    const STATUS_VERIFY_WAIT = 1;
    const STATUS_VERIFY_PASS = 2;
    const STATUS_VERIFY_REBUT = 3;

    //1推广消耗 2 退款打款
    const TYPE_SPREAD_CONSUME = 1;
    const TYPE_REFUND_AMOUNT = 2;

    const TYPE_MAP = [
        self::TYPE_SPREAD_CONSUME => '推广消耗',
        self::TYPE_REFUND_AMOUNT => '退款打款'
    ];

    /**
     * 新增代理退款申请
     * @param array $refundData
     * @param array $agentAmountData
     * @return bool
     */
    public static function add($refundData, $agentAmountData): bool
    {
        //记录代理商退款申请数据
        $refundId = self::insertRecord($refundData);
        if (empty($refundId)) {
            SimpleLogger::error('insert agent pre storage refund data error', $refundData);
            return false;
        }
        $updateRow = AgentInfoModel::batchUpdateRecord($agentAmountData['data'], $agentAmountData['where']);
        if (empty($updateRow)) {
            SimpleLogger::error('update agent amount data error', $agentAmountData);
            return false;
        }
        return true;
    }

    /**
     * 编辑代理退款申请
     *
     * @param array $refundUpdateData
     * @param array $agentAmountData
     * @param array $log
     * @return bool
     */
    public static function update(array $refundUpdateData, array $agentAmountData, array $log) :bool
    {

        $refundUpdateRow = self::updateRecord($refundUpdateData['where']['id'],$refundUpdateData['data']);

        if (empty($refundUpdateRow)) {
            SimpleLogger::error('update agent pre storage refund data error', $refundUpdateData);
            return false;
        }

        if (!empty($agentAmountData)){
            $agentUpdateRow = AgentInfoModel::batchUpdateRecord($agentAmountData['data'], $agentAmountData['where']);
            if (empty($agentUpdateRow)) {
                SimpleLogger::error('update agent amount data error', $agentAmountData);
                return false;
            }
        }

        $logId = AgentPreStorageReviewLogModel::insertRecord($log);

        if (empty($logId)) {
            SimpleLogger::error('insert agent pre storage review log data error', $log);
            return false;
        }

        return true;
    }

    /**
     * 获取列表数据
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public static function list(array $where, int $page, int $limit): array
    {
        $data = ['count' => 0, 'list' => [],];
        $db = MysqlDB::getDB();
        $data['count'] = $db->count(self::$table, [
            "[><]" . AgentModel::$table => ['agent_id' => 'id'],
            "[>]" . EmployeeModel::$table => ['employee_id' => 'id'],
        ], [self::$table . '.id'], $where);
        if (empty($data['count'])) {
            return $data;
        }
        $offset = ($page - 1) * $limit;
        $data['list'] = $db->select(
            self::$table,
            [
                "[><]" . AgentModel::$table => ['agent_id' => 'id'],
                "[>]" . EmployeeModel::$table => ['employee_id' => 'id'],
            ],
            [
                self::$table . '.id',
                self::$table . '.agent_id',
                self::$table . '.type',
                self::$table . '.amount',
                self::$table . '.create_time',
                self::$table . '.status',
                self::$table . '.employee_id',
                AgentModel::$table . '.name',
                EmployeeModel::$table . '.name(employee_name)' ,
            ],
            [
                "AND" => $where,
                "ORDER" => [self::$table . ".id" => 'DESC'],
                "LIMIT" => [$offset, $limit],
            ]
        );
        return $data;
    }


    /**
     * 获取退款详情
     * @param int $refundId
     * @return mixed
     */
    public static function detail(int $refundId): array
    {
        $db = MysqlDB::getDB();
        $data = $db->select(
            self::$table,
            [
                "[><]" . AgentModel::$table => ['agent_id' => 'id'],
                "[>]" . EmployeeModel::$table => ['employee_id' => 'id'],
            ],
            [
                self::$table . '.id',
                self::$table . '.agent_id',
                self::$table . '.amount',
                self::$table . '.create_time',
                self::$table . '.status',
                self::$table . '.employee_id',
                AgentModel::$table . '.name',
                EmployeeModel::$table . '.name(employee_name)',
            ],
            [
                self::$table . '.id' => $refundId,
            ]
        );
        return $data[0] ?? [];
    }
}
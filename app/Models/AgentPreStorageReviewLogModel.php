<?php
namespace App\Models;

use App\Libs\MysqlDB;

class AgentPreStorageReviewLogModel extends Model
{
    public static $table = "agent_pre_storage_review_log";
    //状态：1预存年卡订单 2退款
    const DATA_TYPE_REFUND = 2;

    //再次提交
    const TYPE_RESET_PUSH = 4;


    /**
     * 获取记录
     *
     * @param int $refundId
     * @return array
     */
    public static function getLogList(int $refundId): array
    {

        $db = MysqlDB::getDB();

        $data =  $db->select(
            self::$table,
            [
                "[>]" . EmployeeModel::$table => ['reviewer_uid' => 'id'],
            ],
            [
                self::$table . '.id',
                self::$table . '.remark',
                self::$table . '.create_time',
                self::$table . '.type',
                self::$table . '.reviewer_uid',
                EmployeeModel::$table . '.name(reviewer_name)',
            ],
            [
                "AND" => [
                    'data_id' => $refundId,
                ],
                "ORDER" => [self::$table . ".create_time" => 'DESC'],
            ]
        );
        return $data ?: [];
    }



}
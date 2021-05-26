<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:52
 */

namespace App\Models;

use App\Libs\MysqlDB;
class AgentPreStorageReviewLogModel extends Model
{
    public static $table = "agent_pre_storage_review_log";
    //状态：1预存年卡订单 2退款
    const DATA_TYPE_PRE_STORAGE = 1;
    const DATA_TYPE_REFUND = 2;
    //日志操作类型：1提交 2审核通过 3驳回审核不通过 4重新提交
    const LOG_TYPE_SUBMIT = 1;
    const LOG_TYPE_APPROVED = 2;
    const LOG_TYPE_REJECT = 3;
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
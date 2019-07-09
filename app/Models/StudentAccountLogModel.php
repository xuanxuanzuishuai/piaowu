<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-29
 * Time: 15:16
 */

namespace App\Models;


use App\Libs\MysqlDB;

class StudentAccountLogModel extends Model
{
    public static $table = "student_account_log";

    const TYPE_ADD = 1; // 入账
    const TYPE_REDUCE = 2; // 消费
    const TYPE_DISCARD = 3; // 作废

    public static function insertSAL($studentAccountLogs)
    {
        return self::insertRecord($studentAccountLogs);
    }

    /**
     * 获取账户操作记录
     * @param $studentId
     * @param $orgId
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getSALs($studentId, $orgId, $page = -1, $count = 20)
    {

        $totalCount = MysqlDB::getDB()->count(self::$table, [
            '[><]' . StudentAccountModel::$table => ['s_a_id' => 'id']
        ], [
            self::$table . '.id'
        ], [
            StudentAccountModel::$table . '.student_id' => $studentId,
            StudentAccountModel::$table . '.org_id' => $orgId
        ]);
        if ($totalCount == 0) {
            return [[], 0];
        }

        $logs = MysqlDB::getDB()->select(self::$table, [
            '[><]' . EmployeeModel::$table => ['operator_id' => 'id'],
            '[><]' . StudentAccountModel::$table => ['s_a_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.create_time',
            self::$table . '.balance',
            self::$table . '.type',
            self::$table . '.operator_id',
            self::$table . '.remark',
            EmployeeModel::$table . '.name(operator_name)'
        ], [
            StudentAccountModel::$table . '.student_id' => $studentId,
            StudentAccountModel::$table . '.org_id' => $orgId,
            'ORDER' => [self::$table . '.create_time' => 'DESC'],
            'LIMIT' => [($page - 1) * $count, $count]
        ]);

        return [$logs, $totalCount];
    }

    /**
     * 获取用户购买记录
     * @param $studentId
     * @param $orgId
     * @return array
     */
    public static function getAddSALog($studentId, $orgId)
    {
        $sql = "SELECT
    SUM(l.balance) balance, sa.student_id, sa.type
FROM
    " . self::$table . " l
        INNER JOIN
    " . StudentAccountModel::$table . " sa ON sa.id = l.s_a_id
WHERE
    sa.student_id IN (" . implode(',', $studentId) .  ") AND org_id = {$orgId}
        AND l.type IN (" . self::TYPE_ADD . ", " . self::TYPE_DISCARD . ")";

        $balances = MysqlDB::getDB()->queryAll($sql);
        return !empty($balances) ? $balances : [];
    }
}
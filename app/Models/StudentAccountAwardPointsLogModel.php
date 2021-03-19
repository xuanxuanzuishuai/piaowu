<?php


namespace App\Models;


use App\Libs\MysqlDB;

class StudentAccountAwardPointsLogModel extends Model
{
    public static $table = 'student_account_award_points_log';

    const APPID_SUBTYPE_EXPLODE = '_';  //app_id 和 sub_type 分割符

    /**
     * 获取列表
     * @param $where
     * @param $page
     * @param $count
     * @return array
     */
    public static function getList($where, $page, $count)
    {
        $returnData = ['total' => 0, 'list' => []];
        $db = MysqlDB::getDB();

        $returnData['total'] = $db->count(self::$table . '(b)', $where);
        if ($returnData['total'] <= 0) {
            return $returnData;
        }

        $listWhere = $where;
        $listWhere['LIMIT'] = [($page - 1) * $count, $count];
        $returnData['list'] = $db->select(self::$table . '(b)', [
            '[>]' . EmployeeModel::$table . '(e)' => ['b.operator_id' => 'id'],
        ], [
            'e.name(operator_name)',
            'b.id',
            'b.operator_id',
            'b.operator_type',
            'b.app_id',
            'b.sub_type',
            'b.num',
            'b.student_id',
            'b.uuid',
            'b.mobile',
            'b.remark',
            'b.create_time',
        ], $listWhere);
        return $returnData;
    }
}
<?php


namespace App\Models;


use App\Libs\MysqlDB;

class StudentAccountAwardPointsLogModel extends Model
{
    public static $table = 'student_account_award_points_log';

    const APPID_SUBTYPE_EXPLODE = '_';  //app_id 和 sub_type 分割符

    /**
     * 获取列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getList($params, $page, $count)
    {
        $where = [];
        if (!empty($params['uuid'])) {
            $where['b.uuid'] = $params['uuid'];
        }
        if (!empty($params['mobile'])) {
            $where['b.mobile'] = $params['mobile'];
        }
        if (!empty($params['account_name'])) {
            $accountType = explode(StudentAccountAwardPointsLogModel::APPID_SUBTYPE_EXPLODE, $params['account_name']);
            $where['b.app_id'] = $accountType[0] ?? 0;
            $where['b.sub_type'] = $accountType['1'] ?? 0;
        }
        if (!empty($params['start_time'])) {
            $where['b.create_time[>=]'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where['b.create_time[<=]'] = strtotime($params['end_time']);
        }
        $returnData = ['total' => 0, 'list' => []];
        $db = MysqlDB::getDB();

        $returnData['total'] = $db->count(self::$table . '(b)', $where);
        if ($returnData['total'] <= 0) {
            return $returnData;
        }

        $listWhere = $where;
        $listWhere['LIMIT'] = [($page - 1) * $count, $count];
        $list = $db->select(self::$table . '(b)', [
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
        $returnData['list'] = is_array($list) ? $list : [];
        return $returnData;
    }
}
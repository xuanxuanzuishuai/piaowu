<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/22
 * Time: 5:14 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class DeptPrivilegeModel extends Model
{
    public static $table = 'dept_privilege';

    const PRIVILEGE_NONE = 0;
    const PRIVILEGE_SELF = 1;
    const PRIVILEGE_SUBS = 2;
    const PRIVILEGE_ALL = 3;
    const PRIVILEGE_CUSTOM = 4;

    const DATA_TYPE_STUDENT = 1;

    public static function getByDept($deptId)
    {
        $db = MysqlDB::getDB();

        $where = ['dp.dept_id' => $deptId];

        $records = $db->select(self::$table . '(dp)', [
            '[>]' . EmployeeModel::$table . '(e)' => ['dp.operator' => 'id']
        ], [
            'dp.id',
            'dp.dept_id',
            'dp.data_type',
            'dp.privilege_type',
            'dp.privilege_custom',
            'dp.create_time',
            'dp.status',
            'dp.update_time',
            'dp.operator',
            'e.name(operator_name)',
        ], $where);

        return $records ?? [];

    }
}
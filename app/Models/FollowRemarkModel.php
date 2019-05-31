<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/19
 * Time: 上午9:54
 */

namespace App\Models;



use App\Libs\MysqlDB;

class FollowRemarkModel extends Model
{
    public static $table = 'follow_remark';

    public static function getCountNum($where, $isOrg = true)
    {
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0 &&  empty($where[self::ORG_ID_STR]))
                $where[self::ORG_ID_STR] = $orgId;
        }
        return MysqlDB::getDB()->count(self::$table, $where);
    }

    /**
     * @param $where
     * @param bool $isOrg
     * @return array
     * 查看跟进记录
     */
    public static function getRemarkLog($where, $isOrg = true)
    {
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0 &&  empty($where[self::ORG_ID_STR]))
                $where[self::ORG_ID_STR] = $orgId;
        }
        return MysqlDB::getDB()->select(self::$table,
            [
                '[><]' . EmployeeModel::$table => ['operator_id' => 'id']
            ],
            [
              self::$table . '.remark',
              EmployeeModel::$table . '.name',
              self::$table . '.create_time'
            ],
            $where);
    }

}
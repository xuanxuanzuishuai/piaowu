<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 18/7/19
 * Time: 下午3:36
 */

namespace App\Models;


use App\Libs\MysqlDB;

class DeptDataModel extends Model
{

    public static $table = "dept_data";
    public static $redisExpire = 0;
    public static $redisDB;

    const DATA_TYPE_DEFAULT = 0;

    public static $types = [
        self::DATA_TYPE_DEFAULT => "default",
    ];

    const SUPER_PRIVILEGE = -1;  //拥有超级权限

    const DATA_GROUP_ID = "id";             //数据查询分组字段 user.id
    const DATA_GROUP_DEPT_ID = "dept_id";   //数据查询分组字段 dept_id

    /**
     * 保存部门数据权限信息
     * @param $insert
     * @return mixed
     */
    public static function insertDeptData($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    /**
     * 更新部门数据权限信息
     * @param $id
     * @param $update
     * @return mixed
     */
    public static function updateDeptData($id, $update)
    {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }

    /**
     * 获取部门数据权限信息
     * @param $deptId
     * @return mixed
     */
    public static function getDeptData($deptId)
    {
        return MysqlDB::getDB()->select(DeptDataModel::$table, '*', ['dept_id' => $deptId]);
    }

    /**
     * 根据部门ID与数据类型查询拥有权限部门
     * @param $deptId
     * @param $dataType
     * @return array
     */
    public static function getDeptDataWithType($deptId, $dataType)
    {
        return MysqlDB::getDB()->select(self::$table, '*', [
            'AND' => [
                'dept_id' => $deptId,
                'data_type' => $dataType
            ]
        ]);
    }
}
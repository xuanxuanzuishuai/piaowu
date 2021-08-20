<?php
/**
 * Created by PhpStorm.
 * User: ypeng
 * Date: 2021/8/13
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;


class WhiteGrantRecordModel extends Model
{
    public static $table = "white_grant_record";

    const STATUS_GIVE = 1; // 发放成功待领取
    const STATUS_GIVE_FAIL = 2; // 发放失败
    const STATUS_GIVE_NOT_GRANT = 3; //不予发放
    const STATUS_GIVE_NOT_SUCC  = 4; //发放成功用户领取成功


    //发放步骤
    const GRANT_STEP_0 = 0; //成功
    const GRANT_STEP_1 = 1; //用户不存在
    const GRANT_STEP_2 = 2; //金叶子发放失败
    const GRANT_STEP_3 = 3; //扣减金叶子失败
    const GRANT_STEP_4 = 4; //未绑定公众号
    const GRANT_STEP_5 = 5; //微信发红包失败

    const ENVIRONMENT_NOE_EXISTS = 'NOT_ENV_SATISFY';  // 环境不对

    public static function getList($params, $page, $pageSize){
        $dssStudent = DssStudentModel::getTableNameWithDb();
        $dssEmployee = DssEmployeeModel::getTableNameWithDb();

        $where = ' 1=1 ';
        if(!empty($params['uuid'])){
            $where  .= "AND g.uuid = '{$params['uuid']}'";
        }

        if(!empty($params['status'])){
            $where  .= ' AND g.status = ' . $params['status'];
        }

        if(!empty($params['id'])){
            $where  .= ' AND g.id = ' . $params['id'];
        }

        $leftStudent = '';
        $leftEmployee = '';
        if(!empty($params['mobile'])){
            $where  .= ' AND s.mobile = ' . "'{$params['mobile']}'";
            $leftStudent = ' LEFT JOIN ' . $dssStudent . ' as s' . ' ON g.uuid = s.uuid ';
        }

        if(!empty($params['course_manage_id'])){
            $leftStudent = ' LEFT JOIN ' . $dssStudent . ' as s' . ' ON g.uuid = s.uuid ';
            $where .= ' AND s.course_manage_id in ('. implode(',', $params['course_manage_id']) . ')';
            $leftEmployee = ' LEFT JOIN ' . $dssEmployee . ' as e on s.course_manage_id=e.id ';
        }

        $db = MysqlDB::getDB();

        $countSql = "SELECT count(1) as count FROM " . self::$table . ' as g  ' . $leftStudent . $leftEmployee .
            '  WHERE' . $where;

        $count = $db->queryAll($countSql);

        $limit = ' LIMIT ' . ($page - 1) * $pageSize .',' . $pageSize;

        $sql = 'SELECT g.*,e.name as course_manage_name FROM ' . self::$table .
            ' as g  LEFT JOIN ' . $dssStudent . ' as s' . ' ON g.uuid = s.uuid' .
            ' LEFT JOIN ' . $dssEmployee . ' as e on s.course_manage_id=e.id WHERE ' .
            $where . ' ORDER BY id DESC ' . $limit;

        $list = $db->queryAll($sql);

        return [$list, $count[0]['count']];

    }
}

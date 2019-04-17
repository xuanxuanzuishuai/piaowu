<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ScheduleTaskModel extends Model
{
    public static $table = "schedule_task";
    public static $redisExpire = 0;
    public static $redisDB;

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getSTList($params, $page = -1, $count = 20)
    {
        $db = MysqlDB::getDB();
        $where = [];
        if (!empty($params['classroom_id'])) {
            $where['classroom_id'] = $params['classroom_id'];
        }
        if (!empty($params['course_id'])) {
            $where['course_id'] = $params['course_id'];
        }
        if (isset($params['status'])) {
            $where['status'] = $params['status'];
        }
        // 获取总数
        $totalCount = $db->count(self::$table, "*", $where);
        // 分页设置
        $where['LIMIT'] = [($page - 1) * $count, $count];
        // 排序设置
        $where['ORDER'] = [
            'classroom_id' => 'ASC',
            'create_time' => 'DESC'
        ];
        $result = $db->select(self::$table, '*', $where);
        return array($totalCount, $result);
    }

    /**
     * @param $id
     * @return array
     */
    public static function getSTDetail($id) {
        return MysqlDB::getDB()->select(self::$table,'*',['id'=>$id]);
    }

    /**
     * @param $insert
     * @return int|mixed|string|null
     */
    public static function addST($insert) {
        return MysqlDB::getDB()->insertGetID(self::$table,$insert);
    }

    /**
     * @param $id
     * @param $update
     * @return bool
     */
    public static function modifyST($id,$update) {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;


use App\Libs\MysqlDB;

class HomeworkModel extends Model
{
    public static $table = "homework";


    /** 创建
     * @param $schedule_id
     * @param $org_id
     * @param $teacher_id
     * @param $student_id
     * @param $created_time
     * @param $end_time
     * @param $remark
     * @return int|mixed|null|string
     */
    public static function createHomework($schedule_id, $org_id, $teacher_id, $student_id, $created_time, $end_time, $remark)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, [
            'schedule_id' => $schedule_id,
            'org_id' => $org_id,
            'created_time' => $created_time,
            'teacher_id' => $teacher_id,
            'student_id' => $student_id,
            'end_time' => $end_time,
            'remark' => $remark
        ]);
    }

    /** 获取老师最近的作业的book_id数组
     * @param int $teacher_id
     * @param int $page 从1开始
     * @param int $limit
     * @param bool $isOrg
     * @return array
     */
    public static function getRecentBookIds($teacher_id, $page, $limit,$isOrg = true)
    {
        $where = "";
        if($isOrg == true) {
            global $orgId;
            if($orgId > 0 )
                $where = " and org_id = ".$orgId;
        }
        $start = ($page - 1) * $limit;
        $query = "select distinct book_id from " . HomeworkModel::$table .
            " where teacher_id=" . $teacher_id . $where ." order by create_time desc limit " . $start . ", " . $limit;
        $bookIdList = MysqlDB::getDB()->queryAll($query);
        return $bookIdList;
    }

    /** 获取老师最近的作业的opern_id数组
     * @param int $teacher_id
     * @param int $page
     * @param int $limit
     * @param bool $isOrg
     * @return array
     */
    public static function getRecentOpernIds($teacher_id, $page, $limit,$isOrg = true)
    {
        $where = "";
        if($isOrg == true) {
            global $orgId;
            if($orgId > 0 )
                $where = " and org_id = ".$orgId;
        }
        $start = ($page - 1) * $limit;
        $query = "select distinct opern_id from " . HomeworkModel::$table .
            " where teacher_id=" . $teacher_id . $where. " order by create_time desc limit " . $start . ", " . $limit;
        $opernIdList = MysqlDB::getDB()->queryAll($query);
        return $opernIdList;
    }

    /**
     * 查询作业
     * @param array $where
     * @return array
     */
    public static function getHomeworkList($where=[]){
        $result = MysqlDB::getDB()->select(
                HomeworkModel::$table,
                [
                    '[>]'.HomeworkTaskModel::$table => ['id' => 'homework_id']
                ],
                [
                    HomeworkModel::$table.'.id',
                    HomeworkModel::$table.'.student_id',
                    HomeworkModel::$table.'.teacher_id',
                    HomeworkModel::$table.'.org_id',
                    HomeworkModel::$table.'.schedule_id',
                    HomeworkModel::$table.'.created_time',
                    HomeworkModel::$table.'.end_time',
                    HomeworkModel::$table.'.remark',
                    HomeworkTaskModel::$table.'.id(task_id)',
                    HomeworkTaskModel::$table.'.lesson_id',
                    HomeworkTaskModel::$table.'.lesson_name',
                    HomeworkTaskModel::$table.'.collection_id',
                    HomeworkTaskModel::$table.'.collection_name',
                    HomeworkTaskModel::$table.'.baseline',
                    HomeworkTaskModel::$table.'.is_complete(complete)'
                ],
                $where
            );
        return $result;
    }
}

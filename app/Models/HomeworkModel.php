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


    /**
     * @param $opern_id
     * @param $teacher_id
     * @param $book_id
     * @param $student_id
     * @param $create_time
     * @param $stop_time
     * @param $content
     * @return int|mixed|null|string
     */
    public static function createHomework($opern_id, $book_id, $teacher_id, $student_id, $create_time, $stop_time, $content)
    {
        return self::insertRecord([
            'opern_id' => $opern_id,
            'book_id' => $book_id,
            'create_time' => $create_time,
            'teacher_id' => $teacher_id,
            'student_id' => $student_id,
            'stop_time' => $stop_time,
            'content' => $content
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
                    HomeworkModel::$table.'id',
                    HomeworkModel::$table.'student_id',
                    HomeworkModel::$table.'teacher_id',
                    HomeworkModel::$table.'org_id',
                    HomeworkModel::$table.'schedule_id',
                    HomeworkModel::$table.'created_time',
                    HomeworkModel::$table.'end_time',
                    HomeworkModel::$table.'remark',
                    HomeworkTaskModel::$table.'id(task_id)',
                    HomeworkTaskModel::$table.'lesson_id',
                    HomeworkTaskModel::$table.'lesson_name',
                    HomeworkTaskModel::$table.'collection_id',
                    HomeworkTaskModel::$table.'collection_name',
                    HomeworkTaskModel::$table.'baseline',
                    HomeworkTaskModel::$table.'is_complete(complete)'
                ],
                $where
            );
        return $result;
    }
}

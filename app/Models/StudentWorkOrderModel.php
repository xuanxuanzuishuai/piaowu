<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:33 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;


class StudentWorkOrderModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    public static $table = "student_work_order";

    public static function getTotalNum($where)
    {
        return self::getCount($where);
    }

    /**
     * @param $swoId
     * @param array $files
     * @return mixed
     * 根据Id获取单条记录信息
     */
    public static function getSwoById($swoId,$files=[])
    {
        return self::getRecord(['id' => $swoId],$files);
    }

    /**
     * @param $swoId
     * @param array $files
     * @return int|null
     * 根据Id更新单条记录信息
     */
    public static function UpdateSwoById($swoId,$files=[])
    {
        return self::updateRecord(['id' => $swoId],$files);
    }

    /**
     * @param $data
     * @param $ids
     * @return int|null
     * 根据Id批量更新记录信息
     */
    public static function UpdateSwoByIds($data,$ids)
    {
        $where = [
            'id'=>$ids
        ];
        return self::batchUpdateRecord($data,$where,$isOrg = false);
    }


    /**
     * @param $where
     * @param array $files
     * @return array
     * 根据学员ID获取所有历史工单信息
     */
    public static function getSwoListByStudentId($where,$files=[])
    {
        return self::getRecords($where,$files);
    }

    public static function getSwoDetailList($params)
    {
        $studentWorkOrder = self::$table;
        $employee = EmployeeModel::$table;
        $student = StudentModel::$table;

        //定义sql语句
        $table = " FROM {$studentWorkOrder} AS `swo` ";

        $select  = " SELECT `swo`.`id` AS swo_id,
                       `swo`.`student_opera_name`,
                       `swo`.`opera_num`,
                       `swo`.`status`,
                       `swo`.`create_time` as apply_time,
                       `swo`.`refuse_msg`,
                       `swo`.`creator_name`,
                       `s`.`id` as student_id,
                       `s`.`name` as student_name,
                       `s`.`mobile` as student_mobile,
                       `s`.`has_review_course`,
                       `ass`.`name` as assistant_name,
                       `man`.`name` as course_manage_name,
                       `mak`.`name` as opera_maker_name,
                       `con`.`name`as opera_config_name,
                       concat(`swo`.`textbook_name`,'/',`swo`.`opera_name`) as opera_lib";
        $joinStudent = "JOIN {$student} AS `s` ON `swo`.`student_id` = `s`.`id` AND s.status = 1";
        $joinAssistant = " LEFT JOIN {$employee} AS `ass` ON `swo`.`assistant_id` = `ass`.`id` AND ass.status = 1";
        $joinManager = " LEFT JOIN {$employee} AS `man` ON `swo`.`course_manage_id` = `man`.`id` AND man.status = 1";
        $joinMaker = " LEFT JOIN {$employee} AS `mak` ON `swo`.`opera_maker_id` = `mak`.`id` AND mak.status = 1";
        $joinConfig = " LEFT JOIN {$employee} AS `con` ON `swo`.`opera_config_id` = `con`.`id` AND con.status = 1";

        $where = ' WHERE 1 ';
        //根据学生姓名进行搜索
        if(!empty($params['student_name'])){
            $joinStudent .= " AND `s`.`name` like '%{$params['student_name']}%'";
        }

        //根据学生手机号进行搜索
        if(!empty($params['student_mobile'])){
            $joinStudent .= " AND `s`.`mobile` = {$params['student_mobile']}";
        }

        //根据曲谱名进行搜索
        if(!empty($params['student_opera_name'])){
            $where .= " AND `swo`.`student_opera_name` like '%{$params['student_opera_name']}%'";
        }

        //根据工单状态进行搜索
        if(!empty($params['status'])){
            $where .= " AND `swo`.`status` = {$params['status']}";
        }

        //根据助教进行搜索
        if(!empty($params['assistant_id'])){
            $joinAssistant .= " AND `ass`.`id` = {$params['assistant_id']}";
            $where .= " AND `swo`.`assistant_id` = {$params['assistant_id']}";
        }

        //根据课管进行搜索
        if(!empty($params['course_manage_id'])){
            $joinManager .= " AND `man`.`id` = {$params['course_manage_id']}";
            $where .= " AND `swo`.`course_manage_id` = {$params['course_manage_id']}";
        }

        //根据曲谱制作人进行搜索
        if(!empty($params['opera_maker_id'])){
            $joinMaker .= " AND `mak`.`id` = {$params['opera_maker_id']}";
            $where .= " AND `swo`.`opera_maker_id` = {$params['opera_maker_id']}";
        }

        //根据曲谱配置人进行搜索
        if(!empty($params['opera_config_id'])){
            $joinConfig .= " AND `con`.`id` = {$params['opera_config_id']}";
            $where .= " AND `swo`.`opera_config_id` = {$params['opera_config_id']}";
        }

        //根据提交人人进行搜索
        if(!empty($params['creator_name'])){
            $where .= " AND `swo`.`creator_name` like '%{$params['creator_name']}%'";
        }

        //根据工单ID进行搜索
        if(!empty($params['id'])){
            $where .= " AND `swo`.`id`  = {$params['id']}";
        }

        //根据提交时间进行搜索
        if(!empty($params['start_time'])){
            $where .= " AND `swo`.`create_time` >= '{$params['start_time']}'";
        }
        if(!empty($params['end_time'])){
            $where .= " AND `swo`.`create_time` <= '{$params['end_time']}'";
        }

        $order = " ORDER BY `swo`.`create_time` {$params['apply_time_sort']}";
        $offset = ($params['page']-1)*$params['limit'];
        $limit = " LIMIT {$offset},{$params['limit']}";
        $sql = $select . $table . $joinStudent . $joinAssistant . $joinManager. $joinMaker . $joinConfig . $where . $order . $limit;
        $totalSql = "SELECT count(1) as count". $table . $joinStudent . $joinAssistant . $joinManager. $joinMaker . $joinConfig . $where;
        $list = MysqlDB::getDB()->queryAll($sql)??[];
        $totalNum = MysqlDB::getDB()->queryAll($totalSql)[0]['count']??0;
        return [$list,$totalNum];
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 5:36 PM
 */

namespace App\Models;

class StudentRelationModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    public static $table = 'student_relation';

    /**
     * 获取学生关联关系
     * @param $studentId
     * @return array
     */
    public static function getRecordWithStudentId($studentId)
    {
        return self::getRecords(['student_id'=>$studentId, 'status'=>self::STATUS_NORMAL]);
    }

    /**
     * 获取一组学生的管理关系
     * @param $studentIdArray
     * @return array
     */
    public static function getRecordWithStudentIdArray($studentIdArray)
    {
        return self::getRecords(['student_id'=>$studentIdArray, 'status'=>self::STATUS_NORMAL]);
    }

    /**
     * 设置学生关联信息为删除状态
     * @param $studentId
     * @return int|null
     */
    public static function delStudentRelation($studentId)
    {
        $count = self::batchUpdateRecord(
            ['status' => StudentRelationModel::STATUS_DEL],
            ['student_id' => $studentId, 'status' => self::STATUS_NORMAL]);
        return $count;
    }

    /**
     * 插入学生关联人数据
     * @param $studentId
     * @param $relations
     * @return int|mixed|null|string
     */
    public static function insertStudentRelations($studentId, $relations)
    {
        $relation_data = self::makeStudentRelationData($studentId, $relations);
        return StudentRelationModel::insertRecord($relation_data);
    }

    /**
     * 生成学生关联人
     * @param $relations
     * @param $studentId
     * @return array
     */
    public static function makeStudentRelationData($studentId, $relations)
    {
        $data = [];
        $t = time();
        foreach($relations as $relation){
            $row = [];
            $row['student_id'] = $studentId;
            $row['tel'] = $relation['mobile'];
            $row['title'] = $relation['title'];
            $row['status'] = self::STATUS_NORMAL;
            $row['create_time'] = $t;
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 根据关联手机查询学员ID
     * @param $mobile
     * @return array
     */
    public static function getStudentIdByMobile($mobile)
    {
        return self::getOneFields(['student_id'], [
            'tel' => $mobile,
            'status' => self::STATUS_NORMAL
        ]);
    }
}
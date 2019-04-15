<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/9
 * Time: 5:40 PM
 */

namespace App\Services;


use App\Libs\Util;
use App\Models\StudentRelationModel;

class StudentRelationService
{
    /**
     * 获取学生关联通讯列表
     * @param $studentId
     * @param $hideMobile
     * @return array
     */
    public static function getRelationList($studentId, $hideMobile = true)
    {
        $student_relations = StudentRelationModel::getRecordWithStudentId($studentId);
        $data = [];
        foreach($student_relations as $relation)
        {
            $row = [];
            $row['relation_id'] = $relation['id'];
            $row['title'] = $relation['title'];
            $row['mobile'] = ($hideMobile ? Util::hideUserMobile($relation['tel']) : $relation['tel']);
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 获取学生关联人数据Map
     * @param $studentIdArray
     * @return array
     */
    public static function getStudentRelationMap($studentIdArray)
    {
        $student_relation = StudentRelationModel::getRecordWithStudentIdArray($studentIdArray);
        $studentRelationsMap = [];
        foreach($student_relation as $item){
            $row = [];
            $row['tel'] = Util::hideUserMobile($item['tel']);
            $row['title'] = $item['title'];
            $studentRelationsMap[$item['student_id']][] = $row;
        }
        return $studentRelationsMap;
    }

    /**
     * @param $studentId
     * @param $relation_data
     */
    public static function addStudentRelations($studentId, $relation_data)
    {
        StudentRelationModel::insertStudentRelations($studentId, $relation_data);
    }

    /**
     * 删除学生关联
     * @param $studentId
     */
    public static function delStudentRelation($studentId)
    {
        StudentRelationModel::delStudentRelation($studentId);
    }
}
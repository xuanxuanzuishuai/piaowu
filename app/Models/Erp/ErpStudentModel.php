<?php
namespace App\Models\Erp;

use App\Libs\Constants;
class ErpStudentModel extends ErpModel
{
    public static $table = 'erp_student';

    /**
     * 根据uuid或者手机号获取学生信息
     * @param $uuidArr
     * @param $mobileArr
     * @param array $fields
     * @return mixed
     */
    public static function getListByUuidAndMobile($uuidArr,$mobileArr, $fields =[])
    {
        if (!empty($uuidArr) && !empty($mobileArr)) {
            $studentWhere = [
                "OR" => [
                    'uuid' => $uuidArr,
                    'mobile' => $mobileArr
                ],
            ];
        }elseif (!empty($uuidArr)) {
            $studentWhere = [
                'uuid' => $uuidArr,
            ];
        }elseif (!empty($mobileArr)) {
            $studentWhere = [
                'mobile' => $mobileArr
            ];
        }else {
            return [];
        }

        $studentList = ErpStudentModel::getRecords($studentWhere, $fields);
        return $studentList;
    }

    /**
     * 获取用户信息
     * @param $studentId
     * @return array|null
     */
    public static function getUserInfo($studentId)
    {
        $table              = self::$table;
        $erpStudentAppModel = ErpStudentAppModel::$table;
        $appId              = Constants::USER_TYPE_STUDENT;

        $sql = "
            SELECT
                {$table} .id,
                {$table} .name,
                {$table} .uuid,
                {$table} .mobile,
                {$table} .thumb,
                {$erpStudentAppModel} .first_pay_time,
                {$erpStudentAppModel} .status
            FROM {$table} 
            INNER JOIN {$erpStudentAppModel}  ON {$table}.id = {$erpStudentAppModel}.student_id
            WHERE
                {$table}.id = {$studentId}
                AND {$erpStudentAppModel}.app_id = {$appId}
        ";
        return self::dbRO()->queryAll($sql);
    }

}
<?php
namespace App\Models\Erp;

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
}
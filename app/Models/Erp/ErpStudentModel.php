<?php

namespace App\Models\Erp;

use App\Libs\Constants;

class ErpStudentModel extends ErpModel
{
    public static $table = 'erp_student';
    //社交账号类型：1邮箱 2微信 3脸书
    const SOCIAL_ACCOUNT_TYPE_EMAIL = 1;
    const SOCIAL_ACCOUNT_TYPE_WX = 2;
    const SOCIAL_ACCOUNT_TYPE_FACEBOOK = 3;

    /**
     * 根据uuid或者手机号获取学生信息
     * @param $uuidArr
     * @param $mobileArr
     * @param array $fields
     * @return mixed
     */
    public static function getListByUuidAndMobile($uuidArr, $mobileArr, $fields = [])
    {
        if (!empty($uuidArr) && !empty($mobileArr)) {
            $studentWhere = [
                "OR" => [
                    'uuid' => $uuidArr,
                    'mobile' => $mobileArr
                ],
            ];
        } elseif (!empty($uuidArr)) {
            $studentWhere = [
                'uuid' => $uuidArr,
            ];
        } elseif (!empty($mobileArr)) {
            $studentWhere = [
                'mobile' => $mobileArr
            ];
        } else {
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
        $table = self::$table;
        $erpStudentAppModel = ErpStudentAppModel::$table;
        $appId = Constants::REAL_APP_ID;

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


    /**
     * 获取学生基础信息
     * @param $studentId
     * @return array
     */
    public static function getStudentInfoById($studentId)
    {
        $db = self::dbRO();
        $info = $db->select(self::$table,
            [
                "[>]" . ErpStudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.uuid',
                self::$table . '.name',
                self::$table . '.mobile',
                self::$table . '.thumb',
                self::$table . '.channel_id',
                self::$table . '.country_code',
                ErpStudentAppModel::$table . '.status',
                ErpStudentAppModel::$table . '.first_pay_time',
                ErpStudentAppModel::$table . '.student_id',
            ],
            [
                self::$table . '.id' => $studentId,
                ErpStudentAppModel::$table . '.app_id' => Constants::REAL_APP_ID,
            ]);
        return empty($info) ? [] : $info[0];
    }

    /**
     * 获取学生真人应用注册信息:通过uuid
     * @param $studentUuid
     * @return array
     */
    public static function getStudentInfoByUuid($studentUuid)
    {
        $db = self::dbRO();
        $info = $db->select(self::$table,
            [
                "[>]" . ErpStudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.uuid',
                self::$table . '.mobile',
                self::$table . '.channel_id',
                ErpStudentAppModel::$table . '.status',
                ErpStudentAppModel::$table . '.first_pay_time',
            ],
            [
                self::$table . '.uuid' => $studentUuid,
                ErpStudentAppModel::$table . '.app_id' => Constants::REAL_APP_ID,
            ]);
        return empty($info) ? [] : $info[0];
    }

    /**
     * 批量获取学生uuid
     * @param $studentUuidArr
     * @return array
     */
    public static function getStudentInfoByUuids($studentUuidArr): array
    {
        $db = self::dbRO();
        $info = $db->select(
            self::$table,
            [
                "[>]" . ErpStudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.uuid',
                self::$table . '.mobile',
            ],
            [
                self::$table . '.uuid' => $studentUuidArr,
                ErpStudentAppModel::$table . '.app_id' => Constants::REAL_APP_ID,
            ]
        );
        return is_array($info) ? $info : [];
    }

    /**
     * 根据手机号获取真人用户信息
     * @param $studentMobile
     * @return mixed
     */
    public static function getRealUserInfoByMobile($studentMobile)
    {
        $db = self::dbRO();
        return $db->get(self::$table,
            [
                "[><]" . ErpStudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                self::$table . '.id(student_id)',
                self::$table . '.mobile',
                self::$table . '.uuid',
            ],
            [
                self::$table . '.mobile' => $studentMobile,
                ErpStudentAppModel::$table . '.app_id' => Constants::REAL_APP_ID,
            ]);
    }

    /**
     * 根据手机号查询用户注册业务线信息
     * @param $mobile
     * @return mixed
     */
    public static function getUserInfoByMobile($mobile)
    {
        $db = self::dbRO();
        $sql = 'select es.mobile, es.uuid, ifnull(esa.app_id, 1) as app_id
from ' . self::$table . ' as es
         left join ' . ErpStudentAppModel::$table . ' as esa on es.id = esa.student_id
where es.mobile = ' . $mobile;
        return $db->queryAll($sql);
    }

    /**
     * 根据手机号获取用户信息
     * @param $studentMobile
     * @param $appId
     * @return mixed
     */
    public static function getUserInfoByMobiles($studentMobile, $appId)
    {
        $db = self::dbRO();
        return $db->get(self::$table,
            [
                "[><]" . ErpStudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                self::$table . '.id(student_id)',
                self::$table . '.mobile',
                self::$table . '.uuid',
            ],
            [
                self::$table . '.mobile' => $studentMobile,
                ErpStudentAppModel::$table . '.app_id' => $appId,
            ]);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 19:58
 */

namespace App\Services;

use App\Models\StudentModel;
use App\Models\StudentRefereeModel;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class StudentRefereeService
{

    /**
     * 记录学生转介绍推荐人关系数据
     * @param $refereeId
     * @param $studentId
     * @param $refereeType
     * @return int|mixed|null|string
     */
    public static function recordStudentRefereeData($refereeId, $studentId, $refereeType)
    {
        $studentReferralData = [
            'referee_id' => $refereeId,
            'student_id' => $studentId,
            'referee_type' => $refereeType,
            'create_time' => time()
        ];
        $studentReferralLastId = StudentRefereeModel::insertRecord($studentReferralData, false);
        if (empty($studentReferralLastId)) {
            SimpleLogger::error(__FILE__ . __LINE__, [
                'msg' => 'record student referral data error',
                'student_id' => $studentId,
                'referrer_id' => $refereeId,
            ]);
        }
        return $studentReferralLastId;
    }

    /**
     * 获取学生推荐人信息
     * @param $studentId
     * @return array
     */
    public static function studentRefereeUserData($studentId)
    {
        $db = MysqlDB::getDB();
        $data = $db->get(
            StudentRefereeModel::$table,
            [
                "[>]" . StudentModel::$table => ["referee_id" => "id"]
            ],
            [
                StudentModel::$table . '.id',
                StudentModel::$table . '.collection_id',
                StudentModel::$table . '.assistant_id',
            ],
            [
                StudentRefereeModel::$table . '.student_id' => $studentId
            ]
        );
        //返回数据
        return $data;
    }
}
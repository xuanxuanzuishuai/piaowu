<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;

class OperationActivityModel extends Model
{
    public static $table = "operation_activity";

    // 启用状态
    const ENABLE_STATUS_OFF = 1;        // 待启用
    const ENABLE_STATUS_ON = 2;         // 启用
    const ENABLE_STATUS_DISABLE = 3;    // 已禁用
    // 启用状态对应的文字
    const ENABLE_STATUS_ZH = [
        self::ENABLE_STATUS_OFF => '待启用',
        self::ENABLE_STATUS_ON => '已启用',
        self::ENABLE_STATUS_DISABLE => '已禁用',
    ];

    // 活动开始状态描述
    const ACTIVITY_STATUS_ZH = [
        'no_start' => '未开始',
        'already_start' => '已开始',
        'already_over' => '已结束'
    ];

    /**
     * 当前阶段为付费正式课且未参加当前活动的学员手机号
     * @param $activityId
     * @return array
     */
    public static function getPaidAndNotAttendStudentsMobile($activityId)
    {
        $students = MysqlDB::getDB()->queryAll("
SELECT
    id, mobile, country_code
FROM
    " . DssStudentModel::$table . "
WHERE
    has_review_course = " . DssStudentModel::REVIEW_COURSE_1980 . "
    AND status = " . DssStudentModel::STATUS_NORMAL . "
    AND id NOT IN (SELECT student_id FROM " . SharePosterModel::$table . " WHERE activity_id = :activity_id)", [':activity_id' => $activityId]);

        return $students ?? [];
    }

    /**
     * 当前阶段为付费正式课且未参加当前活动的学员手微信open_id
     * @param $activityId
     * @return array
     */
    public static function getPaidAndNotAttendStudentsOpenid($activityId)
    {
        // 当前阶段为付费正式课且未参加当前活动的学员
        $boundUsers = MysqlDB::getDB()->queryAll("
SELECT
    uw.user_id, uw.open_id
FROM
    " . DssStudentModel::$table . " s
INNER JOIN
    " . DssUserWeiXinModel::$table . " uw ON uw.user_id = s.id
WHERE
    s.has_review_course = " . DssStudentModel::REVIEW_COURSE_1980 . "
    AND s.status = " . DssStudentModel::STATUS_NORMAL . "
    AND s.id NOT IN (SELECT student_id FROM " . SharePosterModel::$table . " WHERE activity_id = :activity_id)
    AND uw.user_type = " . DssUserWeiXinModel::USER_TYPE_STUDENT . "
    AND uw.status = " . DssUserWeiXinModel::STATUS_NORMAL . "
    AND uw.app_id = " . Constants::SMART_APP_ID, [':activity_id' => $activityId]);

        return $boundUsers ?? [];
    }
}

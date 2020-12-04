<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Models\Dss\DssStudentModel;
use App\Libs\Util;
use App\Models\StudentInviteModel;

class ReferralService
{
    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getReferralList($params)
    {
        list($records, $total) = DssStudentModel::getInviteList($params);
        foreach ($records as &$item) {
            $item = self::formatStudentInvite($item);
        }
        return [$records, $total[0]['total'] ?? 0];
    }

    public static function formatStudentInvite($item)
    {
        $hasReviewCourseSet = DictConstants::getSet(DictConstants::HAS_REVIEW_COURSE);
        $item['student_mobile_hidden']  = Util::hideUserMobile($item['mobile']);
        $item['referrer_mobile_hidden'] = Util::hideUserMobile($item['referral_mobile']);
        $item['has_review_course_show'] = $hasReviewCourseSet[$item['has_review_course']];
        $item['create_time_show']       = date('Y-m-d H:i', $item['create_time']);
        $item['register_time']          = $item['create_time'];
        return $item;
    }

    /**
     * 某个用户的推荐人信息
     * @param $appId
     * @param $studentId
     * @return mixed
     */
    public static function getReferralInfo($appId, $studentId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentInviteModel::getRecord(
                ['student_id' => $studentId, 'app_id' => $appId]
            );
        }
        return NULL;
    }

    /**
     * @param $appId
     * @param $studentId
     * @param $refereeType
     * @return mixed
     * 当前这个人推荐过来的所有用户
     */
    public static function getRefereeAllUser($appId, $studentId, $refereeType)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentInviteModel::getRecords(
                ['referee_id' => $studentId, 'app_id' => $appId, 'referee_type' => $refereeType]
            );
        }
        return NULL;
    }



}

<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssStudentModel;
use App\Services\ActivityService;
use App\Services\StudentService;

/**
 * 智能用户在分享活动中特殊逻辑服务类
 */
class DssService implements BaseInterface
{
    public $studentId = 0;

    public function __construct($studentId)
    {
        $this->studentId = $studentId;
    }

    /**
     *
     */
    public function getActivityList()
    {
        //学生信息
        $userDetail = StudentService::dssStudentStatusCheck($this->studentId, false, null);
        if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
            throw new RunTimeException(['no_in_progress_activity']);
        }
        $userInfo = [
            'nickname'   => $userDetail['student_info']['name'] ?? '',
            'headimgurl' => StudentService::getStudentThumb($userDetail['student_info']['thumb'])
        ];
        //查询活动
        $uuid = $userDetail['student_info']['uuid'] ?? '';
        $activityCountryCode = $userDetail['student_info']['country_code'] ?? '';
        $activityInfo = [];

        $activityInfo = ActivityService::formatData($activityInfo);
        return $activityInfo;
    }

    /**
     * 检测学生付费状态
     * @param $studentId
     * @return void
     * @throws RunTimeException
     */
    public function studentStatusCheck($studentId)
    {
        $userDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
            throw new RunTimeException(['no_in_progress_activity']);
        }
    }


}
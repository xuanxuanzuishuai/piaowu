<?php

namespace App\Services\Activity\LimitTimeActivity;

use App\Libs\Constants;
use App\Services\Activity\LimitTimeActivity\TraitService\DssService;
use App\Services\PosterService;
use App\Services\StudentService;
use App\Services\WeekActivityService;

/**
 * 限时有奖活动客户端功能服务类
 */
class LimitTimeActivityClientService
{
    public static function baseData($userData, $appId)
    {
        $data = [
            'list'                  => [],// 海报列表
            'activity'              => [],// 活动详情
            'student_info'          => [],// 学生详情
            "is_have_activity"      => false,//是否有可参与的活动
            "no_re_activity_reason" => WeekActivityService::ACTIVITY_RETRY_UPLOAD_NO,//是否有补卡资格
        ];
        //海报配置数据
        $posterConfig = PosterService::getPosterConfig();
        //获取活动列表
        $activityData = [];
        switch ($appId) {
            case Constants::SMART_APP_ID:
                $serviceObj = new DssService($userData['user_id']);
                break;
            case Constants::REAL_APP_ID:
                echo 99;
                break;
        }
        if (empty($activityData)) {
            return $data;
        }
        $serviceObj->getActivityList();

    }


}
<?php

use App\Controllers\Client\Activity\LimitTimeActivityController;

//限时有奖活动路由
return [
    '/limit_time_activity/base_data'          => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':baseData'
    ],
    '/limit_time_activity/join_records'       => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':joinRecords'
    ],
    '/limit_time_activity/activity_task_list' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskList'
    ],
    '/limit_time_activity/verify_list' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskVerifyList'
    ],
    '/limit_time_activity/verify_detail' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskVerifyDetail'
    ],
    '/limit_time_activity/award_rule' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':awardRule'
    ],
    '/limit_time_activity/poster_upload' => [
        'method' => ['post'],
        'call'   => LimitTimeActivityController::class . ':posterUpload'
    ],
];
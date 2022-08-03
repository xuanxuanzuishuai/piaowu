<?php

use App\Controllers\Client\Activity\LimitTimeActivityController;

//限时有奖活动路由
return [
    '/base_data'          => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':baseData'
    ],
    '/join_records'       => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':joinRecords'
    ],
    '/activity_task_list' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskList'
    ],
    '/verify_list'        => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskVerifyList'
    ],
    '/verify_detail'      => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityTaskVerifyDetail'
    ],
    '/award_rule'         => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':awardRule'
    ],
    '/poster_upload'      => [
        'method' => ['post'],
        'call'   => LimitTimeActivityController::class . ':posterUpload'
    ],
	'/show_tab'      => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':activityShowTab'
    ],
];
<?php

use App\Controllers\Client\Activity\LimitTimeActivityController;

//限时有奖活动路由
return [
    '/limit_time_activity/base_data' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':baseData'
    ],
    '/limit_time_activity/join_records' => [
        'method' => ['get'],
        'call'   => LimitTimeActivityController::class . ':joinRecords'
    ],
];
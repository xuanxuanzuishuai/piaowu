<?php
/**
 * 周周领奖xyzop1262
 * 周周领奖限时活动-提高分享次数
 */

namespace App\Services;

class XYZOP1262Service extends WeekActivityService
{
    const WEEK_ACTIVITY_ONE   = 1;
    const WEEK_ACTIVITY_TWO   = 2;
    const WEEK_ACTIVITY_THREE = 3;
    const WEEK_ACTIVITY_FOUR  = 4;
    const WEEK_ACTIVITY_NAME  = [
        self::WEEK_ACTIVITY_ONE   => '第1次分享截图上传',
        self::WEEK_ACTIVITY_TWO   => '第2次分享截图上传',
        self::WEEK_ACTIVITY_THREE => '第3次分享截图上传',
        self::WEEK_ACTIVITY_FOUR  => '第4次分享截图上传',
    ];
}

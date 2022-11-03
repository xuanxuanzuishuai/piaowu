<?php
/**
 * 清晨相关配置
 */

namespace App\Libs;


class MorningDictConstants extends DictConstants
{
    // 清晨推送用户群体
    const MORNING_PUSH_USER_GROUP = [
        'type' => 'morning_push_user_group',
        'keys' => [
            '1',    // 全部用户
            '2',    // 清晨体验卡用户
            '3',    // 清晨年卡用户
            '4',    // 清晨体验营打卡返现活动用户
        ]
    ];

    // 清晨转介绍相关配置
    const MORNING_REFERRAL_CONFIG = [
        'type' => 'morning_referral_config',
        'keys' => [
            'PUSH_MSG_USER_SHARE_CHANNEL_ID',
            'WX_BIND_USER_URL',
            'OPEN_ID_NOT_BIND_USER_MSG_ID',
            'not_found_referral_channel',   // 转介绍小程序没有发现推荐人信息时的兜底渠道id
        ]
    ];

    // 清晨学生状态对应的文字
    const MORNING_STUDENT_STATUS = [
        'type' => 'morning_student_status',
        'keys' => [
            Constants::MORNING_STUDENT_STATUS_CANCEL,
            Constants::MORNING_STUDENT_STATUS_REGISTE,
            Constants::MORNING_STUDENT_STATUS_TRAIL,
            Constants::MORNING_STUDENT_STATUS_TRAIL_EXPIRE,
            Constants::MORNING_STUDENT_STATUS_NORMAL,
            Constants::MORNING_STUDENT_STATUS_NORMAL_EXPIRE,
        ]
    ];

    // 清晨不同用户状态对应的不同产品包
    const MORNING_STUDENT_STATUS_PACKAGE = [
        'type' => 'morning_student_status_package',
        'keys' => [
            Constants::MORNING_STUDENT_STATUS_NORMAL,        // 年卡状态对应的产品
            Constants::MORNING_STUDENT_STATUS_TRAIL_EXPIRE,  // 非年卡
        ]
    ];


    // 5日打卡活动
    const MORNING_FIVE_DAY_ACTIVITY = [
        'type' => 'morning_five_day_activity',
        'keys' => [
            '5day_poster_w_h',
            '5day_water_poster_thumb',
            '5day_water_poster_nickname',
            '5day_water_poster_lesson_name',
            '5day_water_poster_knowledge',
            '5day_water_poster_intonation',
            '5day_water_poster_rhythm',
            '5day_water_poster_qr',
            '5day_water_poster_channel',
            '5day_award_node',
            '5day_clock_in_node',
            '5day_collection_start_time',
        ]
    ];
}

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
        ]
    ];

    // 清晨转介绍相关配置
    const MORNING_REFERRAL_CONFIG = [
        'type' => 'morning_referral_config',
        'keys' => [
            'PUSH_MSG_USER_SHARE_CHANNEL_ID',
            'WX_BIND_USER_URL',
            'OPEN_ID_NOT_BIND_USER_MSG_ID'
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
            Constants::MORNING_STUDENT_STATUS_NORMAL,
            Constants::MORNING_STUDENT_STATUS_NORMAL_EXPIRE,
        ]
    ];
}

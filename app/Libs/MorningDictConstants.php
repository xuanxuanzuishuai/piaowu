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
}

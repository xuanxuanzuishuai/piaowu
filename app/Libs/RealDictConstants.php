<?php
/**
 * 真人业务线相关配置
 */

namespace App\Libs;

class RealDictConstants extends DictConstants
{
    // 真人 - 周周领奖上传分享截图奖励规则
    const REAL_SHARE_POSTER_AWARD_RULE = [
        'type' => 'real_share_poster_award_rule',
        'keys' => [
            '0',
            '1',
            '2',
            '-1',
        ]
    ];
    
    // 真人转介绍相关配置
    const REAL_REFERRAL_CONFIG = [
        'type' => 'REAL_REFERRAL_CONFIG',
        'keys' => [
            'real_refused_poster_url', //海报审核未通过跳转地址
            'real_share_poster_history_url', // 用户分享海报历史记录连接
            'real_month_award_url', // 月月有奖地址
            'register_default_channel', //注册默认渠道
            'employee_replace_student_create_poster_activity_id', // 员工替学生生成转介绍分享海报活动id
        ]
    ];

    // 分享截图审核通过的消息id
    const REAL_SHARE_POSTER_CONFIG = [
        'type' => 'REAL_SHARE_POSTER_CONFIG',
        'keys' => [
            '2',  // 审核状态 - 审核通过消息id
            '3',  // 审核状态 - 审核未通过消息id
        ]
    ];

    // 真人二次分享页面跑马灯数据
    const REAL_TWO_SHARE_POSTER_TOP_CONFIG = [
        'type' => 'REAL_TWO_SHARE_POSTER_TOP_CONFIG',
        'keys' => [
            'mobile_invitee_num', // 手机号对应邀请人数
            'magic_stone',
        ],
    ];

    // 真人新签用户支持多次分享活动配置
    const REAL_XYZOP_1321_CONFIG =[
        'type' => 'REAL_XYZOP_1321_CONFIG',
        'keys' => [
            'real_xyzop_1321_first_pay_time_start',   // 用户首次付费开始时间
            'real_xyzop_1321_first_pay_time_end',     // 用户首次付费结束时间
            'real_xyzop_1321_activity_ids',           // 特定活动ids
            'real_xyzop_1321_normal_activity_ids',    // 正常的活动ids(当前正在启用的活动id再这个里面的不校验活动id)
            'real_xyzop_1321_msg_id', // 消息id
            'real_xyzop_1321_msg_url', // 消息跳转链接
        ],
    ];
}

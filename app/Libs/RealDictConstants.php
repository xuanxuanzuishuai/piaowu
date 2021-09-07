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
}

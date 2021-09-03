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
        ]
    ];
}

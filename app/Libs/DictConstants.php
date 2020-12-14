<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/5
 * Time: 12:53 PM
 */

namespace App\Libs;

use App\Services\DictService;

class DictConstants {

    // 员工专项活动设置
    const EMPLOYEE_ACTIVITY_ENV = [
        'type' => 'EMPLOYEE_ACTIVITY_ENV',
        'keys' => [
            'invite_channel',
            'employee_activity_landing_url'
        ],
    ];

    // 阿里云config
    const ALI_OSS_CONFIG = [
        'type' => 'ALI_OSS_CONFIG',
        'keys' => [
            'access_key_id',
            'access_key_secret',
            'bucket',
            'endpoint',
            'host',
            'callback_url',
            'expire',
            'max_file_size',
            'region_id',
            'record_file_arn',
            'img_dir',
            'cdn_domain',
            'img_size_h',
            'dss_cdn_domain'
        ]
    ];

    // 用户中心
    const USER_CENTER = [
        'type' => 'USER_CENTER_CONFIG',
        'keys' => [
            'host',
            'app_id_op',
            'app_secret_op'
        ]
    ];

    const SERVICE = [
        'type' => 'SERVICE_CONFIG',
        'keys' => [
            'sms_host',
            'opern_host',
            'new_opern_host',
            'ai_backend_host',
            'uc_app_host',
            'panda_crm_host',
            'erp_host',
            'ai_class_host',
            'voice_appId',
            'voice_appKey',
            'voice_url',
            'voice_templateId',
            'ai_class_http_host',
            'dss_host'
        ]
    ];

    const VOICE_SMS_CONFIG = [
        'type' => 'VOICE_SMS_CONFIG',
        'keys' => [
            'voice_app_id',
            'voice_app_key',
            'voice_host',
            'voice_purchase_experience_class_template_id'
        ]
    ];

    // 后台配置
    const ORG_WEB_CONFIG = [
        'type' => 'ORG_WEB_CONFIG',
        'keys' => [
            'assistant_role',
            'course_manage_role',
            'maker_role',
            'config_role'
        ]
    ];

    const APP_CONFIG_COMMON = [
        'type' => 'APP_CONFIG_COMMON',
        'keys' => [
            'ai_host',
            'new_ai_host',
            'review_mobile',
            'review_validate_code',
            'super_validate_code',
            'res_test_mobiles',
        ]
    ];

    // ip白名单
    const IP_WHITE_LIST = [
        'type' => 'IP_WHITE_LIST',
        'keys' => [
            'erp',
            'org_web',
            'crm',
            'sales_master',
        ]
    ];

    //个性化海报/标准海报配置
    const TEMPLATE_POSTER_CONFIG = [
        'type' => 'template_poster_config',
        'keys' => [
            1,
            2,
            'QR_X',
            'QR_Y',
            'QR_WIDTH',
            'QR_HEIGHT',
            'POSTER_WIDTH',
            'POSTER_HEIGHT',
        ]
    ];

    const DEPT_DATA_TYPE_NAME = [
        'type' => 'DEPT_DATA_TYPE_NAME',
        'keys' => [1]
    ];

    const DEPT_PRIVILEGE_TYPE_NAME = [
        'type' => 'DEPT_PRIVILEGE_TYPE_NAME',
        'keys' => [0, 1, 2, 3, 4]
    ];

    //go的钉钉接口
    const DING_DING_CONFIG = [
        'type' => 'ding_ding',
        'keys' => [
            'host',
            'tem_no',
            'flow_url'
        ]
    ];
    //钉钉审批状态
    const DING_DING_STATUS = [
        'type' => 'ding_apply_status',
        'keys' => [1,2,3,4,5]
    ];

    const HAS_REVIEW_COURSE = [
        'type' => 'HAS_REVIEW_COURSE',
        'keys' => [0, 1, 2, 3]
    ];

    //通用红包搜索节点
    const COMMON_CASH_NODE = [
        'type' => 'operation_common_node',
        'keys' => [
            '4','5','6','7','8','9','10','11','12','13'
        ]
    ];
    //补发红包搜索节点
    const REISSUE_CASH_NODE = [
        'type' => 'operation_reissue_node',
        'keys' => [
            '6','7','8','9','10','11','12','13'
        ]
    ];
    //转介绍红包搜索节点
    const REFEREE_CASH_NODE = [
        'type' => 'operation_referee_node',
        'keys' => [
            '1','2','3'
        ]
    ];
    //节点特殊配置
    const NODE_SETTING = [
        'type' => 'node_setting',
        'keys' => [
            'not_display_wait',
            'not_verify_refund'
        ]
    ];
    //节点与task的对应关系
    const NODE_RELATE_TASK = [
        'type' => 'node_relate_task',
        'keys' => [
            '1','2','3','4','5','6','7','8','9','10','11','12','13'
        ]
    ];
    //不同应用的微信app_id
    const WECHAT_APPID = [
        'type' => 'wechat_app_id',
        'keys' => [
            '8_1', '8_8'
        ]
    ];
    //不同应用的微信app_secret
    const WECHAT_APP_SECRET = [
        'type' => 'wechat_app_secret',
        'keys' => [
            '8_1', '8_8'
        ]
    ];
    //不同应用的微信商户号
    const WECHAT_MCHID = [
        'type' => 'wechat_mchid',
        'keys' => [
            '8_1'
        ]
    ];
    //不同应用的cert的pem路径
    const WECHAT_API_CERT_PEM = [
        'type' => 'wechat_api_cert_pem',
        'keys' => [
            '8_1'
        ]
    ];
    //不同应用的key的pem路径
    const WECHAT_API_KEY_PEM = [
        'type' => 'wechat_api_key_pem',
        'keys' => [
            '8_1'
        ]
    ];
    //红包祝福语设置
    const WE_CHAT_RED_PACK_CONFIG = [
        'type' => 'WE_CHAT_RED_PACK',
        'keys' => ['ACT_NAME', 'SEND_NAME', 'WISHING', 'NORMAL_PIC_WORD', 'COMMUNITY_PIC_WORD', 'TERM_SPRINT_PIC_WORD', 'REFERRER_PIC_WORD', 'REISSUE_PIC_WORD'],
    ];
    public static function get($type, $key)
    {
        if (empty($type) || empty($key)) {
            return null;
        }

        if (is_array($key)) {
            return self::getValues($type, $key);
        }

        if (!in_array($key, $type['keys'])) {
            SimpleLogger::error(__FILE__ . __LINE__ . ' DictConstants::get [invalid key]', [
                'type' => $type,
                'key' => $key
            ]);
            return null;
        }

        return DictService::getKeyValue($type['type'], $key);
    }

    public static function getValues($type, $keys)
    {
        if (empty($type)) {
            return [];
        }

        if (empty($keys)) {
            return [];
        }

        // 如果给的$keys中有不在$type['keys']里的直接返回空
        if (!empty(array_diff($keys, $type['keys']))) {
            return [];
        }

        return DictService::getKeyValuesByArray($type['type'], $keys);
    }

    public static function getSet($type)
    {
        return DictService::getTypeMap($type['type']);
    }
}
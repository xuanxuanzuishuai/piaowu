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

    //邀请学生渠道配置
    const STUDENT_INVITE_CHANNEL = [
        'type' => 'STUDENT_INVITE',
        'keys' => [
            'NORMAL_STUDENT_INVITE_STUDENT',
            'BUY_NORMAL_STUDENT_INVITE_STUDENT',
            'BUY_TRAIL_STUDENT_INVITE_STUDENT',
            'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT',
            'POSTER_LANDING_49_STUDENT_INVITE_STUDENT',
            'APP_CAPSULE_INVITE_CHANNEL'
        ]
    ];

    //消息队列相关配置
    const QUEUE_CONFIG = [
        'type' => 'queue_config',
        'keys' => [
            'NSQ_LOOKUPS', 'NSQ_TOPIC_PREFIX'
        ]
    ];

    //默认头像
    const STUDENT_DEFAULT_INFO = [
        'type' => 'student_info',
        'keys' => [
            'default_thumb'
        ]
    ];
    
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
            'dss_host',
            'tpns_host',
            'access_id_android',
            'secret_key_android',
            'access_id_ios',
            'secret_key_ios',
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
            'config_role',
            'push_user_template',
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
            '20', '21', '22'
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
            '1','2','3','4','5','6','7','8','9','10','11','12','13',
            '18','19','20','21','22'
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
        'keys' => ['NORMAL_PIC_WORD', 'COMMUNITY_PIC_WORD', 'REFERRER_PIC_WORD', 'REISSUE_PIC_WORD'],
    ];

    //消息推送规则
    const MESSAGE_RULE = [
        'type' => 'message_rule_config',
        'keys' => [
            'receive_red_pack_rule_id',
            'trail_user_c_rule_id',
            'assign_template_id',
            'year_pay_rule_id',
            'subscribe_rule_id',
            'only_trail_rule_id',
            'only_year_rule_id',
            'year_user_c_rule_id',
            'register_user_c_rule_id',
            'how_long_not_invite',
            'how_long_not_result',
            'start_class_day_rule_id',
            'start_class_seven_day_rule_id',
            'before_class_one_day_rule_id',
            'before_class_two_day_rule_id',
            'after_class_one_day_rule_id',
            'monthly_event_rule_id',
            'no_play_day_rule_config', // 未练琴推送规则ID配置
        ]
    ];

    // 体验营打卡签到设置
    const CHECKIN_PUSH_CONFIG = [
        'type' => 'CHECKIN_PUSH_CONFIG',
        'keys' => [
            'day_0',
            'day_1',
            'day_2',
            'day_3',
            'day_4',
            'day_5',
            'text_position',
            'poster_config',
            'verify_message_config_id',
            'url',
            'page_url',
            'collection_event_id',
            'max_name_length',
            'day_channel',
            'day_poster_config',
        ],
    ];
    // 转介绍相关配置
    const REFERRAL_CONFIG = [
        'type' => 'REFERRAL_CONFIG',
        'keys' => [
            'new_rule_start_time', // 新规则启用时间
        ]
    ];

    //推送用户类型
    const PUSH_USER_TYPE = [
        'type' => 'push_user_type',
        'keys' => [
            '1',    //全量用户推动
            '2',    //指定用户推送
        ]
    ];

    //推送类型
    const PUSH_TYPE = [
        'type' => 'push_type',
        'keys' => [
            '1',    //首页
            '2',    //webview链接
            '3',    //浏览器链接
            '4',    //小程序
            '5',    //音符商城-商品详情页
            '6',    //练琴日历
            '7',    //套课详情页
        ]
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
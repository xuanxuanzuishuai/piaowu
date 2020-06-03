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
        ]
    ];

    // 用户中心
    const USER_CENTER = [
        'type' => 'USER_CENTER_CONFIG',
        'keys' => [
            'host',
            'app_id_dss',
            'app_secret_dss',
            'app_id_student',
            'app_secret_student',
            'app_id_teacher',
            'app_secret_teacher',
        ]
    ];

    const SERVICE = [
        'type' => 'SERVICE_CONFIG',
        'keys' => [
            'sms_host',
            'opern_host',
            'ai_backend_host',
            'uc_app_host',
            'panda_crm_host',
            'erp_host',
            'ai_class_host',
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

    const APP_CONFIG_STUDENT = [
        'type' => 'APP_CONFIG_STUDENT',
        'keys' => [
            'guide_url',
            'review_guide_url',
            'policy_url',
            'sub_info_count',
            'tmall_2680',
            'tmall_599',
            'free_package',
            'pay_test_students',
            'success_url',
            'cancel_url',
            'result_url',
            'pay_url',
            'share_url',
            'share',
            'trial_duration',
            'ai_adjust_db',
            'device_check',
            'exam_enable',
            'exam_url',
            'tts_url',
            'exam_category_ids'
        ]
    ];

    const WEB_STUDENT_CONFIG = [
        'type' => 'WEB_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
            'package_id',
            'plus_package_id',
            'mini_package_id',
        ]
    ];

    const WEIXIN_STUDENT_CONFIG = [
        'type' => 'WEIXIN_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url'
        ]
    ];

    //课包配置
    const PACKAGE_CONFIG = [
        'type' => 'PACKAGE_CONFIG',
        'keys' => [
            'package_id',
            'plus_package_id',
        ]
    ];

    const APP_CONFIG_TEACHER = [
        'type' => 'APP_CONFIG_TEACHER',
        'keys' => [
            'default_collections',
            'trial_lessons',
            'policy_url',
            'course_id',
        ]
    ];

    // ip白名单
    const IP_WHITE_LIST = [
        'type' => 'IP_WHITE_LIST',
        'keys' => [
            'erp',
            'org_web',
        ]
    ];

    // 特殊机构对应id
    const SPECIAL_ORG_ID = [
        'type' => 'SPECIAL_ORG_ID',
        'keys' => [
            'internal', // 内部 0
            'direct', // 直营 1
            'panda', // 线上熊猫
            'ec', // 电商
            'classroom', // 老教室
        ]
    ];

    // 用户标签
    const FLAG_ID = [
        'type' => 'FLAG_ID',
        'keys' => [
            'new_score', // 新曲谱测试
            'res_free', // 资源免费
            'app_review', // AppStore审核
        ]
    ];

    //题目状态
    const QUESTION_STATUS = [
        'type' => 'question_status',
        'keys' => [1, 2, 3]
    ];

    //题目类型
    const QUESTION_TEMPLATE = [
        'type' => 'question_template',
        'keys' => [1, 2, 3, 4]
    ];

    const EXAM_BANNER = [
        'type' => 'exam_banner',
        'keys' => [1, 2],
    ];

    const EXAM_POP = [
        'type' => 'exam_pop',
        'keys' => [1],
    ];

    const ACCOUNT_TYPE = [
        'type' => 'account_type',
        'keys' => [1, 2],
    ];

    const LICENSE_TYPE = [
        'type' => 'license_type',
        'keys' => [1, 2, 3],
    ];

    const CLASSROOM_APP_CONFIG = [
        'type' => 'classroom_app_config',
        'keys' => ['used_offline']
    ];

    const LANDING_CONFIG = [
        'type' => 'landing_config',
        'keys' => ['channel_weixin', 'user_action_set_id'],
    ];

    const REVIEW_COURSE_CONFIG = [
        'type' => 'REVIEW_COURSE_CONFIG',
        'keys' => ['reviewer_ids'],
    ];

    const ACTIVITY_CONFIG = [
        'type' => 'ACTIVITY_CONFIG',
        'keys' => ['gift_course_id'],
    ];

    const WE_CHAT_RED_PACK_CONFIG = [
        'type' => 'WE_CHAT_RED_PACK',
        'keys' => ['ACT_NAME', 'SEND_NAME', 'WISHING', 'NORMAL_PIC_WORD', 'COMMUNITY_PIC_WORD'],
    ];

    const REVIEW_TEACHER_THUMB = [
        'type' => 'REVIEW_TEACHER_THUMB',
        'keys' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
    ];

    const PACKAGE_CHANNEL = [
        'type' => 'PACKAGE_CHANNEL',
        'keys' => [1, 2, 3]
    ];

    const PACKAGE_STATUS = [
        'type' => 'PACKAGE_STATUS',
        'keys' => [-1, 0, 1]
    ];

    const PACKAGE_TYPE = [
        'type' => 'PACKAGE_TYPE',
        'keys' => [1, 2]
    ];

    const APPLY_TYPE = [
        'type' => 'APPLY_TYPE',
        'keys' => [1, 2]
    ];

    const TRIAL_TYPE = [
        'type' => 'TRIAL_TYPE',
        'keys' => [1, 2]
    ];

    const COMMUNITY_CONFIG = [
        'type' => 'COMMUNITY_CONFIG',
        'keys' => ['COMMUNITY_UPLOAD_POSTER_URL']
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
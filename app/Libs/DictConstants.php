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
            'panda_crm_host'
        ]
    ];

    const APP_CONFIG_COMMON = [
        'type' => 'APP_CONFIG_COMMON',
        'keys' => [
            'ai_host',
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
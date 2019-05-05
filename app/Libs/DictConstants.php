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

    const SMS_CENTER = [
        'type' => 'SMS_CENTER_CONFIG',
        'keys' => [
            'host'
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
}
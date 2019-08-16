<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:00 PM
 */

namespace App\Services;


use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\AppVersionModel;

class AdminService
{
    const MAIN_MENU_TEMPLATE = 'admin-menu.phtml';
    const PAGE_TEMPLATE = 'admin-menu-%s.phtml';
    const PAGE_URI = '/admin/menu/page?key=';
    const PROCESS_URI = '/admin/menu/process?key=';

    const MENU_CONFIG = [
        'app_cache' => ['title' => 'APP版本缓存'],
        'cache' => ['title' => '缓存管理'],
        'sms_code' => ['title' => '手机验证码'],
    ];

    public static function getPageTemplate($key)
    {
        return sprintf(self::PAGE_TEMPLATE, $key);
    }

    public static function getMainMenu()
    {
        $menu = array_map(function ($key, $item) {
            $item['uri'] = self::PAGE_URI . $key;
            return $item;
        }, array_keys(self::MENU_CONFIG), self::MENU_CONFIG);

        $template = self::MAIN_MENU_TEMPLATE;

        return [$template, [
            'menu' => $menu
        ]];
    }

    public static function defaultFuncName($key)
    {
        return str_replace('_', '', ucwords($key, '_'));
    }

    public static function getPage($key)
    {
        if (empty(self::MENU_CONFIG[$key])) {
            return [null, null];
        }

        $menuItem = self::MENU_CONFIG[$key];
        $data = self::pageData($menuItem['data'] ?? self::defaultFuncName($key));

        $template = self::getPageTemplate($key);

        return [$template, $data];
    }

    public static function pageData($dataFunc)
    {
        $data = call_user_func(self::class . '::pageData' . $dataFunc);
        return $data ?? [];
    }

    public static function processPage($key, $params)
    {
        if (empty(self::MENU_CONFIG[$key])) {
            return ['invalid_page_key'];
        }

        $menuItem = self::MENU_CONFIG[$key];
        list($error, $result) = self::process($menuItem['process'] ?? self::defaultFuncName($key), $params);

        return [$error, $result];
    }

    public static function process($processFunc, $params)
    {
        list($error, $result) = call_user_func(self::class . '::process' . $processFunc, $params);

        return [$error, $result];
    }

    public static function pageDataAppCache()
    {
        $data = [
            'post' => self::PROCESS_URI . 'app_cache',
            'student' => [
                'publish' => [
                    'ios' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_PUBLISH, AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS),
                        'value' => AppVersionModel::getPublishVersion(AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS)
                    ],
                    'android' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_PUBLISH, AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_ANDROID),
                        'value' => AppVersionModel::getPublishVersion(AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_ANDROID)
                    ],
                ],
                'review' => [
                    'ios' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_REVIEW, AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS),
                        'value' => AppVersionModel::getReviewVersion(AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS)
                    ],
                    'android' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_REVIEW, AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_ANDROID),
                        'value' => AppVersionModel::getReviewVersion(AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_ANDROID)
                    ],
                ],
            ],
            'teacher' => [
                'publish' => [
                    'ios' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_PUBLISH, AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_IOS),
                        'value' => AppVersionModel::getPublishVersion(AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_IOS)
                    ],
                    'android' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_PUBLISH, AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_ANDROID),
                        'value' => AppVersionModel::getPublishVersion(AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_ANDROID)
                    ],
                ],
                'review' => [
                    'ios' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_REVIEW, AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_IOS),
                        'value' => AppVersionModel::getReviewVersion(AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_IOS)
                    ],
                    'android' => [
                        'key' => AppVersionModel::getCacheKey(AppVersionModel::VER_TYPE_REVIEW, AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_ANDROID),
                        'value' => AppVersionModel::getReviewVersion(AppVersionModel::APP_TYPE_TEACHER, AppVersionService::PLAT_ID_ANDROID)
                    ],
                ],
            ],
        ];

        return $data;
    }

    public static function processAppCache($params)
    {
        $cacheKey = $params['cache_key'];
        $redis = RedisDB::getConn();
        $redis->del($cacheKey);
        return [null, 'success'];
    }

    public static function pageDataCache()
    {
        return [];
    }

    public static function processCache($params)
    {
        function getString($cacheKey) {
            $redis = RedisDB::getConn();
            $value = $redis->get($cacheKey);
            return [null, $value];
        }

        function delString($cacheKey) {
            $redis = RedisDB::getConn();
            $value = $redis->del($cacheKey);
            return [null, $value];
        }

        function getHash($cacheKey, $cacheSubKey = null) {
            $redis = RedisDB::getConn();
            if (empty($cacheSubKey)) {
                $value = $redis->hgetall($cacheKey);
            } else {
                $value = $redis->hget($cacheKey, $cacheSubKey);
            }
            return [null, $value];
        }

        function delHash($cacheKey, $cacheSubKey = null) {
            $redis = RedisDB::getConn();
            if (empty($cacheSubKey)) {
                $value = $redis->del($cacheKey);
            } else {
                $value = $redis->hdel($cacheKey, $cacheSubKey);
            }
            return [null, $value];
        }

        $cacheKey = $params['cache_key'];
        $cacheSubKey = $params['cache_sub_key'];
        $opType = $params['op_type'];

        $redis = RedisDB::getConn();
        $cacheType = $redis->type($cacheKey);
        if (!is_string($cacheType)) {
            $cacheType = $cacheType->getPayload();
        }

        if (empty($cacheType)) {
            return ['cache_type_error'];
        }
        if ($cacheType == 'none') {
            return [null, 'none'];
        }

        if ($opType != 'get' && $opType != 'del') {
            return ['invalid_op_type'];
        }

        if ($cacheType == 'string') {
            if (!empty($cacheSubKey)) {
                return ['string_type_no_sub_key'];
            }
            if ($opType == 'get') {
                list($error, $value) = getString($cacheKey);
            } elseif ($opType == 'del') {
                list($error, $value) = delString($cacheKey);
            }
        } else {
            if ($cacheType != 'hash') {
                return ['invalid_type'];
            }
            if ($opType == 'get') {
                list($error, $value) = getHash($cacheKey, $cacheSubKey);
            } elseif ($opType == 'del') {
                list($error, $value) = delHash($cacheKey, $cacheSubKey);
            }
        }

        return [$error, $value];
    }

    public static function pageDataSmsCode()
    {
        return [];
    }

    public static function processSmsCode($params)
    {
        if(!is_numeric($params['mobile']) || strlen($params['mobile']) != 11) {
            return ['invalid_mobile'];
        }
        if(!is_numeric($params['code']) || strlen($params['code']) != 4) {
            return ['invalid_code'];
        }

        $cacheKey = 'v_code_' . $params['mobile'];
        $redis = RedisDB::getConn();
        $redis->set($cacheKey, $params['code']);
        return [null, 'success'];
    }
}
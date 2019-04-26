<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/20
 * Time: 3:54 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class AppConfigModel
{
    const TABLE = 'app_config';
    const CACHE_KEY = 'APP_CONFIG';

    const SMS_URL_CACHE_KEY = 'SMS_CENTER_URL';
    const SMS_API_CACHE_KEY = 'SMS_CENTER_API';

    const ERP_URL_KEY = 'ERP_URL';
    const ERP_API_STUDENT_REGISTER_KEY = 'ERP_API_STUDENT_REGISTER';

    const OPERN_URL_KEY = 'OPERN_URL';
    const AIPL_URL_KEY = 'AIPL_URL';
    const OPERN_API_CATEGORIES_KEY = 'OPERN_API_CATEGORIES';
    const OPERN_API_COLLECTIONS_KEY = 'OPERN_API_COLLECTIONS';
    const OPERN_API_LESSONS_KEY = 'OPERN_API_LESSONS';
    const OPERN_API_SEARCH_COLLECTIONS_KEY = 'OPERN_API_SEARCH_COLLECTIONS';
    const OPERN_API_SEARCH_OPERN_KEY = 'OPERN_API_SEARCH_OPERN';

    const AI_HOMEWORK_DEMAND_KEY = 'AI_HOMEWORK_DEMAND';

    const REVIEW_VERSION = 'REVIEW_VERSION';
    const REVIEW_VERSION_FOR_TEACHER_APP = 'REVIEW_VERSION_FOR_TEACHER_APP';

    const RESOURCE_OSS_URL = 'RESOURCE_OSS_URL';

    public static function get($key)
    {
        if (empty($key)) {
            return null;
        }

        $redis = RedisDB::getConn();
        $value = $redis->hget(self::CACHE_KEY, $key);

        if (empty($value)) {
            $config = MysqlDB::getDB()->get(self::TABLE, '*', ['key' => $key]);

            if (!empty($config)) {
                $redis->hset(self::CACHE_KEY, $key, $config['value']);
                return $config['value'];
            }
        }

        return $value;
    }

    public static function set($key, $value)
    {
        if (empty($key)) {
            return false;
        }

        $count = MysqlDB::getDB()->updateGetCount(self::TABLE, ['value' => $value], ['key' => $key]);
        if ($count > 0) {
            $redis = RedisDB::getConn();
            $redis->hset(self::CACHE_KEY, $key, $value);
            return true;
        }

        return false;
    }

    public static function new($key, $value, $desc)
    {
        if (empty($key) || empty($value)) {
            return null;
        }

        $id = MysqlDB::getDB()->insertGetID(self::TABLE, [
            'key' => $key,
            'value' => $value,
            'desc' => $desc
        ]);

        return $id;
    }
}
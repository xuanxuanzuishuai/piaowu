<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/3/5
 * Time: 11:45 AM
 */

namespace App\Models;

use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use GuzzleHttp\Client;

/**
 * app版本
 * Class AppVersionModel
 * @package ERP\Models
 *
 * 版本修改在panda-service里，这里不会收到通知，所以不能缓存
 */

class AppVersionModel
{
    const APP_TYPE_STUDENT = 'aiappstudent';
    const APP_TYPE_TEACHER = 'aiappteacher';

    const UC_APP_API_GET_VERSION = '/ajax/app_mgr/api/v1/app_version/version';
    const APP_VERSION_CACHE_KEY = 'app_version_%s_%s';
    const APP_VERSION_CACHE_EXPIRE = 3600;

    public static function getVersionCacheKey($appType, $platformId)
    {
        return sprintf(self::APP_VERSION_CACHE_KEY, $appType, $platformId);
    }

    public static function getVersionApi($appType, $platformId)
    {
        return sprintf(self::UC_APP_API_GET_VERSION, $appType, $platformId);
    }

    /**
     * 获取最新发布版本
     * @param $appType
     * @param $platformId
     * @return array|null
     */
    public static function getLastVersion($appType, $platformId)
    {
        $versionCacheKey = self::getVersionCacheKey($appType, $platformId);
        $redis = RedisDB::getConn();
        $value = $redis->get($versionCacheKey);
        if(empty($value)) {
            $versionData = self::requestVersionData($appType, $platformId);
            if (empty($versionData)) {
                return null;
            }

            usort($versionData,function ($va, $vb) {
                $vaCode = explode('.', $va['version']);
                $vbCode = explode('.', $vb['version']);

                if ($vaCode[0] ==  $vbCode[0]) {
                    if ($vaCode[1] == $vbCode[1]) {
                        if ($vaCode[2] == $vbCode[2]) { return 0;
                        } else { return $vaCode[2] < $vbCode[2] ? 1 : -1; }
                    } else { return $vaCode[1] < $vbCode[1] ? 1 : -1; }
                } else { return $vaCode[0] < $vbCode[0] ? 1 : -1; }
            });

            $lastVersion = $versionData[0];

            SimpleLogger::info(__FILE__ . __LINE__ . ' [set version cache]', [
                'key ' => $versionCacheKey,
                'cache' => $lastVersion,
            ]);
            $redis->setex($versionCacheKey, self::APP_VERSION_CACHE_EXPIRE, json_encode($lastVersion));
        } else {
            $lastVersion = json_decode($value, true);
        }
        return $lastVersion;
    }

    /**
     * 从app管理后台获取版本信息
     * @param $appType
     * @param $platformId
     * @return array
     */
    public static function requestVersionData($appType, $platformId)
    {
        $client = new Client(['debug' => false]);
        $host = AppConfigModel::get(AppConfigModel::UC_APP_URL_KEY);
        $url = $host . self::UC_APP_API_GET_VERSION;
        $requestData = [
            'query' => ['apptype' => $appType, 'platform' => $platformId],
//            'headers' => ['Content-Type' => 'application/json']
        ];

        $response = $client->request('GET', $url, $requestData);
        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();

        SimpleLogger::info(__FILE__ . ':' . __LINE__ . '[get app version]', [
            'url' => $url,
            'request_data' => $requestData,
            'status' => $status,
            'body' => $body,
        ]);

        $res = json_decode($body, true);
        if (empty($res) || $res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::info(__FILE__ . ':' . __LINE__ . '[get app version error]', [
                'errors' => $res['errors'] ?? []
            ]);
            return [];
        }

        return $res['data']['versions'] ?? [];
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/3/5
 * Time: 11:45 AM
 */

namespace App\Models;

use App\Libs\DictConstants;
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
    const APP_PUBLISH_VERSION_CACHE_KEY = 'app_publish_version_%s_%s';
    const APP_REVIEW_VERSION_CACHE_KEY = 'app_review_version_%s_%s';
    const APP_VERSION_CACHE_EXPIRE = 1800;


    /**
     * 获取App版本数据
     * @param $cacheKey
     * @param $appType
     * @param $platformId
     * @return array|null
     */
    public static function getVersionCache($cacheKey, $appType, $platformId)
    {
        $redis = RedisDB::getConn();
        $value = $redis->get($cacheKey);

        if(empty($value)) {
            self::fetchVersionData($appType, $platformId);
            $value = $redis->get($cacheKey);

            if (empty($value)) {
                return null;
            }
        }

        $version = json_decode($value, true);
        return $version;
    }

    /**
     * 审核版本
     * @param $appType
     * @param $platformId
     * @return array|null
     */
    public static function getReviewVersion($appType, $platformId)
    {
        $cacheKey = sprintf(self::APP_REVIEW_VERSION_CACHE_KEY, $appType, $platformId);
        return self::getVersionCache($cacheKey, $appType, $platformId);
    }

    /**
     * 最新发布版本
     * @param $appType
     * @param $platformId
     * @return array|null
     */
    public static function getPublishVersion($appType, $platformId)
    {
        $cacheKey = sprintf(self::APP_PUBLISH_VERSION_CACHE_KEY, $appType, $platformId);
        return self::getVersionCache($cacheKey, $appType, $platformId);
    }

    /**
     * 从app管理后台获取版本信息，设置缓存
     * app_publish_version_aiappstudent_1 AI练琴 android
     * app_publish_version_aiappstudent_2 AI练琴 ios
     * app_publish_version_aiappteacher_1 AI学琴 android
     * app_publish_version_aiappteacher_2 AI学琴 ios
     * @param $appType
     * @param $platformId
     */
    public static function fetchVersionData($appType, $platformId)
    {
        $client = new Client(['debug' => false]);
        $host = DictConstants::get(DictConstants::SERVICE, 'uc_app_host');
        $url = $host . self::UC_APP_API_GET_VERSION;
        $requestData = [
            'query' => ['apptype' => $appType, 'platform' => $platformId],
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
            return ;
        }

        $versions = $res['data']['versions'];

        if (empty($versions)) {
            return ;
        }

        $publishVerIdx = -1;
        $reviewVerIdx = -1;
        foreach ($versions as $idx => $data) {
            // 查找审核版本(review_status=1)，审核版本只有一个
            if ($reviewVerIdx < 0 && $data['review_status']) {
                $reviewVerIdx = $idx;
            }

            // 在发布版本(status=1)里查找最大版本
            if ($data['status']) {
                if ($publishVerIdx < 0) {
                    $publishVerIdx = $idx;
                } else {
                    if (self::verCmp($data[$publishVerIdx]['version'], $data[$idx]['version'])) {
                        $publishVerIdx = $idx;
                    }
                }
            }
        }

        $redis = RedisDB::getConn();
        $publishVer = ($publishVerIdx >= 0) ? $versions[$publishVerIdx] : [];
        $cacheKey = sprintf(self::APP_PUBLISH_VERSION_CACHE_KEY, $appType, $platformId);
        $redis->setex($cacheKey, self::APP_VERSION_CACHE_EXPIRE, json_encode($publishVer));

        $reviewVer = ($reviewVerIdx >= 0) ? $versions[$reviewVerIdx] : [];
        $cacheKey = sprintf(self::APP_REVIEW_VERSION_CACHE_KEY, $appType, $platformId);
        $redis->setex($cacheKey, self::APP_VERSION_CACHE_EXPIRE, json_encode($reviewVer));


        SimpleLogger::info(__FILE__ . __LINE__ . ' [set version cache]', [
            'publish ' => $publishVer,
            'review' => $reviewVer,
        ]);
    }

    /**
     * 版本号比较
     * 版本号格式为3段点号分割: 1.2.0 , 1.4.13 , 2.0.0
     * @param $va string x.y.z
     * @param $vb string x.y.z
     * @return int
     */
    public static function verCmp($va, $vb) {
        $vaCode = explode('.', $va);
        $vbCode = explode('.', $vb);

        if ($vaCode[0] ==  $vbCode[0]) {
            if ($vaCode[1] == $vbCode[1]) {
                if ($vaCode[2] == $vbCode[2]) { return 0;
                } else { return $vaCode[2] < $vbCode[2] ? 1 : -1; }
            } else { return $vaCode[1] < $vbCode[1] ? 1 : -1; }
        } else { return $vaCode[0] < $vbCode[0] ? 1 : -1; }
    }
}
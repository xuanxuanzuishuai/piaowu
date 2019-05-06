<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/9
 * Time: 5:37 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Models\AppVersionModel;

class AppVersionService
{
    const PLAT_UNKNOWN = 'unknown_plat';
    const PLAT_ANDROID = 'android';
    const PLAT_IOS = 'ios';

    const PLAT_ID_UNKNOWN = 0;
    const PLAT_ID_ANDROID = 1;
    const PLAT_ID_IOS = 2;

    /**
     * 获取审核版本号
     * @param $appType
     * @param $platformId
     * @return string|null
     */
    public static function getReviewVersionCode($appType, $platformId)
    {
        $v = AppVersionModel::getReviewVersion($appType, $platformId);
        return $v['version'] ?? null;
    }

    /**
     * 获取最后一个已发布版本
     * @param $appType
     * @param $platformId
     * @param $version
     * @return array
     */
    public static function getLastVersion($appType, $platformId, $version)
    {
        $reviewVersion = AppVersionModel::getReviewVersion($appType, $platformId);
        if ($version == $reviewVersion['version']) {
            return self::defaultLastVersion($version);
        }

        $v = AppVersionModel::getPublishVersion($appType, $platformId);
        if (empty($v)) {
            return self::defaultLastVersion($version);
        }

        $versionData = [
            'code' => $v['version'],
            'desc' => $v['ver_desc'],
            'force_update' => $v['force_update'],
            'download_url' => $v['download_url']
        ];

        return $versionData;
    }

    public static function defaultLastVersion($version)
    {
        return [
            'code' => $version,
            'desc' => '',
            'force_update' => '0',
            'download_url' => ''
        ];
    }

    public static function getHotfixConfig($appType, $platformId, $version)
    {
        $reviewVersion = AppVersionModel::getReviewVersion($appType, $platformId);
        if ($version == $reviewVersion['version']) {
            return self::defaultHotfixConfig($version);
        }

        $verString = 'default';

        $meta = self::getHotfixMeta($appType, $verString);
        if (empty($meta)) {
            return self::defaultHotfixConfig($version);
        }

        $meta['files'] = AliOSS::signUrls($meta['files'], 'url');

        return $meta;
    }

    public static function defaultHotfixConfig($currentVersion)
    {
        return [
            'config' => ['version' => $currentVersion, 'packed_time' => time()]
        ];
    }

    public static function getHotfixMeta($appType, $version = 'default')
    {
        $staticPath = PROJECT_ROOT . '/www';
        $metaFile = realpath("{$staticPath}/hotfix/{$appType}/{$version}/meta.json");
        if (!file_exists($metaFile)) {
            return null;
        }

        $meta = json_decode(file_get_contents($metaFile), true);
        return $meta ? $meta : null;
    }

    public static function getPlatformId($platform)
    {
        if ($platform == self::PLAT_ANDROID) {
            return self::PLAT_ID_ANDROID;
        }
        if ($platform == self::PLAT_IOS) {
            return self::PLAT_ID_IOS;
        }
        return self::PLAT_ID_UNKNOWN;
    }
}
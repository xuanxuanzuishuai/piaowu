<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/9
 * Time: 5:37 PM
 */

namespace App\Services;


use App\Models\AppConfigModel;
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
     * 获取最后一个已发布版本
     * @param $platformId
     * @param $version
     * @return array
     */
    public static function getLastVersion($platformId, $version)
    {
        if ($version == AppConfigModel::get('REVIEW_VERSION')) {
            return self::defaultLastVersion($version);
        }

        $v = AppVersionModel::lastVersion($platformId);
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

    public static function getHotfixConfig($platformId, $version)
    {
        if ($version == AppConfigModel::get('REVIEW_VERSION')) {
            return self::defaultHotfixConfig($version);
        }

        $host = AppConfigModel::get('HOTFIX_HOST');
        $verString = 'default';

        $meta = self::getHotfixMeta($verString);
        if (empty($meta)) {
            return self::defaultHotfixConfig($version);
        }

        foreach ($meta['files'] as $file => $info) {
            $meta['files'][$file]['url'] = "{$host}/{$verString}/{$info['file']}?{$info['md5']}";
        }

        $meta['hosts'] = [];
        $meta['update_url'] = self::getUpdateUrl($platformId);

        return $meta;
    }

    public static function defaultHotfixConfig($currentVersion)
    {
        return [
            'config' => ['version' => $currentVersion, 'packed_time' => time()]
        ];
    }

    public static function getHotfixMeta($version = 'default')
    {
        $hotfixPath = PROJECT_ROOT . '/www';
        $metaFile = realpath("$hotfixPath/$version/meta.json");
        if (!file_exists($metaFile)) {
            return null;
        }

        $meta = json_decode(file_get_contents($metaFile), true);
        return $meta ? $meta : null;
    }

    public static function getUpdateUrl($platformId)
    {
        if ($platformId == self::PLAT_ID_IOS) {
            return AppConfigModel::get('UPDATE_URL_IOS');
        } elseif ($platformId == self::PLAT_ID_ANDROID) {
            return AppConfigModel::get('UPDATE_URL_ANDROID');
        }
        return '';
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
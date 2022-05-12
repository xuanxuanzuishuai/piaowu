<?php

namespace App\Services;


use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserWeiXinModel;

class ShangYinSheService
{
    /**
     * 获取上音社链接
     * @param $collectionId
     * @return mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getMiniUrlScheme($collectionId)
    {
        //先从缓存中取
        $redis = RedisDB::getConn();
        $key = 'shang_yin_she_url_scheme_' . $collectionId;
        $urlScheme = $redis->get($key);
        if (!empty($urlScheme)) {
            return $urlScheme;
        }

        $appId    = Constants::SMART_APP_ID;
        $busiType = DssUserWeiXinModel::BUSI_TYPE_AI_PLAY_MINAPP;
        $wx = WeChatMiniPro::factory($appId, $busiType);
        $expireTime = time() + 1728000;
        $urlSchemeInfo = $wx->getSupportSmsJumpLink('/pages/index', 'co=' . $collectionId . '&ch=4080', $expireTime);
        if (!empty($urlSchemeInfo['openlink'])) {
            $urlScheme = $urlSchemeInfo['openlink'];
            $redis->setex($key, 1727900, $urlScheme);
            return $urlScheme;
        } else {
            Util::errorCapture('shang yin she url scheme error', $urlSchemeInfo);
            return $_ENV['AI_REFERRER_URL'];
        }
    }
}

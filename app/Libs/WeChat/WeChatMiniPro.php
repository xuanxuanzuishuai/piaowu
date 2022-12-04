<?php

namespace App\Libs\WeChat;


use App\Libs\HttpHelper;
use GuzzleHttp\Exception\GuzzleException;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';

    const TEMP_MEDIA_EXPIRE = 172800; // 临时media文件过期时间 2天
    const SESSION_KEY_EXPIRE = 172800; // SESSION KEY 2天

    const CACHE_KEY = '%s';
    const BASIC_WX_PREFIX = 'basic_wx_prefix_';
    const SESSION_KEY = 'SESSION_KEY';
    const KEY_TICKET = "jsapi_ticket";
    const WX_UNIONID_KEY_PRIFIX = "wx_unionid";

    const API_UPLOAD_IMAGE     = '/cgi-bin/media/upload';
    const API_SEND             = '/cgi-bin/message/custom/send';
    const API_USER_INFO        = '/cgi-bin/user/info';
    const API_GET_CURRENT_MENU = '/cgi-bin/get_current_selfmenu_info';
    const API_GET_ALL_MENU     = '/cgi-bin/menu/get';
    const API_CREATE_MENU      = '/cgi-bin/menu/create';
    const API_CREATE_SCHEME    = '/wxa/generatescheme';
    const API_CODE_2_SESSION   = '/sns/jscode2session';
    const API_TOKEN            = '/cgi-bin/token';
    const API_BATCH_USER_INFO  = '/cgi-bin/user/info/batchget';
    const API_GET_TICKET       = '/cgi-bin/ticket/getticket';
    // 个性化菜单：
    const API_MENU_ADDCONDITIONAL = '/cgi-bin/menu/addconditional';
    const API_MENU_DELCONDITIONAL = '/cgi-bin/menu/delconditional';
    const API_MENU_TRYMATCH       = '/cgi-bin/menu/trymatch';
    // 标签管理：
    const API_TAGS_CREATE         = '/cgi-bin/tags/create';
    const API_TAGS_GET            = '/cgi-bin/tags/get';
    const API_TAGS_UPDATE         = '/cgi-bin/tags/update';
    const API_TAGS_DELETE         = '/cgi-bin/tags/delete';
    // 用户批量打标签
    const API_TAGS_BATCH_TAG      = '/cgi-bin/tags/members/batchtagging';
    // 用户批量取消标签
    const API_TAGS_BATCH_UNTAG    = '/cgi-bin/tags/members/batchuntagging';
    // 获取用户身上的标签列表
    const API_TAGS_GETIDLIST      = '/cgi-bin/tags/getidlist';
    // 获取小程序码
    const GET_MINIAPP_CODE_IMAGE  = '/wxa/getwxacodeunlimit';
    // 获取公众号关注列表
    const GET_WECHAT_SUB_LIST = '/cgi-bin/user/get';


    public $nowWxApp; //当前的微信应用

    private $appId = ''; //当前的微信id
    private $secret = ''; //当前的微信secret

    private $busiType = ''; // 业务场景
    private $busiId = '';   // factory方法对应的appid，不是真正的微信的appid


    public static function factory($appId = 1, $busiType = 1)
    {
        $wxAppKey = self::getWxAppKey($appId, $busiType);
        return (new self($wxAppKey))->setBusies($appId, $busiType);
    }

    public function setBusies($busiesId, $busiesType) {
        $this->busiType = $busiesType;
        $this->busiId = $busiesId;
        return $this;
    }

    public function __construct($config)
    {
        $this->nowWxApp = $config;
    }


    /**
     * 实例化不同应用的基础key
     * @param $appId
     * @param $busiType
     * @return string
     */
    public static function getWxAppKey($appId, $busiType)
    {
        return $appId . '_' . $busiType;
    }


    /**
     * 获取微信配置信息
     *
     * @param bool $getAppId
     * @param bool $getSecret
     * @return array
     */
    public function getWeChatAppIdSecretFromDict(bool $getAppId = true, bool $getSecret = true)
    {
        if ($getAppId && empty($this->appId)) {
            $this->appId = $_ENV['WECHAT_APPID'];
        }

        if ($getSecret && empty($this->secret)) {
            $this->secret = $_ENV['WECHAT_APP_SECRET'];
        }

        return [
            'app_id' => $this->appId,
            'secret' => $this->secret,
        ];
    }

    /**
     * 通过code获取用户的open_id
     * @param $code
     * @return array|bool
     */
    public function getWeixnUserOpenIDAndAccessTokenByCode($code)
    {
        self::getWeChatAppIdSecretFromDict();

        return HttpHelper::requestJson(self::WX_HOST . '/sns/oauth2/access_token', [

            'code' => $code,
            'grant_type' => 'authorization_code',
            'appid' => $this->appId,
            'secret' => $this->secret

        ]);
    }
}

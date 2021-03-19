<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 5:07 PM
 */

namespace App\Libs\WeChat;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\HttpHelper;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\Dss\DssUserWeiXinModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';

    const TEMP_MEDIA_EXPIRE = 172800; // 临时media文件过期时间 2天
    const CACHE_KEY = '%s';
    const BASIC_WX_PREFIX = 'basic_wx_prefix_';
    const SESSION_KEY = 'SESSION_KEY';
    const KEY_TICKET = "jsapi_ticket";
    
    const API_UPLOAD_IMAGE     = '/cgi-bin/media/upload';
    const API_SEND             = '/cgi-bin/message/custom/send';
    const API_USER_INFO        = '/cgi-bin/user/info';
    const API_GET_CURRENT_MENU = '/cgi-bin/get_current_selfmenu_info';
    const API_CREATE_MENU      = '/cgi-bin/menu/create';
    const API_CREATE_SCHEME    = '/wxa/generatescheme';
    const API_CODE_2_SESSION   = '/sns/jscode2session';
    const API_TOKEN            = '/cgi-bin/token';
    const API_BATCH_USER_INFO  = '/cgi-bin/user/info/batchget';
    const API_GET_TICKET       = '/cgi-bin/ticket/getticket';

    public $nowWxApp; //当前的微信应用


    public static function factory($appId, $busiType)
    {
        $wxAppKey = self::getWxAppKey($appId, $busiType);
        return new self($wxAppKey);
    }

    public function __construct($config)
    {
        $this->nowWxApp = $config;
    }

    /**
     * 设置当前应用的access_token
     * @param $accessToken
     */
    public function setAccessToken($accessToken, $expire = 1800)
    {
        RedisDB::getConn()->setex($this->getWxAccessTokenKey($this->nowWxApp), $expire, $accessToken);
    }

    /**
     * access_token key
     * @param $app
     * @return string
     */
    private function getWxAccessTokenKey($app)
    {
        return self::BASIC_WX_PREFIX . $app . '_access_token';
    }

    /**
     * 得到当前应用的access_token
     * @return string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getAccessToken()
    {
        $accessToken = RedisDB::getConn()->get($this->getWxAccessTokenKey($this->nowWxApp));
        //空处理
        if (empty($accessToken)) {
            $this->refreshAccessToken();
        }
        return RedisDB::getConn()->get($this->getWxAccessTokenKey($this->nowWxApp));
    }

    private function requestJson($api, $params = [], $method = 'GET')
    {
        $client = new Client(['debug' => false]);

        if ($method == 'GET') {
            $data = empty($params) ? [] : ['query' => $params];
        } elseif ($method == 'POST') {
            // JSON_UNESCAPED_UNICODE 参数指定不对unicode转码 否则中文会显示为unicode
            $data = ['body' => json_encode($params, JSON_UNESCAPED_UNICODE)];
            $data['headers'] = ['Content-Type' => 'application/json'];
        } elseif ($method == 'POST_FORM_DATA') {
            $method = 'POST';
            $data = $params;
        } else {
            return false;
        }
        SimpleLogger::info("[WeChatMiniPro] send request", ['api' => $api, 'data' => $data]);

        try {
            $response = $client->request($method, $api, $data);

        } catch (GuzzleException $e) {
            SimpleLogger::error("[WeChatMiniPro] send request error", [
                'error_message' => $e->getMessage()
            ]);
            return false;
        }

        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        SimpleLogger::info("[WeChatMiniPro] send request ok", ['body' => $body, 'status' => $status]);

        if (($status != StatusCode::HTTP_OK)) {
            return false;
        }

        $res = json_decode($body, true);

        // 兼容响应为非文本，比如返回的是图片
        if (empty($res)) {
            $res = $body;
        }
        return $res;
    }

    /**
     * 生成带参数的小程序码
     * 注意参数最长32位
     * @param $paramsId
     * @param bool $retry
     * @return bool|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getMiniappCodeImage($paramsId, $retry = false)
    {
        $api = self::WX_HOST . '/wxa/getwxacodeunlimit?access_token=' . $this->getAccessToken();
        $scene = '&param_id=' . $paramsId;
        $requestParams = [
            'scene' => substr($scene, 0, 32)
        ];
        $res = $this->requestJson($api, $requestParams, 'POST');
        if (is_string($res) && strlen($res) > 0) {
            return $res;
        } elseif (is_array($res) && in_array($res['errcode'], [40001,42001]) && !$retry) {
            $this->refreshAccessToken();
            return $this->getMiniappCodeImage($paramsId, true);
        }
        SimpleLogger::error("[WeChatMiniPro] get mini app code error", [$res]);
        return false;
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
     * 刷新access_token
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function refreshAccessToken()
    {
        list($appId, $busiType) = explode('_', $this->nowWxApp);
        if ($appId == Constants::SMART_APP_ID && $busiType == DssUserWeiXinModel::BUSI_TYPE_SHOW_MINAPP){
            $this->requestAccessToken();
        } elseif ($appId == Constants::SMART_APP_ID) {
            $data = (new Dss())->updateAccessToken(['busi_type' => $busiType]);
            if (!empty($data['access_token'])) {
                $this->setAccessToken($data['access_token']);
            }
        } elseif ($appId == UserCenter::AUTH_APP_ID_OP_AGENT) {
            $this->requestAccessToken();
        } else {
            SimpleLogger::info('UNKNOW APPID', [$appId, $busiType]);
        }
    }

    /**
     * 请求微信access_token
     * @return void
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function requestAccessToken()
    {
        $params = [
            'grant_type' => 'client_credential',
            'appid'      => DictConstants::get(DictConstants::WECHAT_APPID, $this->nowWxApp),
            'secret'     => DictConstants::get(DictConstants::WECHAT_APP_SECRET, $this->nowWxApp),
        ];
        $apiUrl = $this->apiUrl(self::API_TOKEN, false);
        $res = $this->requestJson($apiUrl, $params);
        if (empty($res) || !empty($res['errcode'])) {
            SimpleLogger::error('GET ACCESS TOKEN ERROR', [$res, $params]);
            return;
        }
        $this->setAccessToken($res['access_token'], $res['expires_in']);
    }
    

    /**
     * 通过code获取用户的open_id
     * @param $code
     * @return array|bool
     */
    public function getWeixnUserOpenIDAndAccessTokenByCode($code)
    {
       return HttpHelper::requestJson(self::WX_HOST . '/sns/oauth2/access_token', [

           'code' => $code,
           'grant_type' => 'authorization_code',
           'appid' => DictConstants::get(DictConstants::WECHAT_APPID, $this->nowWxApp),
           'secret' => DictConstants::get(DictConstants::WECHAT_APP_SECRET, $this->nowWxApp)

       ]);
    }

    /**
     * 推送模板消息
     * @param $body
     * @return array|bool
     */
    public function sendTemplateMsg($body)
    {
        return HttpHelper::requestJson(self::WX_HOST . '/cgi-bin/message/template/send?access_token=' . $this->getAccessToken(), $body, 'POST');
    }

    /**
     * 拼装URL
     * @param $api
     * @param bool $withToken
     * @return string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function apiUrl($api, $withToken = true)
    {
        if ($withToken) {
            $token = $this->getAccessToken();
            $query = '?' . http_build_query(['access_token' => $token]);
        } else {
            $query = '';
        }
        return self::WX_HOST . $api . $query;
    }

    /**
     * 发送基本方法
     * @param $openId
     * @param $type
     * @param $content
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    private function send($openId, $type, $content)
    {
        $api = $this->apiUrl(self::API_SEND);

        $params = [
            'touser' => $openId,
            'msgtype' => $type,
            $type => $content
        ];
        return $this->requestJson($api, $params, 'POST');
    }

    /**
     * 获取临时素材缓存key
     * @param $key
     * @return string
     */
    private function tempMediaCacheKey($key)
    {
        return sprintf(self::CACHE_KEY, $this->nowWxApp) . '_temp_media_' . $key;
    }

    /**
     * 发送文本消息
     * @param $openId
     * @param $content
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function sendText($openId, $content)
    {
        return $this->send($openId, 'text', ['content' => $content]);
    }

    /**
     * 发送图片消息
     * @param $openId
     * @param $mediaId
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function sendImage($openId, $mediaId)
    {
        return $this->send($openId, 'image', ['media_id' => $mediaId]);
    }

    /**
     * 获取临时素材数据
     * @param $type
     * @param $tempKey
     * @param $url
     * @return false|mixed|string
     */
    public function getTempMedia($type, $tempKey, $url)
    {
        $redis = RedisDB::getConn();
        $cacheKey = $this->tempMediaCacheKey($tempKey);
        $cache = $redis->get($cacheKey);

        if (!empty($cache)) {
            $media = json_decode($cache, true);
            return $media;
        }

        $content = file_get_contents($url);

        SimpleLogger::info('download media file', [
            'url' => $url,
            'content_len' => strlen($content)
        ]);

        $res = self::uploadMedia($type, $tempKey, $content);

        if (!empty($res['media_id'])) {
            $redis->setex($cacheKey, self::TEMP_MEDIA_EXPIRE, json_encode($res));
            return $res;
        }

        return false;
    }

    /**
     * 上传素材
     * @param $type
     * @param $fileName
     * @param $content
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function uploadMedia($type, $fileName, $content)
    {
        $api = $this->apiUrl(self::API_UPLOAD_IMAGE);

        $params = [
            'multipart' => [
                [
                    'name'     => 'type',
                    'contents' => $type
                ],
                [
                    'name'     => 'media',
                    'filename' => $fileName,
                    'contents' => $content,
                ]
            ],
        ];
        return $this->requestJson($api, $params, 'POST_FORM_DATA');
    }

    /**
     * 获取用户基本信息
     * @param $openId
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getUserInfo($openId)
    {
        $api = $this->apiUrl(self::API_USER_INFO, false);
        $params = [
            'access_token' => $this->getAccessToken(),
            'openid' => $openId,
        ];
        return $this->requestJson($api, $params, 'GET');
    }

    /**
     * 获取当前自定义菜单
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getCurrentMenu()
    {
        $api = $this->apiUrl(self::API_GET_CURRENT_MENU);
        return $this->requestJson($api);
    }

    /**
     * 创建菜单
     * @param $data
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function createMenu($data)
    {
        $api = $this->apiUrl(self::API_CREATE_MENU);
        return $this->requestJson($api, $data, 'POST');
    }

    /**
     * 发送菜单消息
     * @param $openId
     * @param $data
     * @return bool|false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    //[
    //    'head_content' => '测试title',
    //    'list' => [
    //        ['id' => 1, 'content' => '满意'],
    //        ['id' => 2, 'content' => '不满意']
    //    ],
    //    'tail_content' => '欢迎'
    //]
    public function sendMenuMsg($openId, $data)
    {
        return $this->send($openId, 'msgmenu', $data);
    }

    /**
     * 生成可在短信内打开的小程序链接
     * @param $path
     * @param $query
     * @param int $expireTime
     * @return bool|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getSupportSmsJumpLink($path, $query, $expireTime = 0)
    {
        $api = $this->apiUrl(self::API_CREATE_SCHEME);
        empty($expireTime) ? $isExpire = false : $isExpire = true;
        $data = [
            'jump_wxa' => [
                'path' => $path,
                'query' => $query
            ],
            'is_expire' => $isExpire,
            'expire_time' => $expireTime
        ];
        return $this->requestJson($api, $data, 'POST');
    }

    /**
     * 小程序登录
     * @param string $code
     * @return array|false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function code2Session($code = '')
    {
        if (empty($code)) {
            return [];
        }
        $url = $this->apiUrl(self::API_CODE_2_SESSION, false);
        $params = [
            'appid'      => DictConstants::get(DictConstants::WECHAT_APPID, $this->nowWxApp),
            'secret'     => DictConstants::get(DictConstants::WECHAT_APP_SECRET, $this->nowWxApp),
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ];
        $data = $this->requestJson($url, $params);
        if (empty($data['errcode'])) {
            $this->setSessionKey($data['openid'], $data['session_key']);
        }
        return $data;
    }

    /**
     * 记录登录session信息
     * @param $openId
     * @param $sessionKey
     * @return int
     */
    public function setSessionKey($openId, $sessionKey)
    {
        return RedisDB::getConn()->hset(self::SESSION_KEY, $openId, $sessionKey);
    }

    /**
     * 获取session_key
     * @param $openId
     * @return string
     */
    public function getSessionKey($openId)
    {
        return RedisDB::getConn()->hget(self::SESSION_KEY, $openId);
    }

    /**
     * 解密手机号
     * @param $openId
     * @param $iv
     * @param $encryptedData
     * @return array|mixed
     */
    public function decryptMobile($openId, $iv, $encryptedData)
    {
        $sessionKey = $this->getSessionKey($openId);
        if (empty($sessionKey)) {
            SimpleLogger::error('SESSION KEY IS EMPTY', [$openId]);
            return [];
        }
        $w = new WXBizDataCrypt(DictConstants::get(DictConstants::WECHAT_APPID, $this->nowWxApp), $sessionKey);
        $code = $w->decryptData($encryptedData, $iv, $data);
        if ($code == 0) {
            return json_decode($data, true);
        }
        SimpleLogger::error('DECODE MOBILE ERROR:', ['code' => $code]);
        return [];
    }


    public function batchGetUserInfo(array $openidArr)
    {
        $api = $this->apiUrl(self::API_BATCH_USER_INFO);
        foreach ($openidArr as $openid) {
            $params['user_list'][] = [
                'openid' => $openid
            ];
        }

        return $this->requestJson($api, $params, 'POST');
    }

    public function getJSSignature($noncestr, $timestamp, $url)
    {

        $ticket = self::getJSAPITicket();

        if (!empty($ticket)) {
            $s = 'jsapi_ticket=' . $ticket . '&noncestr=' . $noncestr . '&timestamp=' . $timestamp . '&url=' . $url;
            SimpleLogger::info('==js signature string: ==', []);
            SimpleLogger::info($s, []);
            return sha1($s);
        }
        return false;
    }

    /**
     * 获取微信 js api ticket
     * @return bool|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getJSAPITicket()
    {
        list($appId, $busiType) = explode('_', $this->nowWxApp);
        $redis = RedisDB::getConn();
        $key = $appId . "_" . self::KEY_TICKET;
        $ticket = $redis->get($key);
        $count = 0;
        if (empty($ticket)) {
            $keyLock = $key . "_LOCK";
            $lock = $redis->setnx($keyLock, "1");
            if ($lock) {
                $redis->expire($keyLock, 2);
                $data = $this->generateJSAPITicket();
                if ($data) {
                    $redis->setex($key, $data['expires_in'] - 120, $data['ticket']);
                    $ticket = $data['ticket'];
                }
                $redis->del([$keyLock]);

            } else {
                $count++;
                if ($count > 3) {
                    return false;
                } else {
                    sleep(2);
                }
                $ticket = self::getJSAPITicket();
            }
        }
        return $ticket;
    }

    /**
     * @return false|mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function generateJSAPITicket()
    {
        // 获取 微信access token
        $at = $this->getAccessToken();
        if (!$at) {
            return false;
        }
        $api = $this->apiUrl(self::API_GET_TICKET, false);
        $params = [
            'access_token' => $at,
            'type' => 'jsapi'
        ];
        $data = $this->requestJson($api, $params);
        if (!empty($data['errcode'])) {
            SimpleLogger::error('REQUEST WECHAT ERROR', [$data, $params]);
        }
        return $data;
    }
}
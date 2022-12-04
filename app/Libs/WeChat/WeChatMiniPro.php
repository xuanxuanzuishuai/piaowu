<?php

namespace App\Libs\WeChat;


use App\Libs\HttpHelper;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';

    const TEMP_MEDIA_EXPIRE = 172800; // 临时media文件过期时间 2天
    const SESSION_KEY_EXPIRE = 172800; // SESSION KEY 2天

    const CACHE_KEY = '%s';
    const BASIC_WX_PREFIX = 'basic_wx_prefix_';
    const SESSION_KEY = 'SESSION_KEY';
    const KEY_TICKET = "jsapi_ticket";

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


    public $nowWxApp; //当前的微信应用
    private $timeout;

    private $appId = ''; //当前的微信id
    private $secret = ''; //当前的微信secret


    public static function factory($appId = 1, $busiType = 1)
    {
        $wxAppKey = self::getWxAppKey($appId, $busiType);
        return new self($wxAppKey);
    }

    public function __construct($config)
    {
        $this->nowWxApp = $config;
    }

    /**
     * 如果需要可以设置超时
     * @param $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
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

        $timeout = 0;
        if (!empty($params['timeout'])) {
            $timeout = $params['timeout'];
            unset($params['timeout']);
        }
        if (!empty($this->timeout) && empty($timeout)) {
            $timeout = $this->timeout;
        }

        if ($method == 'GET') {
            $data = empty($params) ? [] : ['query' => $params];
        } elseif ($method == 'POST') {
            // JSON_UNESCAPED_UNICODE 参数指定不对unicode转码 否则中文会显示为unicode
            $data = ['body' => json_encode($params, JSON_UNESCAPED_UNICODE)];
            $data['headers'] = ['Content-Type' => 'application/json'];
            // 部分接口不需要等待响应
            if (!empty($timeout)) {
                $data['timeout'] = $timeout;
            }
        } elseif ($method == 'POST_FORM_DATA') {
            $method = 'POST';
            $data = $params;
            if (!empty($timeout)) {
                $data['timeout'] = $timeout;
            }
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
        $this->requestAccessToken();

    }

    /**
     * 请求微信access_token
     * @return void
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function requestAccessToken()
    {
        self::getWeChatAppIdSecretFromDict();
        $params = [
            'grant_type' => 'client_credential',
            'appid'      => $this->appId,
            'secret'     => $this->secret,
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
        self::getWeChatAppIdSecretFromDict();

        return HttpHelper::requestJson(self::WX_HOST . '/sns/oauth2/access_token', [

            'code' => $code,
            'grant_type' => 'authorization_code',
            'appid' => $this->appId,
            'secret' => $this->secret

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
}

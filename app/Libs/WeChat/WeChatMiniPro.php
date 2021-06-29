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
        $api = $this->apiUrl(self::GET_MINIAPP_CODE_IMAGE);
        $scene = '&param_id=' . $paramsId;
        $requestParams = [
            'scene' => substr($scene, 0, 32),
            'timeout' => 10,
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
        $selfBusiList = [
            DssUserWeiXinModel::BUSI_TYPE_SHOW_MINAPP,
            DssUserWeiXinModel::BUSI_TYPE_AI_PLAY_MINAPP
        ];
        if ($appId == Constants::SMART_APP_ID && in_array($busiType, $selfBusiList)) {
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
            $type => $content,
            'timeout' => 2
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

        $opts = array(
            "http" => array(
                "method" => "GET",
                "timeout" => 1,//单位秒
            )
        );
        $cnt = 0;
        while ($cnt < 3 && ($content = file_get_contents($url, false, stream_context_create($opts))) === FALSE) {
            SimpleLogger::info('saveTmpImgFile get file ---', ['cnt' => $cnt]);
            $cnt++;
        }

        if (empty($content)) {
            SimpleLogger::info('fail get img resource', []);
            return  false;
        }

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
     * 查询自定义菜单的结构
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getAllMenu()
    {
        $api = $this->apiUrl(self::API_GET_ALL_MENU);
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
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
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

        self::getWeChatAppIdSecretFromDict();

        $params = [
            'appid'      => $this->appId,
            'secret'     => $this->secret,
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
        return RedisDB::getConn()->setex(self::SESSION_KEY . $openId, self::SESSION_KEY_EXPIRE, $sessionKey);
    }

    /**
     * 获取session_key
     * @param $openId
     * @param string $code
     * @return string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getSessionKey($openId, $code = '')
    {
        $cache = RedisDB::getConn()->get(self::SESSION_KEY . $openId);
        if (empty($cache) && !empty($code)) {
            $this->code2Session($code);
            return $this->getSessionKey($openId);
        }
        return $cache;
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

        self::getWeChatAppIdSecretFromDict(true, false);
        $w = new WXBizDataCrypt($this->appId, $sessionKey);
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

    /**
     * 添加个性化菜单
     * @param $menu
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function addConditionalMenu($menu)
    {
        if (empty($menu)) {
            return false;
        }
        if (is_string($menu)) {
            $menu = json_decode($menu, true);
        }
        $api = $this->apiUrl(self::API_MENU_ADDCONDITIONAL);
        $data = $this->requestJson($api, $menu, 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 删除个性化菜单
     * @param $menuId
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function delConditionalMenu($menuId)
    {
        if (empty($menuId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_MENU_DELCONDITIONAL);
        $data = $this->requestJson($api, ['menuid' => $menuId], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 测试个性化菜单匹配结果
     * @param $openId
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function tryMatchMenu($openId)
    {
        if (empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_MENU_TRYMATCH);
        $data = $this->requestJson($api, ['user_id' => $openId], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 创建标签
     * @param $name
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function createTags($name)
    {
        if (empty($name)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_CREATE);
        $data = $this->requestJson($api, ['tag' => ['name' => $name]], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 编辑标签
     * @param $id
     * @param $name
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function editTags($id, $name)
    {
        if (empty($id) || empty($name)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_UPDATE);
        $data = $this->requestJson($api, ['tag' => ['id' => $id, 'name' => $name]], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 删除标签
     * @param $id
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function deleteTags($id)
    {
        if (empty($id)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_DELETE);
        $data = $this->requestJson($api, ['tag' => ['id' => $id]], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 获取公众号已创建的标签
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getTags()
    {
        $api = $this->apiUrl(self::API_TAGS_GET);
        $data = $this->requestJson($api);
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    /**
     * 批量为用户打标签
     * @param $openId
     * @param $tagId
     * @param int $timeout
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function batchTagUsers($openId, $tagId, $timeout = 1)
    {
        if (empty($tagId) || empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_BATCH_TAG);
        if (!is_array($openId)) {
            $openId = [$openId];
        }
        $params = [
            'openid_list' => $openId,
            'tagid' => $tagId
        ];
        if (!empty($timeout)) {
            $params['timeout'] = $timeout;
        }
        return $this->requestJson($api, $params, 'POST');
    }

    /**
     * 重试给用户打标签
     * @param $openId
     * @param $tagId
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function retryTagUsers($openId, $tagId)
    {
        if (empty($tagId) || empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_BATCH_TAG);
        if (!is_array($openId)) {
            $openId = [$openId];
        }
        $params = [
            'tagid' => $tagId
        ];
        foreach ($openId as $oid) {
            $params['openid_list'] = [$oid];
            $data = $this->requestJson($api, $params, 'POST');
            if (!empty($data['errcode'])) {
                SimpleLogger::error(__FUNCTION__, [$data]);
            }
        }
        return true;
    }

    /**
     * 批量为用户取消标签
     * @param $openId
     * @param $tagId
     * @param int $timeout
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function batchUnTagUsers($openId, $tagId, $timeout = 1)
    {
        if (empty($tagId) || empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_BATCH_UNTAG);
        if (!is_array($openId)) {
            $openId = [$openId];
        }
        if (!is_array($tagId)) {
            $tagId = [$tagId];
        }
        if (!empty($timeout)) {
            $params['timeout'] = $timeout;
        }
        $params = [
            'openid_list' => $openId,
        ];
        foreach ($tagId as $id) {
            $params['tagid'] = $id;
            $this->requestJson($api, $params, 'POST');
        }
        return true;
    }

    /**
     * 重试取消用户标签
     * @param $openId
     * @param $tagId
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function retryUntagUsers($openId, $tagId)
    {
        if (empty($tagId) || empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_BATCH_UNTAG);
        if (!is_array($openId)) {
            $openId = [$openId];
        }
        if (!is_array($tagId)) {
            $tagId = [$tagId];
        }

        foreach ($tagId as $tid) {
            foreach ($openId as $oid) {
                $params = [
                    'tagid' => $tid,
                    'openid_list' => [$oid]
                ];
                $data = $this->requestJson($api, $params, 'POST');
                if (!empty($data['errcode'])) {
                    SimpleLogger::error(__FUNCTION__, [$data]);
                }
            }
        }
        return true;
    }

    /**
     * 获取用户身上的标签列表
     * @param $openId
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getUserTags($openId)
    {
        if (empty($openId)) {
            return false;
        }
        $api = $this->apiUrl(self::API_TAGS_GETIDLIST);
        $data = $this->requestJson($api, ['openid' => $openId], 'POST');
        if (!empty($data['errcode'])) {
            SimpleLogger::error(__FUNCTION__, [$data]);
            return false;
        }
        return $data;
    }

    public function delAllMenus()
    {
        $allMenu = $this->getAllMenu();
        foreach ($allMenu['conditionalmenu'] as $subMenu) {
            $this->delConditionalMenu($subMenu['menuid']);
        }
        return true;
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
            $this->appId = DictConstants::get(DictConstants::WECHAT_APPID, $this->nowWxApp);
        }

        if ($getSecret && empty($this->secret)) {
            $this->secret = DictConstants::get(DictConstants::WECHAT_APP_SECRET, $this->nowWxApp);
        }

        return [
            'app_id' => $this->appId,
            'secret' => $this->secret,
        ];
    }

    /**
     * 生成带参数的小程序码
     * 注意参数最长32位
     * @param string $miniAppQrId
     * @param bool $retry
     * @param int $timeout
     * @return bool|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getMiniAppImage($miniAppQrId, $retry = false, $timeout = 2)
    {
        $api = $this->apiUrl(self::GET_MINIAPP_CODE_IMAGE);
        $requestParams = [
            'scene' => substr($miniAppQrId, 0, 32),
        ];
        $this->timeout = $timeout;
        $res = $this->requestJson($api, $requestParams, 'POST');
        if (is_string($res) && strlen($res) > 0) {
            return $res;
        } elseif (is_array($res) && in_array($res['errcode'], [40001,42001]) && !$retry) {
            $this->refreshAccessToken();
            return $this->getMiniAppImage($miniAppQrId, true);
        }
        SimpleLogger::error("[WeChatMiniPro] get mini app code error", [$res]);
        return false;
    }
}

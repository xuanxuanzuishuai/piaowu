<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 18:08
 */

namespace App\Services;

use App\Libs\RedisDB;
use App\Libs\SentryClient;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use GuzzleHttp\Client;
use App\Libs\UserCenter;
use App\Models\UserWeixinModel;
use App\Models\StudentModelForApp;
use App\Models\TeacherModelForApp;
use App\Models\WeChatConfigModel;
use Slim\Http\StatusCode;


class WeChatService
{
    const cacheKeyTokenPri = "wechat_token_";

    const KEY_TOKEN = "access_token";
    const KEY_TICKET = "jsapi_ticket";

    const USER_TYPE_STUDENT = UserWeixinModel::USER_TYPE_STUDENT;
    const USER_TYPE_TEACHER = UserWeixinModel::USER_TYPE_TEACHER;
    const USER_TYPE_STUDENT_ORG = UserWeixinModel::USER_TYPE_STUDENT_ORG;

    const CONTENT_TYPE_TEXT = 'text';

    const weixinAPIURL = 'https://api.weixin.qq.com/cgi-bin/';

    //微信广告平台
    const marketingURL = 'https://api.weixin.qq.com/marketing/';

    /**
     * 微信网页授权相关API URL
     */
    protected static $weixinHTML5APIURL = 'https://api.weixin.qq.com/sns/';

    public static $redisExpire = 2592000; // 30 days

    public static $WeChatInfoMap = [
        UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER . "_" . UserWeixinModel::USER_TYPE_TEACHER => [
            "app_id" => "TEACHER_WEIXIN_APP_ID",
            "secret" => "TEACHER_WEIXIN_APP_SECRET"
        ],
        UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . "_" . UserWeixinModel::USER_TYPE_STUDENT => [
            "app_id" => "STUDENT_WEIXIN_APP_ID",
            "secret" => "STUDENT_WEIXIN_APP_SECRET"
        ],
        UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . "_" . UserWeixinModel::USER_TYPE_STUDENT_ORG => [
            "app_id" => "STUDENT_WEIXIN_ORG_APP_ID",
            "secret" => "STUDENT_WEIXIN_ORG_APP_SECRET"
        ],
        "0_" . UserWeixinModel::USER_TYPE_TEACHER => [ //TheONE国际钢琴课
            "app_id" => "CLASSROOM_TEACHER_WEIXIN_APP_ID",
            "secret" => "CLASSROOM_TEACHER_WEIXIN_APP_SECRET"
        ],
        '1_' . UserWeixinModel::USER_TYPE_STUDENT => [ //landing页公众号内支付
            "app_id" => "STUDENT_PUB_PAY_APP_ID",
            "secret" => "STUDENT_PUB_PAY_APP_SECRET"
        ]
    ];

    protected static $redisDB;

    public static function getWeCHatAppIdSecret($app_id, $userType) {
        $info =  self::$WeChatInfoMap[$app_id . "_" . $userType];
        if (!empty($info)){
            return [
                "app_id" => $_ENV[$info["app_id"]],
                "secret" => $_ENV[$info["secret"]]
            ];
        }
        return null;
    }

    /**
     * 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token) {
        return self::cacheKeyTokenPri . $token;
    }

    /**
     * 生成token
     * @param $user_id
     * @param $user_type
     * @param $app_id
     * @param $open_id
     * @return string
     */
    public static function generateToken($user_id, $user_type, $app_id, $open_id) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $key = self::getTokenKey($token);

        $redis = RedisDB::getConn(self::$redisDB);
        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id" => $user_id,
            "user_type" => $user_type,
            "app_id" => $app_id,
            "open_id" => $open_id
        ]));
        return $token;
    }

    /**
     * 刷新token过期时间
     * @param $token
     */
    public static function refreshToken($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $redis->expire($key, self::$redisExpire);
    }

    /**
     * @param $token
     * @return mixed|string
     */
    public static function getTokenInfo($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $ret = $redis->get($key);
        if (!empty($ret)) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public static function deleteToken($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $redis->expire($key, 0);
    }

    /**
     * 根据用户授权获得的code换取用户open id 和access id
     * @param $code
     * @param $app_id
     * @param $user_type
     * @return bool|mixed
     */
    public static function getWeixnUserOpenIDAndAccessTokenByCode($code, $app_id, $user_type)
    {

        $app_info = self::getWeCHatAppIdSecret($app_id, $user_type);
        if (empty($app_info)) {
            return false;
        }

        try {
            $client = new Client(['base_uri' => self::$weixinHTML5APIURL]);
            $response = $client->request('GET', 'oauth2/access_token', [
                    'query' => [
                        'code' => $code,
                        'grant_type' => 'authorization_code',
                        'appid' => $app_info["app_id"],
                        'secret' => $app_info["secret"]
                    ]
                ]
            );
        } catch (\Exception $e) {
            SimpleLogger::error('obtain_openid_access_token_exception', [print_r($e->getMessage(), true)]);
            SentryClient::captureException($e, [
                'method' => 'WeChatService::getWeixnUserOpenIDAndAccessTokenByCode',
                '$code' => $code,
                '$app_id' => $app_id,
                '$user_type' => $user_type,
            ]);
            return false;
        }

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            $data = json_decode($body, 1);
            if (!empty($data)) {
                return $data;
            }
        } else {
            SimpleLogger::error('obtain_openid_access_token_error', ['error' => $response->getBody()->getContents()]);
        }

        return false;
    }

    /**
     * 调用微信常规接口发送数据
     * @param $app_id
     * @param $userType
     * @param $requestType
     * @param $method
     * @param $body
     * @return array|bool|mixed
     */
    public static function commonWeixinAPI($app_id, $userType, $requestType, $method, $body)
    {

        $at = self::getAccessToken($app_id, $userType);
        if (!$at) {
            return false;
        }

        $client = new Client(['base_uri' => self::weixinAPIURL]);

        if ($requestType == 'GET') {
            $subURL = $method;
            if (empty($body)) { $body = []; }
            $body['access_token'] = $at;
            $data = [
                'query' => $body
            ];

        } else {
            $subURL = "{$method}?access_token={$at}";
            $data = [
                'body' => is_string($body) ? $body : json_encode($body)
            ];
        }

        SimpleLogger::info('request weixin api: ' . $method . '[' . $requestType . ']', [$body]);

        try {
            $response = $client->request($requestType, $subURL, $data);
        } catch (\Exception $e) {
            SimpleLogger::error('WeChatService::commonWeixinAPI Exception', [print_r($e->getMessage(), true)]);
            SentryClient::captureException($e, [
                'method' => 'WeChatService::commonWeixinAPI',
                '$app_id' => $app_id,
                '$userType' => $userType,
                '$requestType' => $requestType,
                '$method' => $method,
                '$body' => $body,
            ]);
            return false;
        }

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            SimpleLogger::info('weixin api response: ' . $body, []);
            $data = json_decode($body, 1);

            if ($data['errcode'] == 40001) {
                self::clearAccessToken($app_id, $userType);
            }

            if (!empty($data)) {

                return $data;
            }
        }
        return false;
    }

    /**
     * 请求微信接口获取公众号accessToken，外部禁止调用本方法, 请调用getAccessToken方法
     * @param $appInfo
     * @return mixed|string
     */
    public static function _generateAccessToken($appInfo)
    {
        $requestParams = [
            'grant_type' => 'client_credential',
            'appid' => $appInfo['app_id'],
            'secret' => $appInfo['secret'],
        ];
        $client = new Client([
            'debug' => false
        ]);
        $data = ["query" => $requestParams];
        $url = "https://api.weixin.qq.com/cgi-bin/token";
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $url, 'data' => $data]);

        try {
            $response = $client->request("GET", $url, $data);
        } catch (\Exception $e) {
            SimpleLogger::error("WeChatService::_generateAccessToken request error",
                [[print_r($e->getMessage(), true)]]);
            SentryClient::captureException($e, [
                'method' => 'WeChatService::_generateAccessToken',
                '$requestParams' => $requestParams,
            ]);
            return false;
        }
        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ["status" => $status, "body" => $body]);

        if ($status !== 200 || !$body) {
            SimpleLogger::error("WeChatService::_generateAccessToken request error", []);
            return false;
        }

        $body = json_decode($body, true);
        if (isset($body["errcode"])) {
            SimpleLogger::error("WeChatService::_generateAccessToken response error", []);
            return false;
        }

        return $body;
    }

    /**
     * 获取公众号accessToken
     * @param $app_id
     * @param $userType
     * @return bool|string
     */
    public static function getAccessToken($app_id, $userType)
    {
        $redis = RedisDB::getConn();
        $appInfo = self::getWeCHatAppIdSecret($app_id, $userType);
        SimpleLogger::debug("getWeCHatAppIdSecret", ["data" => $appInfo]);
        if (empty($appInfo)) {
            return false;
        }

        $key = $appInfo['app_id'] . "_" . self::KEY_TOKEN;
        $accessToken = $redis->get($key);
        SimpleLogger::info("mini pro access token = $accessToken with $key", []);
        $count = 0;
        if (empty($accessToken)) {
            SimpleLogger::info("start getting the access toke with plock", []);
            $keyLock = $key . "_LOCK";
            $lock = $redis->setnx($keyLock, "1");
            SimpleLogger::info("writing plock is $lock", []);
            if ($lock) {
                $redis->expire($keyLock, 2);
                $data = self::_generateAccessToken($appInfo);
                if ($data) {
                    SimpleLogger::info("access token data", []);
                    SimpleLogger::info(print_r($data, true), []);
                    $redis->setex($key, $data['expires_in'] - 120, $data['access_token']);
                    $accessToken = $data['access_token'];
                    SimpleLogger::info("access token = " . $accessToken, []);
                }
                $redis->del($keyLock);
            } else {
                $count++;
                SimpleLogger::info("The waited plock time is $count", []);
                if ($count > 3) {
                    return false;
                } else {
                    sleep(2);
                }
                $accessToken = self::getAccessToken($app_id, $userType);
            }
        }
        return $accessToken;
    }

    /**
     * 清除 access_token
     * @param $app_id
     * @param $userType
     * @return bool
     */
    public static function clearAccessToken($app_id, $userType)
    {
        $redis = RedisDB::getConn();
        $appInfo = self::getWeCHatAppIdSecret($app_id, $userType);
        SimpleLogger::debug("getWeCHatAppIdSecret", ["data" => $appInfo]);
        if (empty($appInfo)) {
            return false;
        }

        $key = $appInfo['app_id'] . "_" . self::KEY_TOKEN;
        $redis->del($key);
        return true;
    }

    /**
     * @param $app_id int, 在UserCenter中定义
     * @param $userType int, WeChatService中定义
     * @param $openid
     * @param $templateId
     * @param $content
     * @param string $url
     * @return array|bool|mixed
     */
    public static function notifyUserWeixinTemplateInfo($app_id, $userType, $openid, $templateId, $content, $url = '')
    {
        //组织数据
        $body = [
            'touser' => $openid,
            'template_id' => $templateId,
        ];
        if (!empty($url)) {
            $body['url'] = $url;
        }
        $body['data'] = $content;
        //发送数据
        $res = self::commonWeixinAPI($app_id, $userType, 'POST', 'message/template/send', $body);
        //返回数据
        return $res;
    }

    /**
     * 微信通知文本消息
     * @param $app_id
     * @param $userType
     * @param $openid
     * @param $content
     * @return array|bool|mixed
     */
    public static function notifyUserWeixinTextInfo($app_id, $userType, $openid, $content){
        //发送数据
        $res = self::commonWeixinAPI($app_id, $userType, 'POST', 'message/custom/send',
            json_encode(['touser' => $openid,
            'msgtype' => self::CONTENT_TYPE_TEXT,
            'text' => [
                'content' => $content
            ]
        ], JSON_UNESCAPED_UNICODE));
        //返回数据
        return $res;
    }

    /**
     * 微信图片消息
     * @param $app_id
     * @param $userType
     * @param $openid
     * @param $mediaId
     * @return array|bool|mixed
     */
    public static function toNotifyUserWeixinCustomerInfoForImage($app_id, $userType, $openid, $mediaId)
    {
        $body =  ['touser' => $openid, 'msgtype' => 'image', 'image' => ['media_id' => $mediaId]];
        $res = self::commonWeixinAPI($app_id, $userType, 'POST', 'message/custom/send', $body);
        //返回数据
        return $res;
    }

    /**
     * 获取微信 js api ticket
     * @param $app_id
     * @param $user_type
     * @return bool|string
     */
    public static function getJSAPITicket($app_id, $user_type)
    {
        $app_info = self::getWeCHatAppIdSecret($app_id, $user_type);
        if (empty($app_info)) {
            return false;
        }
        $redis = RedisDB::getConn(self::$redisDB);
        $key = $app_info["app_id"] . "_" . self::KEY_TICKET;
        $ticket = $redis->get($key);
        SimpleLogger::info("js ticket = $ticket with $key", []);
        $count = 0;
        if (empty($ticket)) {
            SimpleLogger::info("start getting the jsapi ticket with plock", []);
            $keyLock = $key . "_LOCK";
            $lock = $redis->setnx($keyLock, "1");
            SimpleLogger::info("writing jsplock is $lock", []);
            if ($lock) {
                $redis->expire($keyLock, 2);
                $data = self::generateJSAPITicket($app_id, $user_type);
                if ($data) {
                    SimpleLogger::info("jsapi ticket data", []);
                    SimpleLogger::info(print_r($data, true), []);
                    $redis->setex($key, $data['expires_in'] - 120, $data['ticket']);
                    $ticket = $data['ticket'];
                }
                $redis->del($keyLock);

            } else {
                $count++;
                if ($count > 3) {
                    return false;
                } else {
                    sleep(2);
                }
                $ticket = self::getJSAPITicket($app_id, $user_type);
            }
        }
        return $ticket;
    }

    public static function generateJSAPITicket($app_id, $user_type)
    {
        // 获取 微信access token
        $at = self::getAccessToken($app_id, $user_type);
        if (!$at) {
            return false;
        }

        $client = new Client(['base_uri' => self::weixinAPIURL]);
        $response = $client->request('GET', 'ticket/getticket', [
            'query' => [
                'access_token' => $at,
                'type' => 'jsapi'
            ]
        ]);

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            $data = json_decode($body, 1);

            if (!empty($data)) {
                return $data;
            }
        }
        return false;
    }

    public static function getJSSignature($app_id, $user_type, $noncestr, $timestamp, $url)
    {

        $ticket = self::getJSAPITicket($app_id, $user_type);

        if (!empty($ticket)) {
            $s = 'jsapi_ticket=' . $ticket . '&noncestr=' . $noncestr . '&timestamp=' . $timestamp . '&url=' . $url;
            SimpleLogger::info('==js signature string: ==', []);
            SimpleLogger::info($s, []);
            return sha1($s);
        }
        return false;
    }

    public static function uploadImg($imgUrl)
    {
        $file = self::weixinAPIURL . "media/upload?access_token=" . self::getAccessToken(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT_ORG) . "&type=image";
        $data = array('media' => new \CURLFile($imgUrl));
        //创建一个新cURL资源
        $curl = curl_init();
        //设置URL和相应的选项
        curl_setopt($curl, CURLOPT_URL, $file);
        curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //执行curl，抓取URL并把它传递给浏览器
        $output = curl_exec($curl);
        SimpleLogger::info('output: ' . $output, []);
        $errno = curl_errno($curl);
        SimpleLogger::info('errno: ' . $errno, []);
        $error = curl_error($curl);
        SimpleLogger::info('error: ' . $error, []);
        curl_close($curl);
        if (empty($errno)) {
            return json_decode($output, true);
        }
        return false;
    }

    //点击反馈给微信广告平台
    public static function feedback($accessToken, array $params)
    {
        $client = new Client(['base_uri' => self::marketingURL]);
        $response = $client->request('POST', "user_actions/add?version=v1.0&access_token={$accessToken}", [
            'form_params' => $params
        ]);

        $content = '';
        $code = $response->getStatusCode();
        $success = false;

        if ($code == StatusCode::HTTP_OK) {
            $content = $response->getBody()->getContents();
            $data = json_decode($content, 1);
            $success = !empty($data) && empty($data['errmsg']);
        }

        SimpleLogger::info('request_tencent_marketing_feedback', [
            'access_token' => $accessToken,
            'params'       => $params,
            'result'       => $content,
            'status_code'  => $code,
        ]);

        return $success;
    }


    /**
     * 获取用户微信信息
     * @param $busiType
     * @param $mobile
     * @return array|bool
     */
    public static function busiTypeMap($busiType,$mobile){
        if($busiType == UserWeixinModel::BUSI_TYPE_STUDENT_SERVER){
            $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
            $userType = WeChatService::USER_TYPE_STUDENT;
            $userInfo = StudentModelForApp::getStudentInfo("", $mobile);

        }elseif($busiType == UserWeixinModel::BUSI_TYPE_TEACHER_SERVER){
            $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER;
            $userType = WeChatService::USER_TYPE_TEACHER;
            $userInfo = TeacherModelForApp::getTeacherInfo("", $mobile);
        }else{
            return [];
        }
        //获取用户信息
        $userWeChatInfo = UserWeixinModel::getBoundInfoByUserId($userInfo['id'], $appId,$userType,$busiType);
        if (empty($userWeChatInfo)) {
            return [];
        }
        //返回数据
        return [$appId,$userType,$userWeChatInfo];
    }


    /**
     * 微信发送自定义消息
     * @param int $mobile               用户手机号码
     * @param int $id                   微信配置数据表wechat_config唯一ID
     * @param array $replaceParams      动态参数
     * @param array $configData         配置数据
     * @return array|bool|mixed
     */
    public static function notifyUserCustomizeMessage($mobile, $id = 0, $replaceParams = [], $configData = [])
    {
        //判断发送的数据是否存在:$configData为指定的发送数据，如果为空根据ID去数据库查询数据
        $res = [];
        if (empty($configData)) {
            $configData = WeChatConfigModel::getRecord(['id' => $id], [], false);
            if (empty($configData)) {
                SimpleLogger::info('wechat config data is not exits', ['id' => $id]);
                return $res;
            }
        }
        //获取公众号数据
        list($appId, $userType, $userWeChatInfo) = self::busiTypeMap($configData['type'], $mobile);
        if (empty($appId) || empty($userType) || empty($userWeChatInfo)) {
            SimpleLogger::info('wechat config info error', [$configData['type']]);
            return $res;
        }
        //发送数据
        if ($configData['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) {
            //文本消息
            $res = self::notifyUserWeixinTextInfo($appId, $userType, $userWeChatInfo['open_id'], Util::textDecode($configData['content']));
        } elseif ($configData['content_type'] == WeChatConfigModel::CONTENT_TYPE_IMG) {
            //图片消息
            $data = WeChatService::uploadImg($configData['content']);
            if (!empty($data['media_id'])) {
                $res = self::toNotifyUserWeixinCustomerInfoForImage($appId, $userType, $userWeChatInfo['open_id'], $data['media_id']);
            }
        } elseif ($configData['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEMPLATE) {
            //模版消息
            $templateConfig = json_decode($configData['content'], true);
            //根据关键标志替换模板内容
            foreach ($templateConfig['vars'] as &$tcv){
                $tcv['value'] = Util::pregReplaceTargetStr(Util::textDecode($tcv['value']),$replaceParams);
            }
            $url = $replaceParams['url'] ?? $templateConfig["url"];
            $res = self::notifyUserWeixinTemplateInfo($appId, $userType, $userWeChatInfo['open_id'], $templateConfig["template_id"], $templateConfig['vars'], $url);
        } else {
            return $res;
        }
        //返回数据
        return $res;
    }
}

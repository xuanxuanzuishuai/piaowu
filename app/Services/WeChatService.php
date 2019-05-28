<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 18:08
 */

namespace App\Services;

use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;
use App\Libs\UserCenter;
use App\Models\UserWeixinModel;


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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getWeixnUserOpenIDAndAccessTokenByCode($code, $app_id, $user_type)
    {

        $client = new Client(['base_uri' => self::$weixinHTML5APIURL]);

        $app_info = self::getWeCHatAppIdSecret($app_id, $user_type);
        if (empty($app_info)) {
            return false;
        }

        $response = $client->request('GET', 'oauth2/access_token', [
                'query' => [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'appid' => $app_info["app_id"],
                    'secret' => $app_info["secret"]
                ]
            ]
        );

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            $data = json_decode($body, 1);
            if (!empty($data)) {
                return $data;
            }
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function commonWeixinAPI($app_id, $userType, $requestType, $method, $body)
    {

        $at = self::getAccessToken($app_id, $userType);
        if (!$at) {
            return false;
        }

        $client = new Client(['base_uri' => self::weixinAPIURL]);

        $data = [
            'body' => is_string($body) ? $body : json_encode($body)
        ];

        $subURL = "{$method}?access_token={$at}";
        if ($requestType == 'GET') {
            $subURL = $method;
            $body['access_token'] = $at;
            $data = [
                'query' => $body
            ];
        }

        SimpleLogger::info('request weixin api: ' . $method . '[' . $requestType . ']', []);

        $response = $client->request($requestType, $subURL, $data);

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            SimpleLogger::info('weixin api response: ' . $body, []);
            $data = json_decode($body, 1);
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
        $response = $client->request("GET", $url, $data);
        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ["status" => $status, "body" => $body]);
        if ($status !== 200 || !$body){
            throw new \Exception("PROXY_LOGIN_FAILED: " . json_encode($body));
        } else {
            $body = json_decode($body, true);
            if (isset($body["errcode"])) {
                throw new \Exception("PROXY_LOGIN_FAILED: " . json_encode($body));
            }
        }
        return $body;
    }

    /**
     * 获取公众号accessToken
     * @param $app_id
     * @param $userType
     * @return bool|string
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @param $app_id int, 在UserCenter中定义
     * @param $userType int, WeChatService中定义
     * @param $openid
     * @param $templateId
     * @param $content
     * @param string $url
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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

    public function toNotifyUserWeixinCustomerInfoForImage($app_id, $userType, $openid, $mediaId)
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

}

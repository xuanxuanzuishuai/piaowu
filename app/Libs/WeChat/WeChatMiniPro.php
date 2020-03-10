<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 5:07 PM
 */

namespace App\Libs\WeChat;


use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';
    const API_GET_ACCESS_TOKEN = '/cgi-bin/token';
    const API_SEND = '/cgi-bin/message/custom/send';
    const API_UPLOAD_IMAGE = '/cgi-bin/media/upload';

    const CACHE_KEY = 'wechat_%s_%s';
    const ACCESS_TOKEN_REFRESH_BEFORE = 120; // 提前一段时间刷新即将到期的access_token

    const TEMP_MEDIA_EXPIRE = 172800; // 临时media文件过期时间 2天

    private $appId = '';
    private $appSecret = '';

    public static function factory($config)
    {
        if (empty($config['app_id']) || empty($config['app_secret'])) {
            return null;
        }
        $wx = new self($config);
        return $wx;
    }

    public function __construct($config)
    {
        $this->appId = $config['app_id'];
        $this->appSecret = $config['app_secret'];
    }

    private function requestJson($api, $params = [], $method = 'GET')
    {
        try {
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
            $response = $client->request($method, $api, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info("[WeChatMiniPro] send request ok", ['body' => $body, 'status' => $status]);

            if (($status != StatusCode::HTTP_OK)) {
                return false;
            }

            $res = json_decode($body, true);

        } catch (\Exception $e) {
            SimpleLogger::error("[WeChatMiniPro] send request error", [
                'error_message' => $e->getMessage()
            ]);
            return false;
        }

        return $res;
    }

    private function accessTokenCacheKey()
    {
        return sprintf(self::CACHE_KEY, $this->appId, $this->appSecret) . '_token';
    }

    private function tempMediaCacheKey($key)
    {
        return sprintf(self::CACHE_KEY, $this->appId, $this->appSecret) . '_temp_media_' . $key;
    }

    private function getAccessToken()
    {
        $redis = RedisDB::getConn();
        $cacheKey = $this->accessTokenCacheKey();
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            return $cache;
        }

        $res = $this->requestAccessToken();
        if (!empty($res['access_token'])) {
            $redis->setex($cacheKey, $res['expires_in'] - 120, $res['access_token']);
        }

        return $redis->get($cacheKey);
    }

    private function requestAccessToken()
    {
        $res = $this->requestJson(self::WX_HOST . self::API_GET_ACCESS_TOKEN, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'grant_type' => 'client_credential'
        ]);

        if (empty($res['access_token'])) {
            return false;
        }

        return $res;
    }

    private function send($openId, $type, $content)
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return false;
        }

        $url = self::WX_HOST . self::API_SEND . '?access_token=' . $token;

        $params = [
            'touser' => $openId,
            'msgtype' => $type,
            $type => $content
        ];
        return $this->requestJson($url, $params, 'POST');
    }

    public function sendText($openId, $content)
    {
        return $this->send($openId, 'text', ['content' => $content]);
    }

    public function sendImage($openId, $mediaId)
    {
        return $this->send($openId, 'image', ['media_id' => $mediaId]);
    }

    public function getTempMedia($type, $tempKey, $url)
    {
        $redis = RedisDB::getConn();
        $cacheKey = $this->tempMediaCacheKey($tempKey);
        $cache = $redis->get($cacheKey);

        if (!empty($cache)) {
            $media = json_decode($cache, true);
            return $media;
        }

        $res = self::uploadMedia($type, $tempKey, file_get_contents($url));

        if (!empty($res['media_id'])) {
            $redis->setex($cacheKey, self::TEMP_MEDIA_EXPIRE, json_encode($res));
            return $res;
        }

        return false;
    }

    public function uploadMedia($type, $fileName, $content)
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return false;
        }

        $url = self::WX_HOST . self::API_UPLOAD_IMAGE . '?access_token=' . $token;

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
        return $this->requestJson($url, $params, 'POST_FORM_DATA');
    }
}
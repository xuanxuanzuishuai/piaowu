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
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';
    const API_GET_ACCESS_TOKEN = '/cgi-bin/token';
    const API_SEND = '/cgi-bin/message/custom/send';
    const API_UPLOAD_IMAGE = '/cgi-bin/media/upload';
    const API_SHORT_URL = '/cgi-bin/shorturl';

    const CACHE_KEY = '%s';
    const ACCESS_TOKEN_REFRESH_BEFORE = 120; // 提前一段时间刷新即将到期的access_token

    const TEMP_MEDIA_EXPIRE = 172800; // 临时media文件过期时间 2天
    const SHORT_URL_EXPIRE = 86400 * 15; // 短链接缓存过期时间 15天

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

        return $res;
    }

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

    private function accessTokenCacheKey()
    {
        // 此处的 key 和 WeChatService::getAccessToken() 方法里的保持一致，防止互相冲突刷新token
        return sprintf(self::CACHE_KEY, $this->appId) . '_access_token';
    }

    private function tempMediaCacheKey($key)
    {
        return sprintf(self::CACHE_KEY, $this->appId) . '_temp_media_' . $key;
    }

    private function shortUrlCacheKey($url)
    {
        $key = md5($url);
        return sprintf(self::CACHE_KEY, $this->appId) . '_short_url_' . $key;
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
        $api = $this->apiUrl(self::API_GET_ACCESS_TOKEN, false);
        $res = $this->requestJson($api, [
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
        $api = $this->apiUrl(self::API_SEND);

        $params = [
            'touser' => $openId,
            'msgtype' => $type,
            $type => $content
        ];
        return $this->requestJson($api, $params, 'POST');
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

    public function getShortUrl($url)
    {
        $redis = RedisDB::getConn();
        $cacheKey = $this->shortUrlCacheKey($url);
        $cache = $redis->get($cacheKey);

        if (!empty($cache)) {
            return $cache;
        }

        $res = self::createShortUrl($url);

        if (!empty($res['short_url'])) {
            $redis->setex($cacheKey, self::SHORT_URL_EXPIRE, $res['short_url']);
            return $res['short_url'];
        }

        return false;

    }

    public function createShortUrl($url)
    {
        $api = $this->apiUrl(self::API_SHORT_URL);

        $params = [
            'action' => 'long2short',
            'long_url' => $url,
        ];

        return $this->requestJson($api, $params, 'POST');
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 5:07 PM
 */

namespace App\Libs\WeChat;


use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class WeChatMiniPro
{
    const WX_HOST = 'https://api.weixin.qq.com';

    const BASIC_WX_PREFIX = 'basic_wx_prefix_';
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
    public function setAccessToken($accessToken)
    {
        RedisDB::getConn()->set($this->getWxAccessTokenKey($this->nowWxApp), $accessToken);
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
     * @param $params
     * @param bool $retry
     * @return bool|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function getMiniappCodeImage($params, $retry = false)
    {
        $api = self::WX_HOST . '/wxa/getwxacodeunlimit?access_token=' . $this->getAccessToken();
        $scene = "";
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $scene .= '&' . $key . '=' . $value;
            }
        }
        $requestParams = [
            'scene' => substr($scene, 0, 32)
        ];
        $res = $this->requestJson($api, $requestParams, 'POST');
        if (is_string($res) && strlen($res) > 0) {
            return $res;
        } elseif (is_array($res) && $res['errcode'] == 40001 && !$retry) {
            $this->refreshAccessToken();
            return $this->getMiniappCodeImage($params, true);
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
        if ($appId == Constants::SMART_APP_ID) {
            $data = (new Dss())->updateAccessToken(['busi_type' => $busiType]);
            if (!empty($data['access_token'])) {
                $this->setAccessToken($data['access_token']);
            }
        }
    }
}
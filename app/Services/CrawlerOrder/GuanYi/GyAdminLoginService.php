<?php

namespace App\Services\CrawlerOrder\GuanYi;

use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Client;

class GyAdminLoginService
{
    //登录账号
    private $mobile = null;
    private $pwd = null;
    private $route = '';
    private $_ati = '';
    private $headersUserAgent = '';
    private $loginCookieJar = null;
    private $loginHeader = null;
    public $jsessionIdCacheKey = 'crawler_gy_jsession_id_cache_';
    private $jsessionId = null;
    private $loginUri = 'http://login.guanyierp.com/login/loginDispatch';

    public function __construct($mobile, $pwd, $config)
    {
        $this->mobile = $mobile;
        $this->pwd = $pwd;
        $this->route = $config['route'];
        $this->_ati = $config['ati'];
        $this->headersUserAgent = $config['user_agent'];
    }

    /**
     * 登陆管易后台，获取关键参数
     * @return mixed|string
     */
    public function login()
    {
        try {
            $this->setCookies();
            $this->setHeader();
            //获取请求对象
            $client = new Client(['debug' => false]);
            //发起登陆请求
            $response = $client->request('POST', $this->loginUri, [
                'cookies' => $this->loginCookieJar,
                'json'    => [
                    'email'   => $this->mobile,
                    'pwd'     => $this->pwd,
                    'webType' => 'main'
                ],
                'headers' => $this->loginHeader
            ]);
            //解析数据
            $body = $response->getBody()->getContents();
            $info = json_decode($body, true);
            SimpleLogger::info("gy login response", ['gy_login' => $body]);
            if ($info['message'] != 'success') {
                Util::errorCapture("gy login error,account=" . $this->mobile . ' error_msg=' . $info['message'],
                    [$info]);
                return '';
            }
            //登陆成功，拿到跳转url,使用跳转加密url，请求有效JSESSIONID
            $redirectUrl = $info['redirectUrl'];
            $response = $client->request('GET', $redirectUrl);
            $info = $response->getHeader('Set-Cookie');
            SimpleLogger::info("gy redirect url response", ['Set-Cookie' => $info, 'response' => $response]);
            //拿到请求加密url返回的cookie信息,这个值作为获取订单列表和解密手机号关键参数
            $this->jsessionId = explode('=', explode(';', $info[1])[0])[1];
        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        $this->setJsessionIdCache();
        return $this->jsessionId;
    }

    /**
     * 设置请求cookie
     */
    private function setCookies()
    {
        //准备登陆参数，登陆参数很多，逐个精简，得到最简参数
        $this->loginCookieJar = CookieJar::fromArray([
            'saas_uemail' => $this->mobile,
            'route'       => $this->route,
            '_ati'        => $this->_ati
        ], 'login.guanyierp.com');
    }

    /**
     * 设置请求头参数
     */
    private function setHeader()
    {
        $this->loginHeader = [
            'User-Agent'      => $this->headersUserAgent,
            'Content-Type'    => 'application/json; charset=UTF-8',
            'Connection'      => 'keep-alive',
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'Origin'          => 'http://login.guanyierp.com',
            'Referer'         => 'http://login.guanyierp.com/login?webType=main&redirectUrl=http%3A%2F%2Fv2.guanyierp.com%2Flogin',
        ];
    }

    /**
     * 获取请求头agent参数
     * @return string
     */
    public function getHeadersUserAgent()
    {
        return $this->headersUserAgent;
    }

    /**
     * 设置缓存
     */
    private function setJsessionIdCache()
    {
        $rdb = RedisDB::getConn();
        $rdb->setex($this->jsessionIdCacheKey . $this->mobile, Util::TIMESTAMP_1H, $this->jsessionId);
    }

    /**
     * 获取缓存
     * @return string
     */
    public function getJsessionIdCache(): string
    {
        $rdb = RedisDB::getConn();
        $cacheData = $rdb->get($this->jsessionIdCacheKey . $this->mobile);
        if (empty($cacheData)) {
            return '';
        }
        return $cacheData;
    }
}
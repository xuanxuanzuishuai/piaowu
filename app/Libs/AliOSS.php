<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2019/2/22
 * Time: 5:04 PM
 */

namespace App\Libs;


use DateTime;

class AliOSS
{
    private function gmt_iso8601($time) {
        $dtStr = date("c", $time);
        $mydatetime = new DateTime($dtStr);
        $expiration = $mydatetime->format(DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration."Z";
    }

    /**
     * 获取客户端上传签名
     * @param string $accessKeyId 阿里OSSAccessKeyId
     * @param string $accessKeySecret 阿里OSSAccessKeySecret
     * @param string $host Bucket.endpoint
     * @param string $callbackUrl 应用服务器回调地址
     * @param string $dir 上传前缀
     * @param int    $expire policy过期时间（秒）
     * @param int    $maxFileSize 最大文件大小(默认1G)
     * @return array
     */
    public function getSignature($accessKeyId, $accessKeySecret, $host, $callbackUrl, $dir = '', $expire = 30, $maxFileSize=1048576000) {
        $callback_param = array(
            'callbackUrl' => $callbackUrl,
            'callbackBody' => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        );
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);

        $now = time();
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);

        // 最大文件大小
        $condition = array(0=>'content-length-range', 1=>0, 2=> intval($maxFileSize));
        $conditions[] = $condition;
        // 表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
        $start = array(0=>'starts-with', 1=>'$key', 2=>$dir);
        $conditions[] = $start;

        $arr = array('expiration' => $expiration, 'conditions' => $conditions);
        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $accessKeySecret, true));

        return array(
            'accessid' => $accessKeyId,
            'host' => $host,
            'policy' => $base64_policy,
            'signature' => $signature,
            'expire' => $end,
            'callback' => $base64_callback_body,
            'dir' => $dir
        );
    }

    /**
     * 阿里OSS上传回调处理
     * 注意： 如果要使用HTTP_AUTHORIZATION头，你需要先在apache或者nginx中设置rewrite
     *
     * @param $authorizationBase64  $_SERVER['HTTP_AUTHORIZATION']
     * @param $pubKeyUrlBase64      $_SERVER['HTTP_X_OSS_PUB_KEY_URL']
     * @param $requestUrl           $_SERVER['REQUEST_URI']
     * @return bool
     */
    public function uploadCallback($authorizationBase64, $pubKeyUrlBase64, $requestUrl){
        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == ''){
            return false;
        }
        // 获取OSS的签名
        $authorization = base64_decode($authorizationBase64);
        // 获取公钥
        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        SimpleLogger::info("ALIOSS CALLBACK", [$authorization, $pubKeyUrl]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);
        if ($pubKey == ""){
            return false;
        }

        // 获取回调body
        $body = file_get_contents('php://input');
        // 拼接待签名字符串
        $authStr = '';
        $path = $requestUrl;
        $pos = strpos($path, '?');
        if ($pos === false){
            $authStr = urldecode($path . "\n" . $body);
        } else {
            $authStr = urldecode(substr($path, 0, $pos)).substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }
        SimpleLogger::info("ALIOSS AUTHSTR", [$authStr]);
        // 验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
        if ($ok == 1){
            return true;
        }else{
            return false;
        }
    }

}
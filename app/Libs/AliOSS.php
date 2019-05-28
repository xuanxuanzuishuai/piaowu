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
use OSS\Core\OssException;
use OSS\OssClient;

class AliOSS
{
    const DIR_IMG = 'img';
    const DIR_TEACHER_NOTE = 'teacher_note';
    const DIR_DYNAMIC_MIDI = 'dynamic_midi';

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
     * @param string $bucket
     * @param string $endpoint
     * @param string $callbackUrl 应用服务器回调地址
     * @param string $dir 上传前缀
     * @param int    $expire policy过期时间（秒）
     * @param int    $maxFileSize 最大文件大小(默认1G)
     * @return array
     */
    public function getSignature($accessKeyId, $accessKeySecret, $bucket, $endpoint, $callbackUrl, $dir = '', $expire = 30, $maxFileSize=1048576000)
    {

        // 目前没有需要上传回调的逻辑
        Util::unusedParam($callbackUrl);
//        $callback_param = array(
//            'callbackUrl' => $callbackUrl,
//            'callbackBody' => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
//            'callbackBodyType' => "application/x-www-form-urlencoded"
//        );
//        $callback_string = json_encode($callback_param);
//        $base64_callback_body = base64_encode($callback_string);

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
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'host' => $bucket . $endpoint,
            'policy' => $base64_policy,
            'signature' => $signature,
            'expire' => $end,
//            'callback' => $base64_callback_body,
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
        $path = $requestUrl;
        $pos = strpos($path, '?');
        if ($pos === false){
            $authStr = urldecode($path . "\n" . $body);
        } else {
            $authStr = urldecode(substr($path, 0, $pos)).substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }
        SimpleLogger::info("ALIOSS AUTHSTR", [$authStr]);
        // 验证签名
//        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
//        if ($ok == 1){
//            return true;
//        }else{
//            return false;
//        }
        // 默认不验证签名
        return true;
    }

    /**
     * @param        $urlNeedSign
     * @param string $columnName
     * @param string $newColumn
     * @return array|string
     */
    public static function signUrls($urlNeedSign, $columnName = "", $newColumn = ""){
        if (empty($urlNeedSign)){
            return $urlNeedSign;
        }
        $result = $urlNeedSign;

        list($accessKeyId, $accessKeySecret, $bucket, $endpoint) = DictConstants::get(
            DictConstants::ALI_OSS_CONFIG,
            [
                'access_key_id',
                'access_key_secret',
                'bucket',
                'endpoint'
            ]
        );

        try {
            $timeout = 3600 * 8;
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            if (is_array($urlNeedSign) && !empty($columnName)){
                $result = array_map(function($v) use ($ossClient, $bucket, $timeout, $columnName, $newColumn){
                    $url = $v[$columnName];
                    if (!empty($url) && !Util::isUrl($url)){
                        $url = preg_replace("/^\//","", $url);
                        $v[empty($newColumn) ? $columnName : $newColumn] = $ossClient->signUrl($bucket, $url, $timeout);
                    }
                    return $v;
                }, $urlNeedSign);
            }else{
                if (!Util::isUrl($urlNeedSign)){
                    $urlNeedSign = preg_replace("/^\//","", $urlNeedSign);
                    $result = $ossClient->signUrl($bucket, $urlNeedSign, $timeout);
                }
            }
            return $result;

        } catch (OssException $e){
            SimpleLogger::warning("OSSClient error", [$e]);
        }
        return $result;
    }

    public function getMeta($objectName, $md5)
    {
        list($accessKeyId, $accessKeySecret, $bucket, $endpoint) = DictConstants::get(
            DictConstants::ALI_OSS_CONFIG,
            [
                'access_key_id',
                'access_key_secret',
                'bucket',
                'endpoint'
            ]
        );


        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $result = $ossClient->getObjectMeta($bucket, $objectName);
            SimpleLogger::debug("META", $result);
            $hybrid = $this->md5base64_hybrid($md5);
            $r  = $result['content-md5'];

            return $hybrid == $r;

        } catch (OssException $e){
            SimpleLogger::warning("OSSClient error", [$e->getMessage()]);
        }

        return false;

    }

    private function md5base64_hybrid($md5){
        if (strlen($md5) != 32){
            return '';
        }
        $arr = str_split($md5, 1);
        $bbbArr = [];
        foreach ($arr as $item){
            $bb = base_convert($item, 16, 2);
            $bbbArr[] = sprintf("%04d", $bb);
        }
        $binStr =  join("", $bbbArr);

        $base64Chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
        $binArr = str_split($binStr, 6);
        $result = '';
        foreach ($binArr as $b){
            $i = base_convert($b, 2,10 );
            $result .= substr($base64Chars, $i, 1);
        }
        $result .= '==';
        return $result;
    }

    /**
     * 上传内容保存为文件
     * @param $objName
     * @param $file
     */
    public static function uploadFile($objName, $file)
    {
        list($accessKeyId, $accessKeySecret, $bucket, $endpoint) = DictConstants::get(
            DictConstants::ALI_OSS_CONFIG,
            [
                'access_key_id',
                'access_key_secret',
                'bucket',
                'endpoint'
            ]
        );

        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->uploadFile($bucket,$objName, $file);
        }catch (OssException $e){
            SimpleLogger::error($e->getMessage(), []);
            return;
        }
    }

    /**
     * 按类型获取文件上传路径
     * @param $dirType
     * @return string|null
     */
    public static function getDirByType($dirType)
    {
        $typeConstants = [
            self::DIR_IMG,
            self::DIR_TEACHER_NOTE,
            self::DIR_DYNAMIC_MIDI
        ];
        if (!in_array($dirType, $typeConstants)) {
            return null;
        }

        return "{$_ENV['ENV_NAME']}/{$dirType}/";
    }

}
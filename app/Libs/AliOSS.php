<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2019/2/22
 * Time: 5:04 PM
 */

namespace App\Libs;


use Classroom\Libs\File;
use Classroom\Libs\SimpleLogger;
use DateTime;
use OSS\Core\OssException;
use OSS\OssClient;

class AliOSS
{
    const DIR_IMG = 'img';
    const DIR_TEACHER_NOTE = 'teacher_note';
    const DIR_DYNAMIC_MIDI = 'dynamic_midi';
    const DIR_AUDIO_COMMENT = 'audio_comment'; // 老师课堂点评语音
    const DIR_HOMEWORK_AUDIO_COMMENT = 'homework_audio_comment'; // 课后作业点评语音
    const DIR_APP_LOG = 'app_log'; // app日志
    //音基小程序目录
    const DIR_EXAM_IMG = 'exam_img'; // 音基小程序图片
    const DIR_CONVERSE_AUDIO = 'converse_audio'; // 百度转换语音
    const DIR_MADE_AUDIO = 'made_audio'; // 自制音频
    //点评课
    const DIR_REVIEW_COURSE_AUDIO = 'review_course_audio'; // 点评课语音

    const PROCESS_STYLE_NOTE_THUMB = 'note_thumb'; // 老师笔记缩略图 image/auto-orient,1/resize,p_25/quality,q_70
    const DIR_REFERRAL = 'referral';//转介绍海报和二维码
    const DIR_APP_BANNER = 'app_banner';//app banner图
    const DIR_REFERRAL_ACTIVITY = 'referral_activity';//转介绍活动截图上传
    const DIR_WX_AUTO_REPLAY = 'wx_auto_replay'; //微信自动回复
    const DIR_CERTIFICATE = 'certificate';//学生证书
    const DIR_MAKE_OPERA = 'make_opera';    //打谱申请曲谱图片上传
    const DIR_OPN_SEARCH = 'omr'; // 曲谱搜索图源
    const DIR_STUDENT_THUMB = 'thumb'; //头像
    const DIR_MESSAGE_EXCEL = 'message_excel'; // 推送消息EXCEL
    const DIR_MINIAPP_CODE = 'miniapp_code';   // 小程序码
    const DIR_EMPLOYEE_POSTER = 'employee_poster';   // 员工海报
    const DIR_SIGN_IN_POSTER = 'sign_in_poster';//打卡截图上传


    private function gmt_iso8601($time) {
        $dtStr = date("c", $time);
        $mydatetime = new DateTime($dtStr);
        $expiration = $mydatetime->format(DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration."Z";
    }

    /**
     * 获取OSS访问凭据
     *
     * @param $bucket
     * @param $endpoint
     * @param $roleArn
     * @param $path
     * @param $sessionName
     * @return array
     */
    public static function getAccessCredential($bucket, $endpoint, $roleArn, $path, $sessionName = '')
    {
        $policy = [
            'Version' => '1',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['oss:GetObject', 'oss:PutObject'],
                    'Resource' => "acs:oss:*:*:{$bucket}/{$path}*"
                ]
            ]
        ];

        list($errorMessage, $result) = AliClient::assumeRole($roleArn, $sessionName, $policy);

        if (!empty($errorMessage)) {
            SimpleLogger::error(__FILE__ . __LINE__, [
                'action' => 'getAccessCredentials',
                'message' => $errorMessage,
                'roleArn' => $roleArn,
                'sessionName' => $sessionName,
                'policy' => $policy,
                'result' => $result
            ]);
            return ['get_access_token_error'];
        }

        $result['bucket'] = $bucket;
        $result['end_point'] = $endpoint;
        $result['path'] = $path;

        return [null, $result];
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
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'host' => $bucket . '.' . $endpoint,
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
     * @param string $urlNeedSign
     * @param string $columnName
     * @param string $newColumn
     * @param string $style 图片处理方式，在OSS后台配置
     * @param bool $useSSL
     * @param string $waterMark  水印图片参数设置字符串
     * @param string $imgSize    主图片参数设置字符串
     * @return array|string
     */
    public static function signUrls($urlNeedSign, $columnName = "", $newColumn = "", $style="", $useSSL = false, $waterMark='', $imgSize='')
    {
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

        $options = [];
        $options['x-oss-process'] = NULL;
        if (!empty($style)) {
            $options['x-oss-process'] = 'style/' . $style;
        }
        $imageOptions = '';
        //主图设置
        if(!empty($imgSize)){
            $imageOptions .= "resize,".$imgSize;
        }
        //水印图片设置
        if(!empty($waterMark)){
            //多张水印
            if (is_array($waterMark)) {
                $waterMarkStr = [];
                array_map(function ($val) use (&$waterMarkStr) {
                    $waterMarkStr[] = "watermark," . $val;
                }, $waterMark);
                $imageOptions .= implode("/", $waterMarkStr);
            } else {
                //单张水印
                $imageOptions .= "watermark," . $waterMark;
            }
        }
        if($imageOptions){
            $options['x-oss-process'] .= 'image/'.$imageOptions;

        }
        try {
            $timeout = 3600 * 8;
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            if ($useSSL) {
                $ossClient->setUseSSL(true);
            }

            if (is_array($urlNeedSign) && !empty($columnName)){
                $result = array_map(function($v) use ($ossClient,
                    $bucket,
                    $timeout,
                    $columnName,
                    $newColumn,
                    $options
                ){
                    $url = $v[$columnName];
                    if (!empty($url) && !Util::isUrl($url)){
                        $url = preg_replace("/^\//","", $url);
                        $v[empty($newColumn) ? $columnName : $newColumn] = $ossClient->signUrl($bucket,
                            $url,
                            $timeout,
                            OssClient::OSS_HTTP_GET,
                            $options);
                    }
                    return $v;
                }, $urlNeedSign);
            }else{
                if (!Util::isUrl($urlNeedSign)){
                    $urlNeedSign = preg_replace("/^\//","", $urlNeedSign);
                    $result = $ossClient->signUrl($bucket,
                        $urlNeedSign,
                        $timeout,
                        OssClient::OSS_HTTP_GET,
                        $options);
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
        if(empty($dirType)) {
            $dirType = self::DIR_IMG;
        }
        $typeConstants = [
            self::DIR_IMG,
            self::DIR_TEACHER_NOTE,
            self::DIR_DYNAMIC_MIDI,
            self::DIR_AUDIO_COMMENT,
            self::DIR_HOMEWORK_AUDIO_COMMENT,
            self::DIR_APP_LOG,
            self::DIR_CONVERSE_AUDIO,
            self::DIR_MADE_AUDIO,
            self::DIR_EXAM_IMG,
            self::DIR_REVIEW_COURSE_AUDIO,
            self::DIR_REFERRAL,
            self::DIR_APP_BANNER,
            self::DIR_REFERRAL_ACTIVITY,
            self::DIR_WX_AUTO_REPLAY,
            self::DIR_CERTIFICATE,
            self::DIR_MAKE_OPERA,
            self::DIR_OPN_SEARCH,
            self::DIR_STUDENT_THUMB,
            self::DIR_MESSAGE_EXCEL,
            self::DIR_SIGN_IN_POSTER,
        ];
        if (!in_array($dirType, $typeConstants)) {
            return null;
        }

        return "{$_ENV['ENV_NAME']}/{$dirType}/";
    }

    /**
     * 替换cdn域名(曲谱用)
     * @param string $url
     * @return string
     */
    public static function replaceCdnDomain($url)
    {
        $cdnDomain = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'cdn_domain');
        $reg = '/(http|https):\/\/([^\/]+)/i';
        $newUrl = preg_replace($reg, $cdnDomain, $url);
        return $newUrl ?? $url;
    }

    /**
     * @param $url
     * @return string|string[]
     */
    public static function replaceCdnDomainForDss($url)
    {
        $cdnDomain = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'dss_cdn_domain');
        //外部传输已经处理过的url
        if (Util::isUrl($url)) {
            $reg = '/(http[s]):\/\/([^\/]+)/i';
            $newUrl = preg_replace($reg, $cdnDomain, $url);
        } else {
            $newUrl = $cdnDomain . '/' . ($url);
        }
        return $newUrl ?? $url;
    }

    /**
     * 检测文件是否存在
     * @param $objName
     * @return bool
     */
    public static function doesObjectExist($objName)
    {
        //获取oss配置文件
        list($accessKeyId, $accessKeySecret, $bucket, $endpoint) = DictConstants::get(
            DictConstants::ALI_OSS_CONFIG,
            [
                'access_key_id',
                'access_key_secret',
                'bucket',
                'endpoint'
            ]
        );
        //访问oss接口
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $result = $ossClient->doesObjectExist($bucket,$objName);
        }catch (OssException $e){
            SimpleLogger::error($e->getMessage(), []);
            return false;
        }
        //返回结果
        return $result;
    }

    /**
     * 保存临时文件到本地服务器
     * @param $imgUrl
     * @return bool|string
     */
    public static function saveTmpImgFile($imgUrl)
    {
        $imgData = file_get_contents($imgUrl);
        if (empty($imgData)) {
            return false;
        }
        //保存临时文件
        $tmpFileName = md5($imgUrl) . '.jpg';
        list($subPath, $hashDir, $fullDir) = self::createDir('tmp_img', $tmpFileName);
        $tmpSavePath = $fullDir . '/' . $tmpFileName;
        $saveRes = file_put_contents($tmpSavePath, $imgData);
        if (empty($saveRes)) {
            return false;
        }
        chmod($tmpSavePath, 0755);
        return $tmpSavePath;
    }

    /**
     * 创建文件夹
     * @param string $subDir 存储的子目录
     * @param string $filename 文件名, 注：文件名通常为hash过的字符串
     * @return array [文件存储的子路径, 哈希后的文件夹路径, 本地存储的完整文件夹路径]
     */
    public static function createDir ($subDir, $filename)
    {
        // 文件夹 hash
        $d1 = substr($filename, 0, 2);
        $d2 = substr($filename, 2, 2);

        $hashPath = "{$d1}/{$d2}";
        $subPath = "{$subDir}/{$hashPath}";
        $fullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/{$subPath}";

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        return [$subPath, $hashPath, $fullPath];
    }
}
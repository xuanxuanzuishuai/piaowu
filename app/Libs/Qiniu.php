<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/11/11
 * Time: 5:21 PM
 */
namespace App\Libs;

use App\Services\DictService;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class Qiniu
{
    /**
     * 七牛上传
     * @param $file_path
     * @return array|string
     * @throws \Exception
     */
    public static function qiNiuUpload($file_path){

        $url = "";
        list($bucket, $accessKey, $secretKey, $domain, $qiniuFolder) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV,[
            'QINIU_BUCKET_10',
            'QINIU_ACCESS_KEY_10',
            'QINIU_SECRET_KEY_10',
            'QINIU_DOMAIN_10',
            'QINIU_FOLDER_10'
        ]);

        if (empty($bucket) || empty($secretKey) || empty($accessKey) || empty($domain)){
            $result = Valid::addErrors([], 'teacher', 'teacher_import_error');
            return $result;
        }

        //上传文件的本地路径
        $key = $qiniuFolder . "/" .md5(uniqid(microtime(true),true));

        $auth = new Auth($accessKey, $secretKey);

        $uptoken = $auth->uploadToken($bucket);

        $uploadMgr = new UploadManager();

        list($ret, $err) = $uploadMgr->putFile($uptoken, $key, $file_path);
        if ($err == null) {
//            $url =  "http://".$domain . "/" .$ret['key'];
            $url = str_replace($qiniuFolder . '/', '', $ret['key']);
        }
        return $url;
    }
}
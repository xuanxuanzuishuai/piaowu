<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/1
 * Time: 下午3:06
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\UserWeixinModel;

class StudentForMinAppService
{
    public static function register($openId, $iv, $encryptedData, $sessionKey)
    {
        $jsonMobile = self::decodeMobile($iv, $encryptedData, $sessionKey);
        if(empty($jsonMobile)) {
            return [0, null];
        }
        $mobile = $jsonMobile['purePhoneNumber'];

        $stu = StudentModelForApp::getStudentInfo(null, $mobile);
        if(empty($stu)) {
            $lastId = StudentServiceForApp::studentRegister($mobile, StudentModel::CHANNEL_EXAM_MINAPP_REGISTER);
            if(empty($lastId)) {
                SimpleLogger::error('register fail from exam', ['mobile' => $mobile]);
                return [$lastId, null];
            }
        } else {
            $lastId = $stu['id'];
        }

        //保存openid
        $user = UserWeixinModel::getRecord(
            ['open_id' => $openId, 'busi_type' => UserWeixinModel::BUSI_TYPE_EXAM_MINAPP], [], false
        );
        if(empty($user)) {
            UserWeixinModel::insertRecord([
                'user_id'   => $lastId,
                'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
                'open_id'   => $openId,
                'status'    => UserWeixinModel::STATUS_NORMAL,
                'busi_type' => UserWeixinModel::BUSI_TYPE_EXAM_MINAPP,
                'app_id'    => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            ], false);
        }

        return [$lastId, $mobile];
    }

    //解密后的数据结构：
    //{"phoneNumber":"150xxxxxxxx","purePhoneNumber":"150xxxxxxxx","countryCode":"86",
    //"watermark":{"timestamp":1572923104,"appid":"wxc97e112f104721f4"}}
    public static function decodeMobile($iv, $encryptedData, $sessionKey)
    {
        if(empty($sessionKey)) {
            SimpleLogger::error('session key is empty', []);
            return null;
        }
        $w = new WXBizDataCrypt($_ENV['EXAM_MINAPP_ID'], $sessionKey);
        $code = $w->decryptData($encryptedData, $iv, $data);
        if($code == 0) {
            return json_decode($data, 1);
        } else {
            SimpleLogger::error('decode mobile error:', ['code' => $code]);
            return null;
        }
    }

    public static function hasMobile($openId)
    {
        $user = UserWeixinModel::getRecord(['open_id' => $openId, 'busi_type' => UserWeixinModel::BUSI_TYPE_EXAM_MINAPP],
            [], false);

        return !empty($user);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/8/20
 * Time: 下午8:21
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\DictModel;
use GuzzleHttp\Client;

class CallCenterRLService
{
    /**
     * 容联七陌相关配置
     */
    const CALL_API_HOST = "https://apis.7moor.com";
    const CALL_API_DIAL_VER = "v20160818";
    const CALL_API_DIAL_URI = "call/dialout";
    const Call_API_SIGN_IN_OR_OUT_URI = "account/SignInOrOut";
    const CALL_API_ACCOUNT_ID = "N00000050732";
    const CALL_API_SECRET = "9a8a9150-fbbb-11ea-9821-b59e91cbe4d6";

    /**
     * 坐席号签入、签出常量
     */
    const SEAT_SIGN_IN = 'SignIn';
    const SEAT_SIGN_OUT = 'SignOut';

    /**
     * 获取鉴权数据
     * @return array
     */
    public static function getAuthConfig() {
        $ts = date('YmdHis', time());
        $sig = strtoupper(md5(sprintf('%s%s%s', self::CALL_API_ACCOUNT_ID, self::CALL_API_SECRET, $ts)));
        $auth = base64_encode(sprintf('%s:%s', self::CALL_API_ACCOUNT_ID, $ts));
        return array($auth, $sig);
    }

    /**
     * 生成外呼url
     * @param $sig
     * @param $uri
     * @return string
     */
    public static function getUrl($sig, $uri)
    {
        return  sprintf('%s/%s/%s/%s?sig=%s',
            self::CALL_API_HOST,self::CALL_API_DIAL_VER, $uri, self::CALL_API_ACCOUNT_ID, $sig);
    }

    /**
     * 获取外呼方式参数
     * @param $extendType
     * @return string
     */
    public static function transExtendType($extendType)
    {
        $typeMap = DictService::getTypeMap(Constants::DICT_TYPE_RL_EXTEND_TYPE);
        return isset($typeMap[$extendType]) ? $typeMap[$extendType] : '';
    }

    /**
     * 容联外呼
     * @param $fromSeatId
     * @param $toMobile
     * @param $extendType
     * @return mixed|string
     */
    public static function dialoutRonglian($fromSeatId, $toMobile, $extendType){
        SimpleLogger::info("ronglian dalout  params:", [$fromSeatId, $toMobile]);
        //获取鉴权数据
        list($auth, $sig) = self::getAuthConfig();
        //获取外呼类型
        $extendType = self::transExtendType($extendType);
        if(empty($extendType)){
            return ['errMsg' => '外呼方式错误'];
        }
        //获取自定义字段
        $userField = CallCenterService::setUserField($fromSeatId, $toMobile);
        //坐席上线
        $result = self::onlineSeat($fromSeatId, $extendType, $auth, $sig);
        if($result){
            //外呼
            return self::outCall($fromSeatId, $toMobile, $extendType, $userField, $auth, $sig);
        }
        return ['res' => Valid::CODE_PARAMS_ERROR, 'errMsg' => '坐席上线失败'];
    }

    /**
     * 坐席上线
     * @param $fromSeatId
     * @param $extendType
     * @param $auth
     * @param $sig
     * @return mixed
     */
    public static function onlineSeat($fromSeatId, $extendType, $auth, $sig)
    {
        //定义外呼参数
        $formParams = [
            'json' => [
                'sign' => self::SEAT_SIGN_IN,
                'Exten' => $fromSeatId,
                'ExtenType' => $extendType,
            ]
        ];
        //执行外呼
        $result = self::postRequest($formParams, $auth, $sig, self::Call_API_SIGN_IN_OR_OUT_URI);
        //记录签入坐席结果
        SimpleLogger::info('ronglian seat sign in result:', ['form_params' => $formParams, 'res' => $result]);
        return $result['Succeed'];
    }

    /**
     * 外呼
     * @param $fromSeatId
     * @param $toMobile
     * @param $extendType
     * @param $userField
     * @param $auth
     * @param $sig
     * @return array
     */
    public static function outCall($fromSeatId, $toMobile, $extendType, $userField, $auth, $sig)
    {

        //定义外呼参数
        $formParams = [
            'form_params' => [
                'FromExten' => $fromSeatId,
                'Exten' => $toMobile,
                'ExtenType' => $extendType,
                'DialoutStrVar' => $userField
            ]
        ];
        //执行外呼
        $result = self::postRequest($formParams, $auth, $sig, self::CALL_API_DIAL_URI);
        //处理外呼结果
        SimpleLogger::info('ronglian diaout result:', ['form_params' => $formParams, 'res' => $result]);
        if ($result['Succeed']){
            return ['res' => 0, 'userField' => $userField, 'data' => $result];
        }else{
            $errorMap = DictModel::getTypeMap(Constants::DICT_RL_ERROR);
            $errorCode = explode(' ', $result['Message']);
            $errMsg = isset($errorMap[$errorCode[0]]) ? $errorMap[$errorCode[0]] : '未知错误';
            return ['res' => Valid::CODE_PARAMS_ERROR, 'errMsg' => $errMsg];
        }
    }

    /**
     * 发送post请求
     * @param $formParams
     * @param $auth
     * @param $sig
     * @param $uri
     * @return mixed
     */
    public static function postRequest($formParams, $auth, $sig, $uri)
    {
        //获取url
        $url = self::getUrl($sig, $uri);
        //执行外呼
        $client = new Client([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json;charset=utf-8',
                'Authorization' => $auth
            ],
        ]);
        $response = $client->post($url, $formParams);
        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }
}
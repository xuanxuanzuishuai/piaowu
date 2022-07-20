<?php
/**
 * 短信通知SMS
 * @author tianye@xiaoyezi.com
 * @since 2016-04-04 16:53:21
 */

namespace App\Libs;

use App\Services\CommonServiceForApp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class NewSMS
{
    const API_SEND = '/api/qxt/send';
    const API_INT_SEND = '/api/sms/international/send';

    const DEFAULT_COUNTRY_CODE = '86';
    const COUNTRY_CODE_PREFIX = '00';

    private $url;

    // 短信类型
    const SMS_TYPE_VERIFICATION_CODE = 1;   // 验证码类短信
    const SMS_TYPE_NOTICE = 2;              // 通知类短信
    const SMS_TYPE_MARKETING = 3;           // 营销类短信

    // 短息服务商
    const SMS_SERVICE_KSYUN = 'ksyun';  // 金山云短信服务商
    // 个个服务商发送短信相关接口
    const SMS_API_MAP = [
        self::SMS_SERVICE_KSYUN => [
            'send_one'        => '/api/ksyun/send',         // 发送单条
            'batch_send'      => '/api/ksyun/batch/send',   // 批量发送 - 但是短信内容相同
            'batch_diff_send' => '/api/ksyun/diff/send'],   // 批量发送 - 但是短信内容不相同
    ];

    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * 确定服务商返回本次请求的接口地址
     * @param string $useIntApi 服务商名称
     * @param string $sendDataType 发送数据的类型
     * @return string
     */
    private function getApiUrl($serviceProviderName, $data): string
    {
        switch ($serviceProviderName) {
            case self::SMS_SERVICE_KSYUN:   // 金山云
                if (isset($data['sms_list'])) {
                    // 批量发送内容不相同短信
                    $api = self::SMS_API_MAP[self::SMS_SERVICE_KSYUN]['batch_diff_send'] ?? '';
                } elseif (isset($data['mobiles'])) {
                    // 批量发送内容相同的短息
                    $api = self::SMS_API_MAP[self::SMS_SERVICE_KSYUN]['batch_send'] ?? '';
                } else {
                    // 发送单条
                    $api = self::SMS_API_MAP[self::SMS_SERVICE_KSYUN]['send_one'] ?? '';
                }
                break;
            default:    // 创蓝
                $api = self::API_INT_SEND;
                break;
        }
        return $api;
    }

    private function sendSMS($data, $useIntApi = false)
    {
        $client = new Client();
        try {
            // 确定服务商返回本次请求的接口地址
            $serviceProviderName = $useIntApi ? '' : self::SMS_SERVICE_KSYUN;
            $api = self::getApiUrl($serviceProviderName, $data);
            if (empty($api)) {
                SimpleLogger::error('api_is_empty', ['code' => 'send sms exception', $data,$useIntApi]);
                return false;
            }
            $response = $client->request('POST', $this->url . $api, [
                'body' => json_encode($data),
                'debug' => false,
                'headers' => [
                    'Postman-Token' => 'a714ad5f-dce5-a759-b883-e92e6220fe98',
                    'Cache-Control' => 'no-cache',
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (GuzzleException $e) {
            SimpleLogger::error($e->getMessage(), ['code' => 'send sms exception', 'data' => $data]);
            return false;
        }

        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        SimpleLogger::info(__FILE__ . ":" . __LINE__ . " sendSMS ", [
            'data' => $data,
            'response' => $body
        ]);
        if (200 == $status) {
            return true;
        } else {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['code' => 'send sms failure', 'data' => $data]);
            return false;
        }
    }

    /**
     * @param string $sign 签名
     * @param string $mobile 手机
     * @param string $content 内容
     * @param string $countryCode 国家代码
     * @param string $smsType 短信类型
     * @return bool
     */
    private function send($sign, $mobile, $content, $countryCode = self::DEFAULT_COUNTRY_CODE, $smsType = self::SMS_TYPE_NOTICE)
    {
        if (empty($countryCode) || $countryCode == self::DEFAULT_COUNTRY_CODE) {
            $phone_number = $mobile;
        } else {
            $phone_number = self::COUNTRY_CODE_PREFIX . $countryCode . $mobile;
        }
        $data = [
            'sign_name' => $sign,
            'phone_number' => $phone_number,
            'content' => $content,
            'sms_type' => $smsType,
        ];
        return self::sendSMS($data, !empty($countryCode) && $countryCode != self::DEFAULT_COUNTRY_CODE);
    }

    /**
     * 发送短信验证码
     * @param $targetMobile
     * @param $msg
     * @param string $sign
     * @param string $countryCode
     * @return bool
     */
//    public function sendValidateCode($targetMobile, $msg, $sign, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
//    {
//        return self::send($sign, $targetMobile, $msg, $countryCode, self::SMS_TYPE_VERIFICATION_CODE);
//    }

    /**
     * 发送参加活动的提醒
     * @param $mobile
     * @param $sign
     * @param $startTime
     * @return bool
     */
//    public function sendAttendActSMS($mobile, $sign, $startTime)
//    {
//        $msg = "温馨提示：“上传截图领奖”活动已于{$startTime}开始，您的金叶子还未领取！请进入小叶子智能陪练服务号，点击福利中心参与。详情咨询课管老师";
//        // phone_number 支持以逗号隔开的字符串
//        $data = [
//            'sign_name' => $sign,
//            'mobiles' => $mobile,
//            'content' => $msg,
//            'sms_type' => self::SMS_TYPE_NOTICE,
//        ];
//        return self::sendSMS($data);
//    }


    /**
     * @param $sign
     * @param $mobile
     * @param $stage
     * @param $action
     * @param $sMobile
     * @param $buyTime
     * @return bool
     */
//    public function sendWebPageClickNotify($sign, $mobile, $stage, $action, $sMobile, $buyTime)
//    {
//        $msg = "有{$stage}学员待跟进，用户行为:{$action}, 请尽快联系学员 ! 学员电话:{$sMobile},{$buyTime}购买体验卡";
//        // phone_number 支持以逗号隔开的字符串
//        $data = [
//            'sign_name' => $sign,
//            'phone_number' => $mobile,
//            'content' => $msg,
//            'sms_type' => self::SMS_TYPE_NOTICE,
//        ];
//        return self::sendSMS($data);
//    }


    /**
     * 发送短信通用模版
     * @param $msg
     * @param $mobile
     * @param string $sign
     * @param int $smsType
     * @return bool
     */
    public function sendCommonSms($msg, $mobile, $sign = '', $smsType = self::SMS_TYPE_NOTICE)
    {

        if (empty($sign)) {
            $sign = CommonServiceForApp::SIGN_AI_PEILIAN;
        }

        $data = [
            'sign_name' => $sign,
            'phone_number' => $mobile,
            'content' => $msg,
            'sms_type' => $smsType,
        ];
        return self::sendSMS($data);
    }
    
    /**
     * 发送参加活动的提醒
     * @param $mobile
     * @param $mobile1
     * @return bool
     */
    public function sendInviteGiftSMS($mobile, $mobile1)
    {
        $mobile1 = Util::hideUserMobile($mobile1);
        $msg = "您已成功邀请好友（{$mobile1}），5天奖励时长已发放。请在【小叶子智能陪练】公众号-【我的账户】中查看。";
        $data = [
            'sign_name' => CommonServiceForApp::SIGN_AI_PEILIAN,
            'phone_number' => $mobile,
            'content' => $msg,
            'sms_type' => self::SMS_TYPE_NOTICE,
        ];
        return self::sendSMS($data);
    }
}

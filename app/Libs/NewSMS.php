<?php
/**
 * 短信通知SMS
 * @author tianye@xiaoyezi.com
 * @since 2016-04-04 16:53:21
 */

namespace App\Libs;

use GuzzleHttp\Client;

class NewSMS
{
    private $url;
    private $api;

    public function __construct($url, $api)
    {
        $this->url = $url;
        $this->api = $api;
    }

    private function sendSMS($data)
    {
        $client = new Client();

        $response = $client->request('POST', $this->url . $this->api, [
            'body' => json_encode($data),
            'debug' => false,
            'headers' => [
                'Postman-Token' => 'a714ad5f-dce5-a759-b883-e92e6220fe98',
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'application/json'
            ]
        ]);

        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        SimpleLogger::info(__FILE__. ":" .__LINE__,['code'=>'send sms body','data'=>$body]);
        SimpleLogger::info(__FILE__. ":" .__LINE__,['code'=>'send sms status','data'=>$status]);
        if (200 == $status) {
            return true;
        } else {
            SimpleLogger::error(__FILE__. ":" .__LINE__,['code'=>'send sms failure','data'=>$data]);
            return false;
        }
    }

    /**
     * 首通失败提醒
     * @param $targetMobile int 学生手机号
     * @param $validateCode int 验证码
     * @return bool
     */
    public function sendValidateCode($targetMobile, $validateCode)
    {
        $data = [
            'sign_name' => '小叶子爱练琴',
            'phone_number' => $targetMobile,
            'content' => "您好，本次验证码为：${validateCode}，有效期为五分钟，可以在60秒后重新获取",
        ];

        return self::sendSMS($data);
    }


    /**
     * @param $targetMobile
     * @param $validateCode
     * @param string $sign
     * @return bool
     */

    public function newSendValidateCode($targetMobile, $msg, $sign)
    {
        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }
}

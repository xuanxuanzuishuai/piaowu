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
    const API_SEND = '/api/qxt/send';

    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    private function sendSMS($data)
    {
        $client = new Client();

        $response = $client->request('POST', $this->url . self::API_SEND, [
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

    public function send($sign, $mobile, $content)
    {
        $data = [
            'sign_name' => $sign,
            'phone_number' => $mobile,
            'content' => $content,
        ];
        return self::sendSMS($data);
    }


    /**
     * 发送短信验证码
     * @param $targetMobile
     * @param $msg
     * @param string $sign
     * @return bool
     */

    public function sendValidateCode($targetMobile, $msg, $sign)
    {
        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }
}

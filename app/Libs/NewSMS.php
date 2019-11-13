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
        SimpleLogger::info(__FILE__. ":" .__LINE__ . " sendSMS ",[
            'data' => $data,
            'response' => $body
        ]);
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

    public function sendExchangeGiftCode($targetMobile, $code, $sign)
    {
        $msg = "感谢家长选择小叶子爱练琴！您购买的AI产品，激活码为：{$code}。";

        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }

    public function sendFreeGiftCode($targetMobile, $code, $sign)
    {
        $msg = "感谢家长选择小叶子爱练琴！本次活动的赠送激活码为：{$code}。可在微信【小叶子陪练】公众号中输入“激活码”查询，祝您生活愉快！";

        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }

    /**
     * 发送点评短信
     * @param $targetMobile
     * @param $sign
     * @return bool
     */
    public function sendEvaluationMessage($targetMobile, $sign)
    {
        $time = date("Y-m-d", time());
        // 获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $w = date('w', strtotime($time));
        // 1. $w = 0，周日+1； 2. $w > 0, 8 - $w
        $add = $w > 0 ? 8 - $w : 1;
        $start = strtotime("$time + " . $add . ' days');
        $week_start = date('Y-m-d', $start);
        $day = date('d', $start);

        $msg = "你已成功购买小叶子爱练琴两周点评课，将于{$week_start} 周一开始点评，请在{$day}日周一之前开始练琴哦。
【1】请添加你的练琴小助手微信(微信号: zaixianpeilian )，老师会全程个性化辅导宝贝的练琴。
【2】请点击 http://t.cn/AiBPajzr 下载“小叶子爱练琴” App，可以直接使用购买的手机号登录 App 进行练琴哦。如有其他问题，请联系添加的练琴小助手哦。";

        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }
}

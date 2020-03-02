<?php
/**
 * 短信通知SMS
 * @author tianye@xiaoyezi.com
 * @since 2016-04-04 16:53:21
 */

namespace App\Libs;

use App\Services\CommonServiceForApp;
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
     * @param $wechatcs
     * @return bool
     */
    public function sendEvaluationMessage($targetMobile, $sign, $wechatcs)
    {
        $now = time();
        $time = date("Y-m-d", $now);
        // 获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $w = date('w', strtotime($time));
        // 1. $w = 0，周日+1； 2. $w > 0, 8 - $w
        $add = $w > 0 ? 8 - $w : 1;
        $start = strtotime("$time + " . $add . ' days');
        $week_start = date('Y-m-d', $start);

        $msg = "您已成功购买小叶子智能陪练课，将于{$week_start}（周一）开课，时长两周。
《1》在哪体验智能陪练？点击链接，下载【小叶子智能陪练】App：http://t.cn/AiBPajzr
《2》在哪收老师点评？关注微信公众号【小叶子智能陪练】并绑定购买手机号！
《3》遇到问题怎么办？1V1助教老师微信：{$wechatcs}";

        if ($now > strtotime('2020-01-13') && $now < strtotime('2020-01-27')) {
            $msg = "您已成功购买小叶子智能陪练课。由于受春节放假影响，课程将延期至2020-2-3统一开课，时长两周。为表歉意，在此期间您可以正常使用APP，开课前助教老师会统一延长 App 课程的使用期限。
※请立即添加助教老师微信：{$wechatcs}，并发送购课手机号。";
        }

        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }

    /**
     * 点评完成通知
     * @param $targetMobile
     * @return bool
     */
    public function sendReviewCompleteNotify($targetMobile)
    {
        $content = "亲爱的小叶子家长您好！您今天的智能陪练课的点评已经生成，快在公众号里查看吧！未关注公众号的用户关注【小叶子智能陪练】公众号并绑定手机号，明天就能收到点评啦~";
        return self::send(CommonServiceForApp::SIGN_STUDENT_APP, $targetMobile, $content);
    }


    /**
     * 发送班级分配完成短信
     * @param $targetMobile
     * @param $sign
     * @param $collectionList
     * @return bool
     */
    public function sendCollectionCompleteNotify($targetMobile, $sign, $collectionList)
    {
        $teachingStartDate = date("Y-m-d",$collectionList[0]['teaching_start_time']);
        $teachingEndDate = date("Y-m-d",$collectionList[0]['teaching_end_time']);
        $wechatNumber = $collectionList[0]['wechat_number'];
        $week = Util::getShortWeekName($collectionList[0]['teaching_start_time']);
        $days = Util::dateDiff($teachingStartDate,$teachingEndDate);
        $msg = "您已成功购买小叶子智能陪练课，将于{$teachingStartDate}（{$week}）开课，时长{$days}天。
《1》在哪体验智能陪练？点击链接，下载【小叶子智能陪练】App：http://t.cn/AiBPajzr
《2》在哪收老师点评？关注微信公众号【小叶子智能陪练】并绑定购买手机号！
《3》遇到问题怎么办？1V1助教老师微信：{$wechatNumber}";
        $data = [
            'sign_name' => $sign,
            'phone_number' => $targetMobile,
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }
}

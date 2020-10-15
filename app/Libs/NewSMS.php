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

    public function __construct($url)
    {
        $this->url = $url;
    }

    private function sendSMS($data, $useIntApi = false)
    {
        $client = new Client();

        $api = $useIntApi ? self::API_INT_SEND : self::API_SEND;

        try {
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
     * @return bool
     */
    public function send($sign, $mobile, $content, $countryCode = self::DEFAULT_COUNTRY_CODE)
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
        ];
        return self::sendSMS($data, !empty($countryCode) && $countryCode != self::DEFAULT_COUNTRY_CODE);
    }

    /**
     * @param $sign
     * @param $mobileData array [['mobile'=>'15900000000','country_code'=>'86'],['mobile'=>'15900000000','country_code'=>'86']]
     * @param $content
     * @param string $countryCode
     * @return bool
     */
    public function batchSend($sign, $mobileData, $content, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        $result = true;
        foreach ($mobileData as $item) {
            if (empty($item['country_code']) || $item['country_code'] == self::DEFAULT_COUNTRY_CODE) {
                $phone_number = $item['mobile'];
            } else {
                $phone_number = self::COUNTRY_CODE_PREFIX . $item['country_code'] . $item['mobile'];
            }

            $data = [
                'sign_name' => $sign,
                'phone_number' => $phone_number,
                'content' => $content,
            ];
            $result = self::sendSMS($data, $item['country_code'] != self::DEFAULT_COUNTRY_CODE);
        }
        return $result;

    }


    /**
     * 发送短信验证码
     * @param $targetMobile
     * @param $msg
     * @param string $sign
     * @param string $countryCode
     * @return bool
     */
    public function sendValidateCode($targetMobile, $msg, $sign, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        return self::send($sign, $targetMobile, $msg, $countryCode);
    }

    public function sendExchangeGiftCode($targetMobile, $code, $sign, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        $msg = "您的激活码为：{$code}。您也可关注微信公众号【小叶子智能陪练】，在【我的账户】查询激活码。";

        return self::send($sign, $targetMobile, $msg, $countryCode);

    }

    public function sendFreeGiftCode($targetMobile, $code, $sign)
    {

        $msg = "您的激活码为：{$code}。您也可关注微信公众号【小叶子智能陪练】，在【我的账户】查询激活码。";

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
     * @param string $countryCode
     * @return bool
     */
    public function sendEvaluationMessage($targetMobile, $sign, $wechatcs, $countryCode = self::DEFAULT_COUNTRY_CODE)
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

        return self::send(CommonServiceForApp::SIGN_STUDENT_APP, $targetMobile, $msg);
    }

    /**
     * 点评完成通知
     * @param $targetMobile
     * @param string $countryCode
     * @return bool
     */
    public function sendReviewCompleteNotify($targetMobile, $countryCode = self::DEFAULT_COUNTRY_CODE)
    {
        $content = "亲爱的小叶子家长您好！您今天的智能陪练课的点评已经生成，快在公众号里查看吧！未关注公众号的用户关注【小叶子智能陪练】公众号并绑定手机号，明天就能收到点评啦~";
        return self::send(CommonServiceForApp::SIGN_STUDENT_APP, $targetMobile, $content);
    }


    /**
     * 发送班级分配完成短信
     * @param $targetMobile
     * @param $sign
     * @param $collectionList
     * @param string $countryCode
     * @return bool
     */
    public function sendCollectionCompleteNotify($targetMobile, $sign, $collectionList, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {

        $teachingStartDate = date("Y-m-d", $collectionList['teaching_start_time']);
        $teachingEndDate = date("Y-m-d", $collectionList['teaching_end_time']);
        $week = Util::getShortWeekName($collectionList['teaching_start_time']);
        $days = Util::dateBetweenDays($teachingStartDate, $teachingEndDate);
        $msg = "恭喜您成功购买小叶子智能陪练，开营前邀请您试用智能陪练！请您尽快关注微信公众号【小叶子智能陪练】绑定账号获得APP下载链接，我们的课程将于{$teachingStartDate}（{$week}）开始，时长{$days}天，请您务必查看开营指引【".$collectionList['collection_url']."】。如有疑问，请拨打客服电话：".$_ENV['AI_SERVER_TEL']."。";
        return self::send($sign, $targetMobile, $msg, $countryCode);
    }

    /**
     * 发送参加活动的提醒
     * @param $mobile
     * @param $sign
     * @param $startTime
     * @return bool
     */
    public function sendAttendActSMS($mobile, $sign, $startTime)
    {
        $msg = "您预约的“0元领取10元红包”已于{$startTime}开始，请在【小叶子智能陪练】微信号点击【推荐有奖】参加。详情可咨询助教老师";
        // phone_number 支持以逗号隔开的字符串
        $data = [
            'sign_name' => $sign,
            'phone_number' => implode(',', $mobile),
            'content' => $msg,
        ];
        return self::sendSMS($data);
    }
}

<?php
/**
 * 语音短信
 */
namespace App\Libs;

use GuzzleHttp\Client;

class voiceSMS
{
    const API_SEND = '/open/notify/voice-notify';
    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    private function sendSMS($data)
    {
        list($voiceAPPId, $voiceAppKey) = DictConstants::get(DictConstants::VOICE_SMS_CONFIG, ['voice_app_id', 'voice_app_key']);
        $client = new Client();

        $response = $client->request('POST', $this->url . self::API_SEND, [
            'debug' => false,
            'form_params' => [
                'appId' => $voiceAPPId,
                'appKey' => $voiceAppKey,
                'templateId' => $data['templateId'],
                'mobile' => $data['mobile']
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
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['code' => ' send voice sms', 'data' => $data]);
            return false;
        }
    }



    /**
     * 体验课购买成功语音短信
     * @param $mobile
     * @return bool
     */
    public function sendPurchaseExperienceClassSMS($mobile)
    {
        $voiceTemplateId = DictConstants::get(DictConstants::VOICE_SMS_CONFIG,'voice_purchase_experience_class_template_id');
        $data = [
            'mobile' => $mobile,
            'templateId' => $voiceTemplateId,
        ];
        return self::sendSMS($data);
    }

}

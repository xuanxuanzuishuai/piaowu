<?php

namespace App\Libs\SmsCenter;


use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Services\CommonServiceForApp;

class SmsCenter
{
    const API_SINGLE_SEND_SMS = '/api/v2/send/sms';   //【国内】发送单条短信
    const API_BATCH_SEND_SMS = '/api/v2/batch/send/sms';   //【国内】批量发送短信
    const API_SEND_I18N_SMS = '/api/v2/send/i18n/sms';  //【国际】发送国际短信
	const API_BATCH_SEND_I18N_SMS = '/api/v2/batch/send/i18n/sms';//【国际】批量发送短信

	const API_ADD_TEMPLATE = '/api/v2/template/add';  //创建短信模板
    const API_GET_SMS_TEMPLATE = '/api/v2/templates';  //获取短信模版

    const DEFAULT_COUNTRY_CODE = '86';
    const BATCH_SEND_LIMIT = 200; //批量发送短信条数上限
    const OP_APP_ID = 19;  //运营系统app_id
    const SYSTEM_IDENTIFY = 'op_system';  //op系统标识

    protected $host;
    protected $tid;
    protected $sender;
    protected $signName;
    protected $extra;

    public function __construct()
    {
        $this->host = $_ENV['SMS_HOST'];
    }

    /**
     * 发送短信设置变量
     * @param int $tid 模版id
     * @param string $sender 发送人
     * @param string $signName 签名
     * @param array $extra 业务自定义数据结构
     * @return $this
     */
    public function setBaseParams($tid, $sender = '', $signName = '', $extra = [])
    {
        $this->tid = $tid;
        $this->sender = $sender ?: self::SYSTEM_IDENTIFY;
        $this->signName = $signName ?: CommonServiceForApp::SIGN_STUDENT_APP;
        $this->extra = $extra;
        return $this;
    }


    /**
     * 发送请求
     * @param $api
     * @param $params
     * @param string $method
     * @param array $header
     * @return array|false|mixed
     */
    private function commonApi($api, $params, $method = 'POST', $header = [])
    {
        $url = $this->host . $api;
        $res = HttpHelper::requestJson($url, $params, $method, $header);
        if (empty($res['meta']) || !empty($res['meta']['code'])) {
            SimpleLogger::error('sms_center_send_sms_error', ['result' => $res]);
            return false;
        }
        return $res['data'];
    }

    /**
     * 发送单条短信
     * 国内：http://yapi.xiaoyezi.com/project/656/interface/api/20019
     * 国际：http://yapi.xiaoyezi.com/project/656/interface/api/20047
     * @param string $mobile 手机号
     * @param array $params 模版参数。如$params = [1,9], 1对应模版中第一个参数，9对应第二个参数
     * @param string $countryCode 国家编码
     * @return bool
     */
    public function singleSendSms($mobile, $params = [], $countryCode = self::DEFAULT_COUNTRY_CODE)
    {
        $data = [
            'mobile' => (string)$mobile,
            'params' => $params
        ];
        $api = self::API_SINGLE_SEND_SMS;
        if (!empty($countryCode) && $countryCode != self::DEFAULT_COUNTRY_CODE) {
            $api = self::API_SEND_I18N_SMS; //国际短信
            $data['cc'] = (string)$countryCode;
        }

        return $this->send($api, $data);
    }

    /**
     * 批量发送短信：国内
     * http://yapi.xiaoyezi.com/project/656/interface/api/20026
     * @param $items array 批量发送参数和手机号 [["params" => ['1','2'], "mobile" => "xxx"]]
     * @return bool
     */
    public function batchSendSms($items)
    {
        $api = self::API_BATCH_SEND_SMS;
        $items = array_chunk($items, self::BATCH_SEND_LIMIT);
        foreach ($items as $item) {
            $data['item'] = $item;
            $this->send($api, $data);
        }
        return true;
    }


	/**
	 * 批量发送短信:国际
	 * http://yapi.xiaoyezi.com/project/656/interface/api/23736
	 * @param $items array 批量发送参数和手机号
	 * @return bool
	 */
	public function batchSendI18nSms(array $items): bool
	{
		$api = self::API_BATCH_SEND_I18N_SMS;
		$items = array_chunk($items, self::BATCH_SEND_LIMIT);
		foreach ($items as $item) {
			$data['item'] = $item;
			$this->send($api, $data);
		}
		return true;
	}

    /**
     * 发送短信
     * @param $api
     * @param array $data
     * @return bool
     */
    private function send($api, $data = [])
    {
        if (empty($this->tid)) {
            SimpleLogger::error('template_id_is_empty', []);
            return false;
        }
        $requestData['meta'] = [
            'sender' => (string)$this->sender,
            'extra'  => $this->extra
        ];
        $requestData['data'] = array_merge([
            'tid'       => (int)$this->tid,
            'sign_name' => (string)$this->signName
        ], $data);
        return $this->commonApi($api, $requestData) !== false;
    }

    /**
     * 获取短信模版
     * @return array|mixed
     */
    public function getTemplate()
    {
        //注册模板时的appid
        $params['appid'] = self::OP_APP_ID;
        $result = $this->commonApi(self::API_GET_SMS_TEMPLATE, $params, 'GET');
        return $result['templates'] ?? [];
    }
}

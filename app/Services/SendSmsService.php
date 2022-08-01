<?php

namespace App\Services;

use App\Libs\DictConstants;

use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\SmsCenter\SmsCenter;
use App\Libs\SmsCenter\SmsInc;
use App\Libs\Util;
use App\Models\EmployeeModel;

class SendSmsService
{
    /**
     * 获取短信中心模版id配置
     * @param $keyCode
     * @return array|string
     */
    public static function getTemplateIdConfig($keyCode)
    {
        $type = SmsInc::SMS_CENTER_TEMPLATE_ID_CONFIG;
        $templateId = DictService::getKeyValue($type, $keyCode);
        return $templateId ?? '';
    }


    /**
     * 根据模版id获取模版信息
     * @param $templateId
     * @return array|mixed
     */
    public static function getTemplateInfoById($templateId)
    {
        $cacheKey = SmsInc::SMS_CENTER_TEMPLATE_CACHE;
        $redis = RedisDB::getConn();
        // 获取短信模版, 如果不存在则去短信中心取全量模版数据存入redis
        $template = $redis->hget($cacheKey, $templateId);
        if (!empty($template)) {
            return json_decode($template, JSON_UNESCAPED_UNICODE);
        }

        //获取全量权限数据
        $templateList = (new SmsCenter())->getTemplate();
        if (!empty($templateList)) {
            $templateList = array_column($templateList, null, 'id');
            $cacheData = array_map(function ($v) {
                return json_encode($v, JSON_UNESCAPED_UNICODE);
            }, $templateList);
            $redis->hmset($cacheKey, $cacheData);
            $redis->expire($cacheKey, 3 * Util::TIMESTAMP_ONEDAY);
        }
        return $templateList[$templateId] ?? [];
    }

    /**
     * 根据key_code获取模版内容
     * @param $keyCode
     * @return array|mixed
     */
    public static function getTemplateInfoByKeyCode($keyCode)
    {
        $templateId = self::getTemplateIdConfig($keyCode);
        return self::getTemplateInfoById($templateId);
    }

    /**
     * 替换短信内容中的变量
     * @param $content
     * @param $valueArr
     * @return string
     */
    public static function valueReplaceVar($content, $valueArr)
    {
        $result = "";
        $content = preg_replace('/{.*?}/', '{$var}', $content);
        $count = substr_count($content, '{$var}');
        //如果短信内容中无变量则不进行变量替换
        if (!$count) {
            return $content;
        } elseif ($count != count($valueArr)) {
            //判断变量数与数组中值的数量是否一致，不一致则短信内容无效
            SimpleLogger::error(__FILE__ . ":" . __LINE__, [
                'code' => 'sms content variable not match value',
                [
                    'content' => $content,
                    'value'   => $valueArr
                ]
            ]);
            return $result;
        }

        $content = explode('{$var}', $content);
        foreach ($content as $key => $value) {
            $result .= $value . ($valueArr[$key] ?? '');
        }
        return $result;
    }

    /**
     * 单条/批量发送短信
     * items参数：type = 1（单条）$items = ['mobile' => 'xxx', 'params' => [1,2,3]]; params为模版中的变量
     *           type = 2（批量相同参数）$items = ['mobile' => ['xxx', 'xxx'], 'params' => [1,2,3]]
     *           type = 3（批量不同参数）$items = [['mobile' => 'xxx', 'params' => [1,2,3]], ['mobile' => 'xxx', 'params' => [1,2,3]]]
     * type = 1时，可传country_code区分发国内/国际短信
     * @param $type
     * @param string $keyCode dict中对应的模版配置key_code
     * @param int $templateId 模版id
     * @param array $items 发送短信数据
     * @param string $countryCode 国家代码
     * @param int $senderId 发送人id
     * @param string $signName 短信签名
     * @param array $extra 其他业务自定义参数
     * @return bool
     */
    public static function sendSms(
        $type,
        $keyCode = '',
        $templateId = 0,
        $items = [],
        $countryCode = '',
        $senderId = 0,
        $signName = '',
        $extra = []
    ) {
        SimpleLogger::info('send_sms_start', func_get_args());
        //keycode和templateId不能同时为空
        if (empty($keyCode) && empty($templateId)) {
            SimpleLogger::error('cannot_get_template_id', []);
            return false;
        }
        if (empty($items)) {
            SimpleLogger::error('send_sms_params_error', []);
            return false;
        }
        //获取模版id
        $tid = empty($templateId) ? self::getTemplateIdConfig($keyCode) : $templateId;
        //发送人
        $sender = $senderId ? EmployeeModel::getUuidById($senderId) : '';
        //设置参数
        $smsCenter = (new SmsCenter())->setBaseParams($tid, $sender, $signName, $extra);

        switch ($type) {
            case SmsInc::SEND_TYPE_SINGLE:  //发送单条短信
                return $smsCenter->singleSendSms($items['mobile'], $items['params'], $countryCode);
            case SmsInc::SEND_TYPE_BATCH_SAME:  //国内：批量给学员发相同的短信
                $sendData = [];
                foreach ($items['mobile'] as $mobile) {
                    $sendData[] = [
                        'params' => $items['params'],
                        'mobile' => $mobile
                    ];
                }
                return $smsCenter->batchSendSms($sendData);
            case SmsInc::SEND_TYPE_BATCH_DIFF:  //批量给学员发送不同的短信
                return $smsCenter->batchSendSms($items);
			case SmsInc::SEND_TYPE_BATCH_SAME_INTERNATIONAL://国际：批量给学员发相同的短信
				$sendData = [];
				foreach ($items['mobile_list'] as $mv) {
					$sendData[] = [
						'params' => $items['params'],
						'mobile' => $mv['mobile'],
						'cc' => $mv['country_code'],
					];
				}
				return $smsCenter->batchSendI18nSms($sendData);

		}
        return false;
    }


    /*************************************************************************************
     *          业务使用的发送方法
     *************************************************************************************/

    /**
     * 发送验证码
     * @param $targetMobile
     * @param $msg
     * @param string $countryCode
     * @param string $signName
     * @return bool
     */
    public static function sendValidateCode(
        $targetMobile,
        $msg,
        $countryCode = NewSMS::DEFAULT_COUNTRY_CODE,
        $signName = ''
    ) {
        $keyCode = SmsInc::VALIDATE_CODE;
        $template = self::getTemplateInfoByKeyCode($keyCode);
        $items = ['mobile' => $targetMobile, 'params' => [$msg]];
        if (empty($template)) {
            return false;
        }
        self::sendSms(
            SmsInc::SEND_TYPE_SINGLE,
            $keyCode,
            $template['id'],
            $items,
            $countryCode,
            $signName
        );
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'mobile' => $targetMobile]);
        return true;
    }

    /**
     * 清晨分配班级短信
     * @param $targetMobile
     * @param $msg
     * @param string $countryCode
     * @return bool
     */
    public static function sendQcDistributeClass($targetMobile, $msg, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        $keyCode = SmsInc::QC_DISTRIBUTE_CLASS;
        $template = self::getTemplateInfoByKeyCode($keyCode);
        $items = ['mobile' => $targetMobile, 'params' => [$msg]];
        if (empty($template)) {
            return false;
        }
        self::sendSms(
            SmsInc::SEND_TYPE_SINGLE,
            $keyCode,
            $template['id'],
            $items,
            $countryCode
        );
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'mobile' => $targetMobile]);
        return true;
    }

    /**
     * 发送红包结果短信
     * @param $targetMobile
     * @param $msgArr
     * @param string $countryCode
     * @param bool $sendResult
     * @return bool
     */
    public static function sendSmartSendRedpackageResult(
        $targetMobile,
        $msgArr,
        $countryCode = NewSMS::DEFAULT_COUNTRY_CODE,
        $sendResult = true
    ) {
        if ($sendResult) {
            $keyCode = SmsInc::SMART_SEND_REDPACKAGE_SUCCESS;   //发送红包成功短信
        } else {
            $keyCode = SmsInc::SMART_SEND_REDPACKAGE_FAIL;      //发送红包失败短信
        }
        $template = self::getTemplateInfoByKeyCode($keyCode);
        $items = ['mobile' => $targetMobile, 'params' => $msgArr];
        if (empty($template)) {
            return false;
        }
        self::sendSms(
            SmsInc::SEND_TYPE_SINGLE,
            $keyCode,
            $template['id'],
            $items,
            $countryCode
        );
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'mobile' => $targetMobile]);
        return true;
    }

    /**
     * 年卡召回页面按钮点击短信
     * @param $targetMobile
     * @param $msgArr
     * @param string $countryCode
     * @param string $signName
     * @return bool
     */
    public static function sendSmartPayRecall(
        $targetMobile,
        $msgArr,
        $countryCode = SmsCenter::DEFAULT_COUNTRY_CODE,
        $signName = ''
    ) {
        $keyCode = SmsInc::SMART_PAY_RECALL;
        $template = self::getTemplateInfoByKeyCode($keyCode);
        $items = ['mobile' => $targetMobile, 'params' => $msgArr];
        if (empty($template)) {
            return false;
        }
        self::sendSms(
            SmsInc::SEND_TYPE_SINGLE,
            $keyCode,
            $template['id'],
            $items,
            $countryCode,
            $signName
        );
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'mobile' => $targetMobile]);
        return true;
    }

    /**
     * 发送参加活动的提醒
     * @param $targetMobiles
     * @param $msg
     * @param string $countryCode
     * @return bool
     */
    public static function sendOpJoinActivity($targetMobiles, $msg, $countryCode = SmsCenter::DEFAULT_COUNTRY_CODE)
    {
        $keyCode = SmsInc::OP_JOIN_ACTIVITY;
        $template = self::getTemplateInfoByKeyCode($keyCode);
        $items = ['mobile' => $targetMobiles, 'params' => [$msg]];
        if (empty($template)) {
            return false;
        }
        self::sendSms(
            SmsInc::SEND_TYPE_BATCH_SAME,
            $keyCode,
            $template['id'],
            $items,
            $countryCode
        );
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'mobile' => $targetMobiles]);
        return true;
    }

	/**
	 * @param $chinaMobile
	 * @param $overseas
	 * @return bool
	 * @throws RunTimeException
	 */
    public static function sendExchangeResult($chinaMobile, $overseas): bool
	{
		//获取短信模板ID
		$keyCode = SmsInc::OP_EXCHANGE_RESULT;
        $template = self::getTemplateInfoByKeyCode($keyCode);
        if (empty($template)) {
            return false;
        }
		//获取短信链接短地址
		$linkUrl = ((new Dss())->getShortUrl(DictConstants::get(DictConstants::EXCHANGE_CONFIG, 'confirm_exchange_url')))['data']['short_url'];
        //国内
		if (!empty($chinaMobile)) {
			$items = ['mobile' => $chinaMobile, 'params' => [$linkUrl]];
			self::sendSms(
				SmsInc::SEND_TYPE_BATCH_SAME,
				$keyCode,
				$template['id'],
				$items
			);
		}
		//国际
		if (!empty($overseas)) {
			$items = ['mobile_list' => $overseas, 'params' => [$linkUrl]];
			self::sendSms(
				SmsInc::SEND_TYPE_BATCH_SAME_INTERNATIONAL,
				$keyCode,
				$template['id'],
				$items
			);
		}
        $content = self::valueReplaceVar($template['content'], $items['params']);
        SimpleLogger::info($keyCode, ['content' => $content, 'china_mobile' => $chinaMobile,'international_mobile' => $overseas]);
        return true;
    }
}
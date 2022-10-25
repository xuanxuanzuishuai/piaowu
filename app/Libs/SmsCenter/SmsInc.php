<?php

namespace App\Libs\SmsCenter;


class SmsInc
{

    const SEND_TYPE_SINGLE = 1;  //发送单条
    const SEND_TYPE_BATCH_SAME = 2;  //国内：批量发送，模版变量一致
    const SEND_TYPE_BATCH_DIFF = 3;  //批量发送，模版变量不一致
	const SEND_TYPE_BATCH_SAME_INTERNATIONAL = 4;//国际：批量发送，模版变量一致

    const SMS_CENTER_TEMPLATE_CACHE = 'sms_center_template_cache';  //短信中心模版缓存


    //短信中心模版ID配置
    const SMS_CENTER_TEMPLATE_ID_CONFIG = 'template_id_config';
    const VALIDATE_CODE = 'validate_code';                                  //验证码模板
    const QC_DISTRIBUTE_CLASS = 'qc_distribute_class';                      //清晨分配班级模版ID
    const SMART_SEND_REDPACKAGE_SUCCESS = 'smart_send_redpackage_success';  //智能周周领奖白名单发送红包成功模板ID
    const SMART_SEND_REDPACKAGE_FAIL = 'smart_send_redpackage_fail';        //智能周周领奖白名单发送红包失败模板ID
    const SMART_PAY_RECALL = 'smart_pay_recall';                            //年卡召回页面按钮点击短信模板ID
    const OP_JOIN_ACTIVITY = 'op_join_activity';                            //参加活动的提醒短信模板ID
    const OP_EXCHANGE_RESULT = 'op_exchange_result';                        //兑课导入结果通知短信
    const DOU_REPEAT_BUY = 'dou_repeat_buy';                                // 抖店重复购买体验课短息模板id
    const QC_LANDING_ADDRESS = 'qc_landing_address';                        // 清晨landing页收货地址填写提醒
}
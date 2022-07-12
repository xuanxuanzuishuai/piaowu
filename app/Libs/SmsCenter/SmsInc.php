<?php

namespace App\Libs\SmsCenter;


class SmsInc
{

    const SEND_TYPE_SINGLE = 1;  //发送单条
    const SEND_TYPE_BATCH_SAME = 2;  //批量发送，模版变量一致
    const SEND_TYPE_BATCH_DIFF = 3;  //批量发送，模版变量不一致

    const SMS_CENTER_TEMPLATE_CACHE = 'sms_center_template_cache';  //短信中心模版缓存


    //短信中心模版ID配置
    const SMS_CENTER_TEMPLATE_ID_CONFIG = 'template_id_config';
    const VALIDATE_CODE = 'validate_code';                                  //验证码模板
    const QC_DISTRIBUTE_CLASS = 'qc_distribute_class';                      //清晨分配班级模版ID
    const SMART_SEND_REDPACKAGE_SUCCESS = 'smart_send_redpackage_success';  //智能周周领奖白名单发送红包成功模板ID
    const SMART_SEND_REDPACKAGE_FAIL = 'smart_send_redpackage_fail';        //智能周周领奖白名单发送红包失败模板ID
    const SMART_PAY_RECALL = 'smart_pay_recall';                            //年卡召回页面按钮点击短信模板ID
    const OP_JOIN_ACTIVITY = 'op_join_activity';                            //参加活动的提醒短信模板ID
}
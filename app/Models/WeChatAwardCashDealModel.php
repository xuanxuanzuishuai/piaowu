<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

class WeChatAwardCashDealModel extends Model
{
    public static $table = "wechat_award_cash_deal";

    //红包祝福语相关配置
    const NORMAL_PIC_WORD      = 1;
    const COMMUNITY_PIC_WORD   = 2;
    const TERM_SPRINT_PIC_WORD = 3;
    const REFERRER_PIC_WORD    = 4;
    const REISSUE_PIC_WORD     = 5;

    //发送红包成功与否的标识
    const RESULT_FAIL_CODE = 'FAIL'; //发放红包失败
    const RESULT_SUCCESS_CODE = 'SUCCESS'; //发送红包成功

    //查询红包发放结果的标识
    const SENDING = 'SENDING'; //发放中
    const SENT = 'SENT'; //已发放待领取
    const FAILED = 'FAILED'; //发放失败
    const RECEIVED = 'RECEIVED'; //已领取
    const RFUND_ING = 'RFUND_ING'; //退款中
    const REFUND = 'REFUND'; //已退款

    //红包相关微信错误码
    const NOT_SUBSCRIBE_WE_CHAT = 'NOT_SUBSCRIBE_WE_CHAT';
    const NOT_BIND_WE_CHAT = 'NOT_BIND_WX';
    const NO_AUTH = 'NO_AUTH';
    const SENDNUM_LIMIT = 'SENDNUM_LIMIT';
    const ILLEGAL_APPID	= 'ILLEGAL_APPID';
    const MONEY_LIMIT = 'MONEY_LIMIT';
    const SEND_FAILED = 'SEND_FAILED';
    const FATAL_ERROR = 'FATAL_ERROR';
    const CA_ERROR = 'CA_ERROR';
    const SIGN_ERROR = 'SIGN_ERROR';
    const SYSTEMERROR = 'SYSTEMERROR';
    const XML_ERROR = 'XML_ERROR';
    const FREQ_LIMIT = 'FREQ_LIMIT';
    const API_METHOD_CLOSED = 'API_METHOD_CLOSED';
    const NOTENOUGH = 'NOTENOUGH';
    const OPENID_ERROR = 'OPENID_ERROR';
    const MSGAPPID_ERROR = 'MSGAPPID_ERROR';
    const ACCEPTMODE_ERROR = 'ACCEPTMODE_ERROR';
    const PROCESSING = 'PROCESSING';
    const PARAM_ERROR = 'PARAM_ERROR';
    const SENDAMOUNT_LIMIT = 'SENDAMOUNT_LIMIT';
    const RCVDAMOUNT_LIMIT = 'RCVDAMOUNT_LIMIT';
    const NOT_FOUND = 'NOT_FOUND';

    public static function getWeChatErrorMsg($errCode)
    {
        switch ($errCode) {
            case self::NOT_SUBSCRIBE_WE_CHAT:
                return '未关注服务号';
                break;
            case self::NOT_BIND_WE_CHAT:
                return '未绑定微信';
                break;
            case self::NO_AUTH:
                return '发放失败，此请求可能存在风险，已被微信拦截';
                break;
            case self::SENDNUM_LIMIT:
                return '超过个人单日领取红包数上限';
                break;
            case self::ILLEGAL_APPID:
                return '非法appid，请确认是否为公众号的appid，不能为APP的appid';
                break;
            case self::MONEY_LIMIT:
                return '发送红包金额不在限制范围内';
                break;
            case self::SEND_FAILED:
                return '红包发放失败,请更换单号再重试';
                break;
            case self::FATAL_ERROR:
                return 'openid和原始单参数不一致';
                break;
            case self::CA_ERROR:
                return '商户API证书校验出错';
                break;
            case self::SIGN_ERROR:
                return '签名错误';
                break;
            case self::SYSTEMERROR:
                return '请求已受理，系统无返回明确发放结果';
                break;
            case self::XML_ERROR:
                return '输入xml参数格式错误';
                break;
            case self::FREQ_LIMIT:
                return '超过频率限制,请稍后再试';
                break;
            case self::API_METHOD_CLOSED:
                return '你的商户号API发放方式已关闭，请联系管理员在商户平台开启';
                break;
            case self::NOTENOUGH:
                return '帐号余额不足，请到商户平台充值后再重试';
                break;
            case self::OPENID_ERROR:
                return 'openid和appid不匹配';
                break;
            case self::MSGAPPID_ERROR:
                return '触达消息给用户appid有误';
                break;
            case self::ACCEPTMODE_ERROR:
                return '主、子商户号关系校验失败';
                break;
            case self::PROCESSING:
                return '请求已受理，请稍后使用原单号查询发放结果';
                break;
            case self::PARAM_ERROR:
                return '参数错误';
                break;
            case self::SENDAMOUNT_LIMIT:
                return '超过账户单日发放金额上限';
                break;
            case self::RCVDAMOUNT_LIMIT:
                return '超过个人单日领取金额上限';
                break;
            case self::NOT_FOUND:
                return '指定单号数据不存在';
                break;
            case self::SENDING:
                return '发放中';
                break;
            case self::SENT:
                return '已发放待领取';
                break;
            case self::FAILED:
                return '微信红包发放失败';
                break;
            case self::RECEIVED:
                return '';
                break;
            case self::RFUND_ING:
                return '退款中';
                break;
            case self::REFUND:
                return '微信红包未领取';
                break;
            default:
                return '发放失败';
        }
    }

    public static function getWeChatResultCodeMsg($resultCode) {
        $msg = '';
        $isBreak = false;
        switch ($resultCode) {
            case self::RECEIVED:
            case self::RESULT_SUCCESS_CODE:
                $msg = '';
                $isBreak = true;
                break;
        }
        if ($isBreak == false && empty($msg)) {
            $msg = self::getWeChatErrorMsg($resultCode);
        }
        return $msg;
    }

    /**
     * 批量获取多个错误码
     * @param string $resultCodes
     * @return array
     */
    public static function batchGetWeChatErrorMsg(string $resultCodes)
    {
        if (empty($resultCodes)) {
            return [];
        }
        $resultCodeArr = explode(',', $resultCodes);
        foreach ($resultCodeArr as $_code) {
            $_codeZh[] = self::getWeChatErrorMsg($_code);
        }
        return !empty($_codeZh) ? $_codeZh : [];
    }
}
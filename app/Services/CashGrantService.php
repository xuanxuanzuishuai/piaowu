<?php

/*
 * Author: yuxuan
 * */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChatPackage;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeChatOpenIdListModel;


class CashGrantService
{
    /**
     * @param $uuId
     * @param $awardId
     * @param $amount
     * @param $reviewerId
     * @param $keyCode
     * @return array
     * 给用户发放他获取的奖励红包
     */
    public static function cashGiveOut($uuId, $awardId, $amount, $reviewerId, $keyCode)
    {
        $time = time();
        //当前要发放奖励的这个人微信信息
        $studentInfo = StudentModel::getRecord(['uuid' => $uuId], ['id']);
        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($studentInfo['id'],
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);

        //当前奖励是否已经有发放红包记录
        $hasRedPackRecord = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $awardId]);
        $mchBillNo = self::getMchBillNo($awardId, $hasRedPackRecord, $amount);

        $data['user_event_task_award_id'] = $awardId;
        $data['mch_billno'] = $mchBillNo;

        if (empty($studentWeChatInfo['open_id']) && empty($hasRedPackRecord)) {
            //未绑定微信不发送
            $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
            $resultCode = WeChatAwardCashDealModel::NOT_BIND_WE_CHAT;
        } else {
            //绑定微信且关注公众号
            $hasSubscribeRecord = WeChatOpenIdListModel::getRecord(['openid' => $studentWeChatInfo['open_id'], 'status' => WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT]);
            if (!empty($hasSubscribeRecord)) {
                //如果pre环境需要特定的user_id才可以接收
                if (($_ENV['ENV_NAME'] == 'pre' && in_array($studentWeChatInfo['user_id'], explode(',', $_ENV['ALLOW_SEND_RED_PACK_USER']))) || $_ENV['ENV_NAME'] == 'prod') {
                    //已绑定微信推送红包
                    $weChatPackage = new WeChatPackage();
                    $openId = $studentWeChatInfo['open_id'];
                    list($actName, $sendName, $wishing) = self::getRedPackConfigWord($keyCode);
                    //请求微信发红包
                    $resultData = $weChatPackage->sendPackage($mchBillNo, $actName, $sendName, $openId, $amount, $wishing, 'redPack');
                    SimpleLogger::info('we chat send red pack result data:', $resultData);
                    $status = trim($resultData['result_code']) == WeChatAwardCashDealModel::RESULT_SUCCESS_CODE ? ErpReferralService::AWARD_STATUS_GIVE_ING : ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                    $resultCode = trim($resultData['err_code']);
                } else {
                    $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                    $resultCode = WeChatAwardCashDealModel::RESULT_FAIL_CODE;
                }
            } else {
                //未关注服务号
                $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                $resultCode = WeChatAwardCashDealModel::NOT_SUBSCRIBE_WE_CHAT;
            }

        }
        $data['status'] = $status;
        $data['result_code'] = $resultCode;
        $data['open_id'] = $openId ?? '';
        $data['update_time'] = $time;
        $data['reviewer_id'] = $reviewerId;
        $data['award_amount'] = $amount;
        //处理结果入库
        if (empty($hasRedPackRecord)) {
            $data['user_type'] = WeChatService::USER_TYPE_STUDENT;
            $data['busi_type'] = UserWeixinModel::BUSI_TYPE_STUDENT_SERVER;
            $data['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
            $data['create_time'] = $time;
            $data['user_id'] = $studentInfo['id'];
            //记录发送红包
           $result = WeChatAwardCashDealModel::insertRecord($data);
        } else {
           $result = WeChatAwardCashDealModel::updateRecord($hasRedPackRecord['id'], $data);
        }
        if (empty($result)) {
            SimpleLogger::error('cash deal data operate fail', $data);
        }
        return [$awardId, $status];
    }

    /**
     * @param $awardId
     * @param $hasRedPackRecord
     * @param $amount
     * @return mixed|string
     * 如果已经有微信红包记录，并且错误码为 微信内部/ca错误的 用原有单号重试
     */
    private static function getMchBillNo($awardId, $hasRedPackRecord, $amount)
    {
        if (!empty($hasRedPackRecord) && in_array($hasRedPackRecord['result_code'], [WeChatAwardCashDealModel::CA_ERROR, WeChatAwardCashDealModel::SYSTEMERROR])) {
            return $hasRedPackRecord['mch_billno'];
        } else {
            return $_ENV['ENV_NAME'] . $awardId . $amount . date('Ymd');
        }
    }

    /**
     * @return array
     * @param $keyCode
     * 发送微信红包带的配置语
     */
    private static function getRedPackConfigWord($keyCode)
    {
        $configArr = json_decode(DictConstants::get(DictConstants::WE_CHAT_RED_PACK_CONFIG, self::getKeyCodeRelateKey($keyCode)), true);
        return [$configArr['act_name'], $configArr['send_name'], $configArr['wishing']];
    }

    /**
     * @param $keyCode
     * @return string
     * 红包祝福语相关
     */
    private static function getKeyCodeRelateKey($keyCode)
    {
        $arr = [
            WeChatAwardCashDealModel::COMMUNITY_PIC_WORD   => 'COMMUNITY_PIC_WORD',
            WeChatAwardCashDealModel::NORMAL_PIC_WORD      => 'NORMAL_PIC_WORD',
            WeChatAwardCashDealModel::TERM_SPRINT_PIC_WORD => 'TERM_SPRINT_PIC_WORD',
            WeChatAwardCashDealModel::REFERRER_PIC_WORD    => 'REFERRER_PIC_WORD',
            WeChatAwardCashDealModel::REISSUE_PIC_WORD     => 'REISSUE_PIC_WORD'
        ];
        return $arr[$keyCode] ?? NULL;
    }

    /**
     * 查询待领取的红包在微信平台的发放结果
     */
    public static function updateCashDealStatus()
    {
        $time = time();
        //当前状态为待领取的
        $needQueryData = WeChatAwardCashDealModel::getRecords([
            'status' => ErpReferralService::AWARD_STATUS_GIVE_ING,
            'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            'user_type' => WeChatService::USER_TYPE_STUDENT,
            'busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
        ]);
        $weChatPackage = new WeChatPackage();
        $needUpdateAward = [];
        //调用微信
        if (!empty($needQueryData)) {
            foreach ($needQueryData as $va) {
                $resultData = $weChatPackage->getRedPackBillInfo($va['mch_billno']);
                SimpleLogger::info("wx red pack query", ['mch_billno' => $va['mch_billno'], 'data' => $resultData]);
                $status = $va['status'];
                $resultCode = $va['result_code'];

                //处理微信返回结果 根据微信文档 需要根据微信结果两个字段标识判断 status / result_code
                //请求结果失败
                if ($resultData['result_code'] == WeChatAwardCashDealModel::RESULT_FAIL_CODE) {
                    $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                    $resultCode = $resultData['err_code'];
                } else {
                    //已领取
                    if (in_array($resultData['status'], [WeChatAwardCashDealModel::RECEIVED])) {
                        $status = ErpReferralService::AWARD_STATUS_GIVEN;
                        $resultCode = $resultData['status'];
                    }

                    //发放失败/退款中/已退款
                    if (in_array($resultData['status'], [WeChatAwardCashDealModel::REFUND, WeChatAwardCashDealModel::RFUND_ING, WeChatAwardCashDealModel::FAILED])) {
                        $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                        $resultCode = $resultData['status'];
                    }

                    //发放中/已发放待领取
                    if (in_array($resultData['status'], [WeChatAwardCashDealModel::SENDING, WeChatAwardCashDealModel::SENT])) {
                        $status = ErpReferralService::AWARD_STATUS_GIVE_ING;
                        $resultCode = $resultData['status'];
                    }
                }

                if ($status != $va['status']) {
                    //更新当前记录
                    $updateRow = WeChatAwardCashDealModel::updateRecord($va['id'], ['status' => $status, 'result_code' => $resultCode, 'update_time' => $time]);
                    SimpleLogger::info('we chat award update row', ['affectedRow' => $updateRow]);
                    //红包接收成功发送微信消息
                    if ($status == ErpReferralService::AWARD_STATUS_GIVEN) {
                        MessageService::sendMessage($va['open_id'], DictConstants::get(DictConstants::MESSAGE_RULE, 'receive_red_pack_rule_id'));
                    }
                    $needUpdateAward[$va['user_event_task_award_id']] = ['award_id' => $va['user_event_task_award_id'], 'status' => $status, 'review_time' => $time];
                }
            }
        }
        if (!empty($needUpdateAward)) {
            $erp = new Erp();
            $erp->batchUpdateAward($needUpdateAward);
        }
    }
}
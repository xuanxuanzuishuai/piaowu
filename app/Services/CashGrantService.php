<?php

/*
 * Author: yuxuan
 * */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\WeChatAwardCashDealModel;
use App\Libs\WeChatPackage;


class CashGrantService
{
    /**
     * 给用户发放他获取的奖励红包
     * @param $awardId
     * @param $reviewerId
     * @param $keyCode
     * @param string $reason
     * @return array|mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function cashGiveOut($awardId, $reviewerId, $keyCode, $reason = '')
    {
        //前置校验奖励是否可发放
        $res = self::checkAwardCanSend($awardId);
        if (empty($res)) {
            return [];
        }
        $data = [];
        //处理发放相关
        list($awardId, $sendStatus) = self::tryToSendRedPack($awardId, $reviewerId, $keyCode);
        //结果有变化，通知erp
        $awardInfo = ErpUserEventTaskAwardModel::getById($awardId);
        if ($awardInfo['status'] != $sendStatus) {
            $data = (new Erp())->updateAward($awardId, $sendStatus, $reviewerId, $reason);
        }
        return $data;
    }

    /**
     * 基础奖励校验通过后才可以调用此发放方法
     * @param $awardId
     * @param $reviewerId
     * @param $keyCode
     * @return array
     */
    private static function tryToSendRedPack($awardId, $reviewerId, $keyCode)
    {
        //当前奖励的用户是否可正常接收奖励
        list($ifCan, $status, $resultCode, $userWxInfo) = self::checkUserAwardCanSend($awardId);
        $awardBaseInfo = ErpUserEventTaskAwardModel::getById($awardId);
        //当前奖励的交易号
        $hasRedPackRecord = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $awardId]);

        $mchBillNo = self::getMchBillNo($awardId, $hasRedPackRecord, $awardBaseInfo['award_amount']);

        $time = time();

        $data['user_event_task_award_id'] = $awardId;
        $data['mch_billno'] = $mchBillNo;

        //绑定微信且关注公众号
        if ($ifCan) {
            //如果pre环境需要特定的user_id才可以接收
            if (($_ENV['ENV_NAME'] == 'pre' && in_array($userWxInfo['user_id'], explode(',', $_ENV['ALLOW_SEND_RED_PACK_USER']))) || $_ENV['ENV_NAME'] == 'prod') {
                //已绑定微信推送红包
                $weChatPackage = new WeChatPackage($userWxInfo['app_id'], $userWxInfo['busi_type']);
                $openId = $userWxInfo['open_id'];
                list($actName, $sendName, $wishing) = self::getRedPackConfigWord($keyCode);
                //请求微信发红包
                $resultData = $weChatPackage->sendPackage($mchBillNo, $actName, $sendName, $openId, $awardBaseInfo['award_amount'], $wishing, 'redPack');
                SimpleLogger::info('we chat send red pack result data:', $resultData);
                $status = trim($resultData['result_code']) == WeChatAwardCashDealModel::RESULT_SUCCESS_CODE ? ErpUserEventTaskAwardModel::STATUS_GIVE_ING : ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                $resultCode = trim($resultData['err_code']);
            } else {
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                $resultCode = WeChatAwardCashDealModel::RESULT_FAIL_CODE;
            }
        }

        $data['status'] = $status;
        $data['result_code'] = $resultCode;
        $data['open_id'] = $openId ?? '';
        $data['update_time'] = $time;
        $data['reviewer_id'] = $reviewerId;
        $data['award_amount'] = $awardBaseInfo['award_amount'];
        //处理结果入库
        if (empty($hasRedPackRecord)) {
            $data['user_type'] = $userWxInfo['user_type'];
            $data['busi_type'] = $userWxInfo['busi_type'];
            $data['app_id'] = $userWxInfo['app_id'];
            $data['create_time'] = $time;
            $data['user_id'] = $userWxInfo['user_id'];
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
     * 当前奖励是否可发放
     * @param $awardId
     * @return bool
     */
    public static function checkAwardCanSend($awardId)
    {
        $awardInfo = ErpUserEventTaskAwardModel::getById($awardId);
        //仅处理现金
        if ($awardInfo['award_type'] != ErpUserEventTaskAwardModel::AWARD_TYPE_CASH) {
            SimpleLogger::info('only deal cash deal', ['award_info' => $awardInfo]);
            return false;
        }
        //仅处理待发放和发放失败
        if (!in_array($awardInfo['status'], [ErpUserEventTaskAwardModel::STATUS_WAITING, ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL])) {
            SimpleLogger::info('only deal wait or fail', ['award_info' => $awardInfo]);
            return false;
        }
        //仅处理如约发放日期的
        if (($awardInfo['create_time'] + $awardInfo['delay']) > time()) {
            SimpleLogger::info('only deal time is reach', ['award_info' => $awardInfo]);
            return false;
        }
        return true;
    }

    /**
     * 检测当前用户是否可以接收红包
     * @param $awardId
     * @return array|bool[]
     */
    public static function checkUserAwardCanSend($awardId)
    {
        $awardUserInfo = ErpUserEventTaskAwardModel::getAwardUserInfoByAwardInfo($awardId);
        if (empty($awardUserInfo['uuid'])) {
            SimpleLogger::info('not found award user', ['award_id' => $awardId]);
            return [false];
        }
        $ifCan = true;
        $status = NULL;
        $resultCode = NULL;
        $userWxInfo = [];
        if ($awardUserInfo['app_id'] == Constants::SMART_APP_ID) {
            $user = DssStudentModel::getRecord(['uuid' => $awardUserInfo['uuid']], ['id']);
            $userWxInfo = UserService::getUserWeiXinInfoByUserId(Constants::SMART_APP_ID, $user['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
            if (empty($userWxInfo['open_id'])) {
                $ifCan = false;
                //未绑定微信不发送
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                $resultCode = WeChatAwardCashDealModel::NOT_BIND_WE_CHAT;
            }
            $subscribeInfo = DssWechatOpenIdListModel::getRecord(['openid' => $userWxInfo['open_id'], 'status' => DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT, 'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT, 'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER]);
            if (empty($subscribeInfo)) {
                $ifCan = false;
                //未关注服务号
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                $resultCode = WeChatAwardCashDealModel::NOT_SUBSCRIBE_WE_CHAT;
            }
            //取不到微信信息有个默认
            if (empty($ifCan)) {
                $userWxInfo = [
                    'app_id' => Constants::SMART_APP_ID,
                    'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
                    'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
                    'user_id'   => $user['id']
                ];
            }
        }
        return [$ifCan, $status, $resultCode, $userWxInfo];
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
        $configArr = json_decode(DictConstants::get(DictConstants::WE_CHAT_RED_PACK_CONFIG, $keyCode), true);
        return [$configArr['act_name'], $configArr['send_name'], $configArr['wishing']];
    }
}
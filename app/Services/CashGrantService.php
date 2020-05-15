<?php

/*
 * Author: yuxuan
 * */

namespace App\Services;

use App\Libs\Erp;
use App\Libs\UserCenter;
use App\Libs\WeChatPackage;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;


class CashGrantService
{
    /**
     * @param $uuId
     * @param $awardId
     * @param $amount
     * @param $reviewerId
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * 给用户发放他获取的奖励红包
     */
    public static function cashGiveOut($uuId, $awardId, $amount, $reviewerId)
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
        //已经存在记录的，重试发放的时候使用的单号，防止红包发多
        $mchBillNo = !empty($hasRedPackRecord['mch_billno']) ? $hasRedPackRecord['mch_billno'] :$awardId . $amount . time();

        $data['user_event_task_award_id'] = $awardId;
        $data['mch_billno'] = $mchBillNo;

        if (empty($studentWeChatInfo['open_id']) && empty($hasRedPackRecord)) {
            //未绑定微信不发送
            $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
            $resultCode = WeChatAwardCashDealModel::NOT_BIND_WE_CHAT;
        } else {
            //如果pre环境需要特定的user_id才可以接收
            if (($_ENV['ENV_NAME'] == 'pre' && in_array($studentWeChatInfo['user_id'], explode(',', $_ENV['ALLOW_SEND_RED_PACK_USER']))) || $_ENV['ENV_NAME'] == 'prod') {
                //已绑定微信推送红包
                $weChatPackage = new WeChatPackage();
                $actName = WeChatAwardCashDealModel::RED_PACK_ACT_NAME;
                $sendName = WeChatAwardCashDealModel::RED_PACK_SEND_NAME;
                $openId = $studentWeChatInfo['open_id'];
                $wishing = WeChatAwardCashDealModel::RED_PACK_WISHING;
                //请求微信发红包
                $resultData = $weChatPackage->sendPackage($mchBillNo, $actName, $sendName, $openId, $amount, $wishing, 'redPack');
                $status = trim($resultData['result_code']) == WeChatAwardCashDealModel::RESULT_SUCCESS_CODE ? ErpReferralService::AWARD_STATUS_GIVE_ING : ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                $resultCode = trim($resultData['err_code']);
            } else {
                $status = ErpReferralService::AWARD_STATUS_GIVE_FAIL;
                $resultCode = WeChatAwardCashDealModel::RESULT_FAIL_CODE;
            }

        }
        $data['status'] = $status;
        $data['result_code'] = $resultCode;
        //处理结果入库
        if (empty($hasRedPackRecord)) {
            $data['user_type'] = $studentWeChatInfo['user_type'];
            $data['busi_type'] = $studentWeChatInfo['busi_type'];
            $data['app_id'] = $studentWeChatInfo['app_id'];
            $data['open_id'] = $openId;
            $data['award_amount'] = $amount;
            $data['create_time'] = $time;
            $data['update_time'] = $time;
            $data['user_id'] = $studentWeChatInfo['user_id'];
            $data['reviewer_id'] = $reviewerId;
            //记录发送红包
            WeChatAwardCashDealModel::insertRecord($data);
        } else {
            WeChatAwardCashDealModel::updateRecord($hasRedPackRecord['id'], $data);
        }
        return [$awardId, $status];
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
                    WeChatAwardCashDealModel::updateRecord($va['id'], ['status' => $status, 'result_code' => $resultCode, 'update_time' => $time]);
                    $needUpdateAward[$va['user_event_task_award_id']] = ['award_id' => $va['user_event_task_award_id'], 'status' => $status, 'review_time' => $time];
                }
            }
        }
        $erp = new Erp();
        $erp->batchUpdateAward($needUpdateAward);
    }
}
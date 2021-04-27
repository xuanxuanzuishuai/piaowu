<?php

/*
 * Author: yuxuan
 * */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Models\UserPointsExchangeOrderWxModel;
use App\Models\WeChatAwardCashDealModel;
use App\Libs\WeChatPackage;
use App\Services\Queue\RedPackTopic;
use App\Services\Queue\QueueService;

class CashGrantService
{
    /**
     * 红包队列处理
     * @param $awardId
     * @param $eventType
     * @param int $reviewerId
     * @param string $reason
     * @param array $ext
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function redPackQueueDeal($awardId, $eventType, $reviewerId = EmployeeModel::SYSTEM_EMPLOYEE_ID, $reason = '', $ext = [])
    {
        if ($eventType == RedPackTopic::SEND_RED_PACK) {
            self::cashGiveOut($awardId, $reviewerId, $reason, $ext);
        }

        if ($eventType == RedPackTopic::UPDATE_RED_PACK) {
            self::updateCashDealStatus($awardId);
        }
    }

    /**
     * 给用户发放他获取的奖励红包
     * @param $awardId
     * @param $reviewerId
     * @param string $reason
     * @param array $ext
     * @return array|mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function cashGiveOut($awardId, $reviewerId, $reason = '', $ext = [])
    {
        //前置校验奖励是否可发放
        $res = self::checkAwardCanSend($awardId);
        if (empty($res)) {
            return false;
        }
        $awardDetailInfo = ErpUserEventTaskAwardModel::awardRelateEvent($awardId);
        //退费验证
        $verify = self::awardAndRefundVerify($awardDetailInfo);
        if ($verify) {
            $keyCode = self::getAwardKeyWord($awardDetailInfo);
            SimpleLogger::info('red pack key code', ['award_id' => $awardId, 'key code' => $keyCode]);
            list($awardId, $sendStatus) = self::tryToSendRedPack($awardId, $reviewerId, $keyCode);
        } else {
            SimpleLogger::info('refund verify not pass', ['award_id' => $awardId]);
            $sendStatus = ErpUserEventTaskAwardModel::STATUS_DISABLED;
        }

        //结果有变化，通知erp
        if ($awardDetailInfo['status'] != $sendStatus) {
            (new Erp())->updateAward($awardId, $sendStatus, $reviewerId, $reason);
            if ($sendStatus == ErpUserEventTaskAwardModel::STATUS_GIVE_ING) {
                //发放成功推送当前奖励相关的消息
                PushMessageService::sendAwardRelateMessage($awardDetailInfo, $ext);
            }
        }
        return ($sendStatus == ErpUserEventTaskAwardModel::STATUS_GIVE_ING) && ($awardDetailInfo['status'] != $sendStatus);
    }

    /**
     * 红包奖励的退费验证
     * @param $awardDetailInfo
     * @return bool
     */
    public static function awardAndRefundVerify($awardDetailInfo)
    {
        //当前奖励是否需要验证退费
        $needVerify = $awardDetailInfo['type'] == ErpEventModel::TYPE_IS_REFERRAL;
        if (!$needVerify) {
            return true;
        }

        if ($awardDetailInfo['app_id'] == Constants::SMART_APP_ID) {
            $studentInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['uuid']]);
            //完成的体验任务
            if (in_array($awardDetailInfo['event_task_id'], explode(',', DictConstants::get(DictConstants::NODE_RELATE_TASK, '2')))) {
                $giftCodeInfo = DssGiftCodeModel::hadPurchasePackageByType($studentInfo['id']);
            }

            //完成的付费年卡任务
            if (in_array($awardDetailInfo['event_task_id'], explode(',', DictConstants::get(DictConstants::NODE_RELATE_TASK, '3')) )) {
                $giftCodeInfo = DssGiftCodeModel::hadPurchasePackageByType($studentInfo['id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL);
            }

            if (!empty($giftCodeInfo)) {
                return true;
            }
        }
        return false;
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
        //防止op红包发放成功，erp未更新成功的情况，倘若当前奖励在op已经成功，直接返回
        $opAwardInfo = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $awardId]);
        if (!empty($opAwardInfo) && in_array($opAwardInfo['status'], [ErpUserEventTaskAwardModel::STATUS_GIVE, ErpUserEventTaskAwardModel::STATUS_GIVE_ING])) {
            SimpleLogger::info('op award status has change', ['award_info' => $opAwardInfo]);
            return [$awardId, $opAwardInfo['status']];
        }
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
            if (($_ENV['ENV_NAME'] == 'pre' && in_array($userWxInfo['open_id'], explode(',', RedisDB::getConn()->get('red_pack_white_open_id')))) || $_ENV['ENV_NAME'] == 'prod') {
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
                SimpleLogger::info('now env not satisfy', ['award_id' => $awardId]);
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
     * @throws \Exception
     */
    public static function checkAwardCanSend($awardId)
    {
        $awardInfo = ErpUserEventTaskAwardModel::getById($awardId);
        if (empty($awardInfo)) {
            SimpleLogger::info('not found award', ['award_id' => $awardId]);
            //重新入队
            QueueService::sendRedPack([['id' => $awardId]]);
            return false;
        }
        //仅处理现金
        if ($awardInfo['award_type'] != ErpUserEventTaskAwardModel::AWARD_TYPE_CASH) {
            SimpleLogger::info('only deal cash deal', ['award_info' => $awardInfo]);
            return false;
        }
        //金额要大于0
        if ($awardInfo['award_amount'] <= 0) {
            SimpleLogger::info('award amount not enough', ['award_info' => $awardInfo]);
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
            if (!empty($userWxInfo['open_id'])) {
                $subscribeInfo = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER)->getUserInfo($userWxInfo['open_id']);
                if (empty($subscribeInfo['subscribe'])) {
                    $ifCan = false;
                    //未关注服务号
                    $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                    $resultCode = WeChatAwardCashDealModel::NOT_SUBSCRIBE_WE_CHAT;
                }
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
     * @param $awardDetailInfo
     * @return string
     * 转介绍的祝福语keycode
     */
    public static function getAwardKeyWord($awardDetailInfo)
    {
        //完成任务的人，和奖励的人是同一个人的时候特殊处理
        if ($awardDetailInfo['uuid'] == $awardDetailInfo['get_award_uuid']) {
            return 'REFERRER_PIC_WORD';
        }

        $arr = [
            ErpEventModel::TYPE_IS_DURATION_POSTER => 'COMMUNITY_PIC_WORD',
            ErpEventModel::TYPE_IS_REISSUE_AWARD => 'REISSUE_PIC_WORD',
            ErpEventModel::TYPE_IS_REFERRAL => 'NORMAL_PIC_WORD'
        ];
        return $arr[$awardDetailInfo['type']] ?? 'REFERRER_PIC_WORD';
    }

    /**
     * @return array
     * @param $keyCode
     * 发送微信红包带的配置语
     */
    public static function getRedPackConfigWord($keyCode)
    {
        //奖励的详情
        $configArr = json_decode(DictConstants::get(DictConstants::WE_CHAT_RED_PACK_CONFIG, $keyCode), true);
        return [$configArr['act_name'], $configArr['send_name'], $configArr['wishing']];
    }

    /**
     * 查询待领取的红包在微信平台的发放结果
     * @param $awardId
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function updateCashDealStatus($awardId)
    {
        $awardInfo = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $awardId]);
        //仅处理发放中
        if (!empty($awardInfo) && $awardInfo['status'] != ErpUserEventTaskAwardModel::STATUS_GIVE_ING) {
            //因为某些原因erp没有同步状态成功，在此重试
            SimpleLogger::info('only deal ing award', ['award_id' => $awardId]);
            (new Erp())->updateAward($awardId, $awardInfo['status'], EmployeeModel::SYSTEM_EMPLOYEE_ID, '');
            return;
        }
        $weChatPackage = new WeChatPackage($awardInfo['app_id'], $awardInfo['busi_type']);
        $time = time();
        //调用微信
        $resultData = $weChatPackage->getRedPackBillInfo($awardInfo['mch_billno']);
        SimpleLogger::info("wx red pack query", ['mch_billno' => $awardInfo['mch_billno'], 'data' => $resultData]);
        $status = $awardInfo['status'];
        $resultCode = $awardInfo['result_code'];
        //处理微信返回结果 根据微信文档 需要根据微信结果两个字段标识判断 status / result_code
        //请求结果失败
        if ($resultData['result_code'] == WeChatAwardCashDealModel::RESULT_FAIL_CODE) {
            $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
            $resultCode = $resultData['err_code'];
        } else {
            //已领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::RECEIVED])) {
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE;
                $resultCode = $resultData['status'];
            }

            //发放失败/退款中/已退款
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::REFUND, WeChatAwardCashDealModel::RFUND_ING, WeChatAwardCashDealModel::FAILED])) {
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL;
                $resultCode = $resultData['status'];
            }

            //发放中/已发放待领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::SENDING, WeChatAwardCashDealModel::SENT])) {
                $status = ErpUserEventTaskAwardModel::STATUS_GIVE_ING;
                $resultCode = $resultData['status'];
            }
        }

        if ($status != $awardInfo['status']) {
            //更新当前记录
            $updateRow = WeChatAwardCashDealModel::updateRecord($awardInfo['id'], ['status' => $status, 'result_code' => $resultCode, 'update_time' => $time]);
            SimpleLogger::info('we chat award update row', ['affectedRow' => $updateRow]);
            (new Erp())->updateAward($awardId, $status, EmployeeModel::SYSTEM_EMPLOYEE_ID, '');
            if ($status != ErpUserEventTaskAwardModel::STATUS_GIVE) {
                return;
            }
            //红包接收成功发送微信消息
            QueueService::messageRulePushMessage([['delay_time' => 0, 'rule_id' => DictConstants::get(DictConstants::MESSAGE_RULE, 'receive_red_pack_rule_id'), 'open_id' => $awardInfo['open_id']]]);
        }
    }

    /**
     * 积分兑换红包
     * @param int $userPointsExchangeOrderId 红包兑换的订单Id
     * @param int $recordSn  红包兑换记录的唯一标识
     * @param int $operatorId  操作人
     * @return bool
     */
    public static function pointsExchangeRedPack($userPointsExchangeOrderId, $recordSn, $operatorId)
    {
        // 获取订单详情 - 不存在不发放
        $orderInfo = UserPointsExchangeOrderModel::getRecord(['id' => $userPointsExchangeOrderId]);
        if (empty($orderInfo)) {
            SimpleLogger::info('CashGrantService::pointsExchangeRedPack', ['err' => 'not found award', 'id' => $userPointsExchangeOrderId]);
            return false;
        }
        // 获取发放记录，如果记录存在则只处理发放失败和等待发放的订单
        $recordInfo = UserPointsExchangeOrderWxModel::getRecord(['record_sn' => $recordSn, 'user_points_exchange_order_id' => $userPointsExchangeOrderId]);
        if (empty($recordInfo)) {
            SimpleLogger::info('CashGrantService::pointsExchangeRedPack', ['err' => 'not found record_info', 'id' => $userPointsExchangeOrderId, 'record_sn' => $recordSn]);
            return false;
        }
        if (!in_array($recordInfo['status'], [ UserPointsExchangeOrderWxModel::STATUS_WAITING, UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL])) {
            SimpleLogger::info('CashGrantService::pointsExchangeRedPack', ['err' => 'order_is_not_wait_or_not_fail', 'id' => $userPointsExchangeOrderId, 'order_info' => $orderInfo]);
            return false;
        }
        // 检查数据是否正确，不正确直接作废
        if (!self::checkSendRedPackDataRight($recordInfo)) {
            SimpleLogger::info('CashGrantService::pointsExchangeRedPack', ['err' => 'checkIsCanSendRedPack is false', 'id' => $userPointsExchangeOrderId, 'order_info' => $orderInfo]);
            UserPointsExchangeOrderWxModel::updateStatusDisabled($recordInfo['id'], UserPointsExchangeOrderModel::STATUS_CODE_RED_PACK_DATA_ERROR);
            return false;
        }
        // 检查用户是否可接受 - 绑定微信，关注公众号
        list($statusCode, $userWxInfo) = self::checkUserIsCanAcceptRedPack($orderInfo);
        if (!empty($statusCode)) {
            SimpleLogger::info('CashGrantService::pointsExchangeRedPack', ['err' => 'checkUserIsCanAcceptRedPack is false', 'id' => $userPointsExchangeOrderId, 'order_info' => $orderInfo]);
            UserPointsExchangeOrderWxModel::updateStatusFailed($recordInfo['id']['id'], $statusCode);
            return false;
        }

        $time = time();
        // 获取订单号 - 如果之前已经存在交易号用之前的交易号重试
        $mchBillNo = self::createMchBillNo([$orderInfo['id'], $recordSn['record_sn']], $recordInfo, $orderInfo['order_amounts']);
        $keyCode = 'POINTS_EXCHANGE_RED_PACK_SEND_NAME';

        // 调取微信发红包接口
        $res = self::requestWxSendRedPack($mchBillNo, $userWxInfo, $orderInfo, $keyCode);

        // 保存日志
        $data['status'] = $res['status'];
        $data['result_status'] = $res['status'];
        $data['result_code'] = $res['result_code'];
        $data['open_id'] = $userWxInfo['open_id'] ?? '';
        $data['operator_id'] = $operatorId;
        $data['update_time'] = $time;
        $data['mch_billno'] = $mchBillNo;
        //处理结果入库
        $result = UserPointsExchangeOrderWxModel::updateRecord($recordInfo['id'], $data);
        if (empty($result)) {
            SimpleLogger::error('cash deal data operate fail', $data);
        }

        return true;
    }

    /**
     * 检查需要发放的红包奖励数据是否正确
     * @param $orderInfo
     * @return bool
     */
    public static function checkSendRedPackDataRight($orderInfo)
    {
        //金额要大于100分，小于1块不能发送
        if ($orderInfo['order_amounts'] < 100) {
            SimpleLogger::info('CashGrantService::checkIsCanSendRedPack', ['err' => 'amount not enough', 'order_info' => $orderInfo]);
            return false;
        }

        return true;
    }

    /**
     * 检查用户是否可以接收到红包
     * 用户需要绑定微信、关注智能陪练公众号 等
     * @param $orderInfo
     * @return array
     */
    public static function checkUserIsCanAcceptRedPack($orderInfo)
    {
        $userWxInfo = [
            'app_id' => Constants::SMART_APP_ID,
            'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
            'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
            'user_id'   => $orderInfo['user_id']
        ];
        if ($orderInfo['app_id'] != Constants::SMART_APP_ID) {
            return [UserPointsExchangeOrderModel::STATUS_CODE_ILLEGAL_APPID, $userWxInfo];
        }
        $userWxInfo = UserService::getUserWeiXinInfoByUserId(
            Constants::SMART_APP_ID,
            $orderInfo['user_id'],
            DssUserWeiXinModel::USER_TYPE_STUDENT,
            DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
        );
        if (empty($userWxInfo['open_id'])) {
            return [UserPointsExchangeOrderModel::STATUS_CODE_NOT_BIND_WE_CHAT, $userWxInfo];
        }
        if (!empty($userWxInfo['open_id'])) {
            $subscribeInfo = DssWechatOpenIdListModel::getRecord([
                'openid' => $userWxInfo['open_id'],
                'status' => DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT,
                'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
                'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
            ]);
            if (empty($subscribeInfo)) {
                return [UserPointsExchangeOrderModel::STATUS_CODE_NOT_SUBSCRIBE_WE_CHAT, $userWxInfo];
            }
        }
        return ['', $userWxInfo];
    }

    /**
     * 请求微信发送红包接口给用户发送红包
     * @param array $mchBillNo 订单号
     * @param array $userWxInfo 用户微信信息
     * @param array $orderInfo 订单信息
     * @param string $keyCode 红包文字对应语的dict配置key_code
     * @return array
     */
    public static function requestWxSendRedPack($mchBillNo, $userWxInfo, $orderInfo, $keyCode)
    {
        //如果pre环境需要特定的user_id才可以接收
        if (($_ENV['ENV_NAME'] == 'pre' && in_array($userWxInfo['open_id'], explode(',', RedisDB::getConn()->get('red_pack_white_open_id')))) || $_ENV['ENV_NAME'] == 'prod') {
            //已绑定微信推送红包
            $weChatPackage = new WeChatPackage($userWxInfo['app_id'], $userWxInfo['busi_type']);
            $openId = $userWxInfo['open_id'];
            // 红包对应的文字
            list($actName, $sendName, $wishing) = self::getRedPackConfigWord($keyCode);
            $wishing = str_replace('{{order_amounts}}', $orderInfo['order_amounts']/100, $wishing);
            $sendName = str_replace('{{order_amounts}}', $orderInfo['order_amounts']/100, $sendName);
            $actName = str_replace('{{order_amounts}}', $orderInfo['order_amounts']/100, $actName);

            //请求微信发红包
            $resultData = $weChatPackage->sendPackage($mchBillNo, $actName, $sendName, $openId, $orderInfo['order_amounts'], $wishing, 'redPack');
            SimpleLogger::info('CashGrantService::requestWxSendRedPack we chat send red pack result data:', [
                'mchBillNo' => $mchBillNo,
                'resultData' => $resultData,
                'user' => $userWxInfo,
                'order' => $orderInfo,
                'keyCode' => $keyCode,
            ]);
            $status = trim($resultData['result_code']) == WeChatAwardCashDealModel::RESULT_SUCCESS_CODE ? UserPointsExchangeOrderWxModel::STATUS_GIVE_ING : UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL;
            $resultCode = trim($resultData['err_code']);
        } else {
            SimpleLogger::info('CashGrantService::requestWxSendRedPack now env not satisfy', [
                'mchBillNo' => $mchBillNo,
                'user' => $userWxInfo,
                'order' => $orderInfo,
                'keyCode' => $keyCode,
            ]);
            $status = UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL;
            $resultCode = UserPointsExchangeOrderModel::STATUS_CODE_ENV_SATISFY;
        }
        return [
            'status' => $status,            // 发放结果
            'result_code' => $resultCode,   // 发放结果子状态
        ];
    }

    /**
     * 查询待领取的红包在微信平台的发放结果
     * @param $awardId
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function updatePointsExchangeRedPackStatus($awardId)
    {
        $awardInfo = UserPointsExchangeOrderWxModel::getRecord(['id' => $awardId]);
        //仅处理发放中
        if (!empty($awardInfo) && $awardInfo['result_status'] != UserPointsExchangeOrderWxModel::STATUS_GIVE_ING) {
            SimpleLogger::info('CashGrantService::updatePointsExchangeRedPackStatus', ['info' => 'only deal ing award','award_id' => $awardId]);
            return;
        }

        // 获取订单信息
        $orderInfo = UserPointsExchangeOrderModel::getRecord(['id' => $awardInfo['user_points_exchange_order_id']]);
        if (empty($orderInfo)) {
            SimpleLogger::info('CashGrantService::updatePointsExchangeRedPackStatus', ['info' => 'not_found_order_info','award_id' => $awardId]);
            return;
        }
        $weChatPackage = new WeChatPackage($awardInfo['app_id'], $awardInfo['busi_type']);
        $time = time();
        //调用微信
        $resultData = $weChatPackage->getRedPackBillInfo($awardInfo['mch_billno']);
        SimpleLogger::info("CashGrantService::updatePointsExchangeRedPackStatus", [
            'info' => 'wx red pack query',
            'mch_billno' => $awardInfo['mch_billno'],
            'data' => $resultData,
            'award_order_id' => $awardId
        ]);
        $status = $awardInfo['status'];
        $resultCode = $awardInfo['result_code'];
        //处理微信返回结果 根据微信文档 需要根据微信结果两个字段标识判断 status / result_code
        //请求结果失败
        if ($resultData['result_code'] == WeChatAwardCashDealModel::RESULT_FAIL_CODE) {
            $status = UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL;
            $resultCode = $resultData['err_code'];
        } else {
            //已领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::RECEIVED])) {
                $status = UserPointsExchangeOrderWxModel::STATUS_GIVE;
                $resultCode = $resultData['status'];
            }

            //发放失败/退款中/已退款
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::REFUND, WeChatAwardCashDealModel::RFUND_ING, WeChatAwardCashDealModel::FAILED])) {
                $status = UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL;
                $resultCode = $resultData['status'];
            }

            //发放中/已发放待领取
            if (in_array($resultData['status'], [WeChatAwardCashDealModel::SENDING, WeChatAwardCashDealModel::SENT])) {
                $status = UserPointsExchangeOrderWxModel::STATUS_GIVE_ING;
                $resultCode = $resultData['status'];
            }
        }

        if ($status != $awardInfo['status']) {
            //更新当前记录
            $updateRow = UserPointsExchangeOrderWxModel::updateRecord($awardInfo['id'], ['status' => $status, 'result_status' => $status, 'result_code' => $resultCode, 'update_time' => $time]);
            SimpleLogger::info("CashGrantService::updatePointsExchangeRedPackStatus", [
                'info' => 'we chat award update row',
                'affectedRow' => $updateRow,
                'award_order_id' => $awardId,
            ]);
            if (empty($updateRow)) {
                return;
            }

            // 红包被用户成功领取 - 通知erp
            if ($status == UserPointsExchangeOrderWxModel::STATUS_GIVE) {
                (new Erp())->noticeErpUserGetRedPackStatus($awardInfo['uuid'], $orderInfo['order_id'], $awardInfo['record_sn']);
                //红包接收成功发送微信消息
                // QueueService::messageRulePushMessage([['delay_time' => 0, 'rule_id' => DictConstants::get(DictConstants::MESSAGE_RULE, 'receive_red_pack_rule_id'), 'open_id' => $awardInfo['open_id']]]);
            }
        }
    }

    /**
     * 多个条件生成订单号
     * @param $mchBill
     * @param $hasRedPackRecord
     * @param $amount
     * @return mixed|string
     * 如果已经有微信红包记录，并且错误码为 微信内部/ca错误的 用原有单号重试
     */
    public static function createMchBillNo($mchBill, $hasRedPackRecord, $amount)
    {
        if (is_array($mchBill)) {
            $mchBill = implode("a",array_values($mchBill));
        }
        return self::getMchBillNo($mchBill,$hasRedPackRecord,$amount);
    }
}
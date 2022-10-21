<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/12
 * Time: 11:34
 */

namespace App\Services;


use App\Libs\AdTrack;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\QingChen;
use App\Libs\SimpleLogger;
use App\Libs\SmsCenter\SmsCenter;
use App\Libs\Util;
use App\Models\BillMapModel;
use App\Models\CrawlerOrderModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Services\Queue\QueueService;

class DouService
{
    /**
     * 获取用户UUID
     * @param $msg
     * @param $channelId
     * @return mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function register($msg, $channelId)
    {
        $xyzReceiverMsgDecode = Util::authcode($msg['xyz_receiver_msg'], 'DECODE',
            CrawlerOrderModel::CRAWLER_ORDER_AUTH_KEY);
        $xyzReceiverMsgArr = json_decode($xyzReceiverMsgDecode, true);
        if (empty($xyzReceiverMsgArr)) {
            return '';
        }

        $params = [
            'mobile'     => $xyzReceiverMsgArr['tel'],
            'channel_id' => $channelId
        ];
        switch ($msg['xyz_package']['app_id']) {
            case Constants::SMART_APP_ID:
                $uuid = self::registerSmart($params);
                break;
            case Constants::QC_APP_ID:
                $uuid = self::registerQc($params);
                break;
            default:
                SimpleLogger::info('app_id error', $msg);
                break;
        }
        return $uuid ?? '';
    }

    /**
     * 注册智能用户
     * @param $params
     * @return mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function registerSmart($params)
    {
        //检查用户是否存在
        $studentInfo = DssStudentModel::getRecord(['mobile' => $params['mobile']], ['uuid']);
        if (!empty($studentInfo)) {
            return $studentInfo['uuid'];
        }
        //注册智能用户
        $studentInfo = (new Dss())->studentRegisterBound([
            'mobile'       => $params['mobile'],
            'channel_id'   => $params['channel_id'],
            'country_code' => SmsCenter::DEFAULT_COUNTRY_CODE,
        ]);
        return $studentInfo['uuid'] ?? '';
    }

    /**
     * 注册清晨用户
     * @param $params
     * @return mixed|string
     */
    public static function registerQc($params)
    {
        $studentInfo = (new QingChen())->register([
            'mobile'       => $params['mobile'],
            'channel_id'   => $params['channel_id'],
            'country_code' => SmsCenter::DEFAULT_COUNTRY_CODE,
        ]);
        return $studentInfo['data']['uuid'] ?? '';
    }

    /**
     * 投递注册完成事件
     * @param $msg
     * @param $uuid
     * @param $channelId
     * @return bool
     */
    public static function studentRegistered($msg, $uuid, $channelId)
    {
        $data = [
            'third_party_shop' => $msg['third_party_shop'],
            'shop_id'          => $msg['shop_id'],
            'p_id'             => $msg['p_id'],
            's_ids'            => $msg['s_ids'],
            'xyz_receiver_msg' => $msg['xyz_receiver_msg'],
            'guanyi_product_id' => $msg['guanyi_product_id'],
            'update_time'      => time(),
            'order_metadata'   => [
                'channel_id' => $channelId,
            ],
            'xyz_student'      => [
                'uuid' => $uuid,
            ],
        ];
        QueueService::studentRegistered($data);
        return true;
    }

    /**
     * 记录智能付费渠道
     * @param $params
     * @return bool
     */
    public static function recordPayChannelSmart($params)
    {
        $paramMapInfo = $params['msg_body'];
        $douShopId = $paramMapInfo['dou_shop_id'] ?? 0;
        $uuid = $paramMapInfo['student']['uuid'] ?? '';
        SimpleLogger::info('record_dou_shop_order', ['msg' => 'request_start', 'params' => $params]);
        // 必要参数检测， 不满足不记录
        if (empty($douShopId)) {
            SimpleLogger::info('record_dou_shop_order', ['msg' => 'dou_shop_id_empty']);
            return false;
        }
        // 获取用户是否存在
        $studentInfo = StudentService::getStudentInfo($uuid);
        if (empty($studentInfo)) {
            SimpleLogger::info('record_dou_shop_order',
                ['msg' => 'student_not_found', 'uuid' => $uuid, 'student' => $studentInfo]);
            return false;
        }
        $shopChannel = json_decode(DictConstants::get(DictConstants::DOU_SHOP_CONFIG, 'shop_channel'), true);
        SimpleLogger::info('record_dou_shop_order', ['msg' => 'shop_channel', 'shop_channel' => $shopChannel]);
        // 排除非指定抖店渠道订单
        if (empty($shopChannel[$douShopId])) {
            SimpleLogger::info('record_dou_shop_order', ['msg' => 'dou_shop_id_invalid']);
            return false;
        }
        // 查询订单是否存在不记录 - 订单号
        $billMapInfo = BillMapModel::getRecord(['bill_id' => $paramMapInfo['order_id']], ['id']);
        if (!empty($billMapInfo)) {
            SimpleLogger::info('record_dou_shop_order', ['msg' => 'bill_is_exist', 'bill_map_info' => $billMapInfo]);
            return false;
        }
        // 保存订单信息
        $res = BillMapService::mapDataRecord(['c'          => $shopChannel[$douShopId],
                                              'is_success' => BillMapModel::IS_SUCCESS_YES
        ], $paramMapInfo['order_id'], $studentInfo['id']);
        if (!$res) {
            SimpleLogger::info('record_dou_shop_order', ['msg' => 'save_bill_map_fail']);
            return false;
        }
        // 查询是否已经有体验课订单
        $hadPurchasePackageByType = DssGiftCodeModel::hadPurchasePackageByType($studentInfo['id'],
            DssPackageExtModel::PACKAGE_TYPE_TRIAL, false, ['limit' => 2]);
        if (!empty($hadPurchasePackageByType) && count($hadPurchasePackageByType) >= 2) {
            // 购买体验课超过2次，发送短息
            SendSmsService::sendDouRepeatBuy($studentInfo['id']);
        }
        return true;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/22
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\ParamMapModel;
use App\Services\Queue\QueueService;

class RecallLandingService
{
    const LANDING_PAGE_USER_FIRST_KEY = 'USER_24_HOURS_MARK';

    const EVENT_WEB_PAGE_BUTTON_BUY = 0; // 购买按钮
    const EVENT_WEB_PAGE_BUTTON_PAY = 1; // 支付按钮
    const EVENT_WEB_PAGE_ENTER = 2;  // 进入页面
    const RECALL_PAGE_EVENT_KEY = 'WEB_PAGE_EVENT';

    /**
     * @param $packageId
     * @param $student
     * @param array $params
     * @return array
     * @throws RunTimeException
     * @throws \Exception
     */
    public static function getIndexData($packageId, $student, $params = [])
    {
        if (empty($packageId)) {
            return [];
        }
        $mobile = $params['mobile'] ?? '';
        $eventId = $params['activity_id'] ?? 0;
        $data = [];
        $data['had_trial'] = false;
        $data['package'] = [];
        $data['recent_purchase'] = [];
        $data['first_flag'] = false;
        $data['pkg'] = PayServices::PACKAGE_990;
        $data['deadline'] = false;
        $isAgent = false;
        $package = PackageService::getPackageV1Detail($packageId);
        //判断产品包是否绑定赠品组
        $giftGroup = ErpOrderV1Service::haveBoundGiftGroup($packageId);
        $package['has_gift_group'] = $giftGroup;
        if (empty($package)) {
            return [];
        }

        $sceneData = [];
        if (!empty($params['param_id'])) {
            $sceneData = ReferralActivityService::getParamsInfo($params['param_id']);
        }
        if (!empty($sceneData['user_id']) && $sceneData['type'] == ParamMapModel::TYPE_AGENT) {
            $isAgent = true;
        }
        if ($package['sub_type'] == DssCategoryV1Model::DURATION_TYPE_TRAIL) {
            $res = DssGiftCodeModel::hadPurchasePackageByType($student['id']);
            $data['had_trial'] = !empty($res);
        } elseif (!$isAgent) {
            // 年卡需要判断当前在有效期内
            $key = 'event_deadline' . $eventId;
            $deadLine = DictService::getKeyValue(DictConstants::RECALL_CONFIG['type'], $key);
            if (!empty($deadLine) && time() > $deadLine) {
                $data['deadline'] = true;
            }
            // 发送进入页面短信
            self::webEventMessage(self::EVENT_WEB_PAGE_ENTER, $student['id'], $params);
        }
        $data['package'] = $package;
        if ($package['r_price'] != 990) {
            $data['pkg'] = PayServices::PACKAGE_1;
        }
        // 24小时内是否首次进入页面
        if (!empty($mobile)) {
            $redis = RedisDB::getConn();
            $key = self::LANDING_PAGE_USER_FIRST_KEY . implode('_', [$eventId, $mobile]);
            $data['first_flag'] = $redis->get($key) ? false : true;
            // 微信中第二次带着wx_code访问才算第一次
            if (((Util::isWx() && !empty($params['wx_code'])) || !Util::isWx()) && $data['first_flag']) {
                $redis->setex($key, Util::TIMESTAMP_ONEDAY, time());
            }
        }
        $recent = DictConstants::get(DictConstants::AGENT_WEB_STUDENT_CONFIG, 'broadcast_config');
        $recent = json_decode($recent, true);

        if (!empty($recent)) {
            array_walk($recent, function (&$item) {
                $item['image'] = AliOSS::replaceCdnDomainForDss($item['image']);
            });
        }
        $data['recent_purchase'] = $recent;
        return $data;
    }

    /**
     * @param $event
     * @param $userId
     * @param array $params
     * @return false
     * @throws \Exception
     */
    public static function webEventMessage($event, $userId, $params = [])
    {
        if (empty($userId)) {
            return false;
        }
        $eventId = $params['activity_id'] ?? 0;
        $redis = RedisDB::getConn();
        $config = [
            self::EVENT_WEB_PAGE_BUTTON_BUY => [
                'stage' => '高意向',
                'action' => '年卡活动-点击立即抢购',
                'content' => '立即抢购',
            ],
            self::EVENT_WEB_PAGE_BUTTON_PAY => [
                'stage' => '高意向',
                'action' => '年卡活动-点击立即支付',
                'content' => '立即付款',
            ],
            self::EVENT_WEB_PAGE_ENTER => [
                'stage' => '意向',
                'action' => '年卡活动-进入活动页浏览',
                'content' => '进入活动营销页',
            ],
        ];

        if (empty($config[$event])) {
            SimpleLogger::error('EMPTY BUTTON CONFIG', [$event]);
            return false;
        }
        $smsConfig = $config[$event];

        $student = DssStudentModel::getById($userId);
        if (empty($student['assistant_id'])) {
            SimpleLogger::error('STUDENT ASSISTANT NOT FOUND', [$userId]);
            return false;
        }
        $assistantInfo = DssEmployeeModel::getById($student['assistant_id']);
        if (empty($assistantInfo['mobile'])) {
            SimpleLogger::error('EMPTY ASSISTANT MOBILE', [$assistantInfo]);
            return false;
        }
        $payInfo = DssGiftCodeModel::getUserFirstPayInfo($student['id'], DssCategoryV1Model::DURATION_TYPE_TRAIL);

        $redisKey = self::RECALL_PAGE_EVENT_KEY . $eventId;
        $cacheKey = implode("_", [$eventId, $userId, $event, $assistantInfo['id']]);
        $cache = $redis->hget($redisKey, $cacheKey);
        if (!empty($cache)) {
            return false;
        }
        $redis->hset($redisKey, $cacheKey, time());
        $data = [
            'event_id' => $event,
            'uuid' => $student['uuid'],
            'mobile' => $assistantInfo['mobile'],
            'stage' => $smsConfig['stage'],
            'action' => $smsConfig['action'],
            'sMobile' => $student['mobile'],
            'buyTime' => $payInfo['create_time'] ?? 0,
        ];
        $flag = DictConstants::get(DictConstants::RECALL_CONFIG, 'send_sms_flag');
        if (!empty($flag)) {
            QueueService::sendAssistantSms($data);
        }
        QueueService::sendAssistantSmsBi([
            'uuid' => $data['uuid'],
            'activity_id' => strval($params['activity_id']),
            'content' => $smsConfig['content'] ?? ''
        ]);
        return true;
    }
}
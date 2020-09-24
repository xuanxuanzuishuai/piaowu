<?php

namespace App\Services;


use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AIReferralToPandaUserModel;
use App\Services\Queue\PushMessageTopic;
use App\Libs\SimpleLogger;
use Exception;

class AIReferralToPandaUserService
{
    /**
     * 批量添加用户
     * @param $uuids
     * @param $userType
     * @return array | int
     */
    public static function addRecords($uuids, $userType)
    {
        $existRecords = self::getByUuids($uuids);
        $existUuids = array_column($existRecords, 'uuid');
        $now = time();

        $data = [];
        foreach ($uuids as $uuid) {
            if (!in_array($uuid, $existUuids)) {
                $dataItem = [
                    'uuid' => $uuid,
                    'create_time' => $now,
                    'update_time' => $now,
                    'user_type' => $userType
                ];
                $data[] = $dataItem;
            }
        }

        if (empty($data)) {
            return 0;
        }
        $result = AIReferralToPandaUserModel::batchInsert($data, false);
        if (!$result) {
            return Valid::addErrors([], 'insert_error', 'batch_insert_err');
        }
        return count($data);
    }

    /**
     * 根据uuids查询
     * @param $uuids
     * @return array
     */
    public static function getByUuids($uuids)
    {
        if (empty($uuids)) {
            return [];
        }
        return AIReferralToPandaUserModel::getRecords(['uuid' => $uuids], false);
    }

    /**
     * 发送真人报名模板消息
     * @param $date
     * @return array|null
     */
    public static function sendAiReferralToPandaNotice($date)
    {
        $userType = [AIReferralToPandaUserModel::USER_TYPE_LT4D, AIReferralToPandaUserModel::USER_TYPE_LT8D];
        $studentInfo = AIReferralToPandaUserModel::getToSentStudentInfo($userType, $date);
        if (empty($studentInfo)) {
            return [0, 0];
        }
        $url = $_ENV['PANDA_WECHAT_FRONT_DOMAIN'] . '/#/webSignup?mobile=';
        $templateId = $_ENV["AI_REFERRAL_TO_PANDA_NOTICE_TEMPLATE"];
        $data = [
            'first' => [
                'value' => "家长您好，根据宝贝的练琴数据，已成功为您预约小叶子真人1对1钢琴陪练试听课一节，价值299元，限时免费体验！",
            ],
            'keyword1' => [
                'value' => "智能陪练，让宝贝弹准弹对，扎实练琴硬实力；\n真人陪练，帮宝贝精炼细节，提升练琴软实力。",
            ],
            'keyword2' => [
                'value' => "智能+真人结合使用，全面帮助孩子纠正练琴时出现的问题，效率翻倍。这可能是最适合宝贝的陪练课程，千万别错过！",
            ],
            'keyword3' => [
                'value' => "点击查看详情，即可立即领取噢～",
            ],
        ];
        $msgBody = [
            'wx_push_type' => 'template',
            'template_id' => $templateId,
            'data' => $data,
            'open_id' => '',
        ];

        try {
            $topic = new PushMessageTopic();
        } catch (Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', ['$dateTime' => time(),]);
            return [0, 0];
        }

        $successIds = [];
        $failedIds = [];
        foreach ($studentInfo as $info) {
            $msgBody['open_id'] = $info['open_id'];
            $msgBody['url'] = $url . $info['mobile'];
            try {
                $topic->wxPushCommon($msgBody)->publish(rand(0, 600));
                $successIds[] = $info['id'];
            } catch (Exception $e) {
                SimpleLogger::error("send notice send failure", ['info' => $info]);
                $failedIds[] = $info['id'];
                continue;
            }
        }
        return [$successIds, $failedIds];
    }

    /**
     * 更新发送状态
     * @param $sendStatus
     * @param $ids
     * @return int|null
     */
    public static function modifyStatus($ids, $sendStatus)
    {
        $data = [
            'is_send' => AIReferralToPandaUserModel::USER_IS_SEND,
            'is_subscribe' => AIReferralToPandaUserModel::USER_IS_SUBSCRIBE,
            'send_status' => $sendStatus,
            'update_time' => time()
        ];
        return AIReferralToPandaUserModel::updateRecord($ids, $data, false);
    }
}
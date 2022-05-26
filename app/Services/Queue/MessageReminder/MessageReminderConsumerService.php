<?php

namespace App\Services\Queue\MessageReminder;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\StudentMessageReminderModel;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\StudentServices\MessageReminderService;

class MessageReminderConsumerService
{
    /**
     * 提醒消息消费
     * @param array $paramsData
     * @return bool
     * @throws RunTimeException
     */
    public function messageReminder(array $paramsData): bool
    {
        if (empty($paramsData['msg_body'])) {
            return false;
        }
        switch ($paramsData['msg_body']['message_type']) {
            case StudentMessageReminderModel::MESSAGE_TYPE_GOLD_LEAF_60_EXPIRATION_REMINDER:
                MessageReminderService::addMessageData($paramsData['msg_body']['message_type'],
                    [$paramsData['msg_body']]);
                break;
            default:
                return false;
        }
        return true;
    }


    /**
     * 事件任务发送奖励提醒消息消费：这个模式的消息比较特殊，所以单独处理
     * @param array $paramsData
     * @return bool
     */
    public function eventTaskAwardMessageReminder(array $paramsData): bool
    {
        $formatData = [
            'update' => '',
            'insert' => [],
        ];
        $nowTime = time();
        if (empty($paramsData['msg_body'])) {
            return false;
        }
        //根绝erp数据库中的erp_user_event_task_award_gold_leaf数据表主键ID查询数据
        $awardData = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList(
            [
                'id'     => $paramsData['msg_body']['award_ids'],
                'status' => [
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_REVIEWING,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_ING,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_FAIL,
                ],
            ],
            1,
            count($paramsData['msg_body']['award_ids']),
            false,
            ['id' => 'ASC']
        );
        if (empty($awardData['list'])) {
            SimpleLogger::info("gold leaf event task award list empty", []);
            return false;
        }
        //过滤数据
        $tmpAwardData = [];
        foreach ($awardData['list'] as $avl) {
            //确定提醒消息标题：判断本条奖励是不是推荐人奖励 - 如果是需要拼接被推荐人手机号码
            if ($avl['to'] == ErpEventTaskModel::AWARD_TO_REFERRER) {
                $tmpTitle = $avl['event_name'] . $avl['buyer_student_mobile'] . ' +' . $avl['award_num'];
            } else {
                $tmpTitle = $avl['event_name'] . '+' . $avl['award_num'];
            }
            //确定提醒消息类型
            if ($avl['award_node'] == 'week_award' || $avl['event_name'] == '周周领奖') {
                $messageType = StudentMessageReminderModel::AWARD_NODE_MAP_MESSAGE_TYPE['week_award'];
            } elseif ($avl['award_node'] == 'normal_award' || $avl['event_name'] == '付费年卡') {
                $messageType = StudentMessageReminderModel::AWARD_NODE_MAP_MESSAGE_TYPE['normal_award'];
            } elseif ($avl['award_node'] == 'buy_trial_card' || $avl['award_node'] == 'trial_award' || $avl['event_name'] == '付费体验卡') {
                $messageType = StudentMessageReminderModel::AWARD_NODE_MAP_MESSAGE_TYPE['trial_award'];
            } elseif ($avl['award_node'] == 'cumulative_invite_buy_year_card' || $avl['event_name'] == '付费年卡累计奖励') {
                $messageType = StudentMessageReminderModel::AWARD_NODE_MAP_MESSAGE_TYPE['cumulative_invite_buy_year_card'];
            } elseif ($avl['award_node'] == 'referral_rt_coupon' || $avl['event_name'] == '亲友专属福利') {
                $messageType = StudentMessageReminderModel::AWARD_NODE_MAP_MESSAGE_TYPE['referral_rt_coupon'];
            } else {
                $messageType = 0;
            }
            $tmpAwardData[$avl['id']] = [
                'title'        => $tmpTitle,
                'content'      => $avl['status_zh'],
                'data_id'      => $avl['id'],
                'student_uuid' => $avl['uuid'],
                'type'         => $messageType,
                'href_url'     => '',
                'award_status' => $avl['status'],
                'create_time'  => $avl['create_time'],
            ];
        }
        if (empty($tmpAwardData)) {
            SimpleLogger::info("tmp award data empty", []);
            return false;
        }
        //获取奖励记录ID是否存在提醒消息数据表
        $messageReminderData = StudentMessageReminderModel::getRecords([
            'student_uuid' => array_column($tmpAwardData, 'student_uuid'),
            'type'         => array_column($tmpAwardData, 'type'),
            'data_id'      => array_column($tmpAwardData, 'data_id'),
        ], ['id', 'status', 'data_id']);
        if (!empty($messageReminderData)) {
            $messageReminderData = array_column($messageReminderData, null, 'data_id');
        }
        //区分操作是修改/新增
        $tableName = StudentMessageReminderModel::$table;
        foreach ($tmpAwardData as $fv) {
            if (isset($messageReminderData[$fv['data_id']])) {
                //修改
                if (!in_array($fv['award_status'], [
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED,
                    ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING
                ])) {
                    $formatData['update'] .= "update " . $tableName . " set update_time=" . $nowTime . ",content='" . $fv['content'] . "',status=" . Constants::STATUS_FALSE . " where id=" . $messageReminderData[$fv['data_id']]['id'] . ';';
                } else {
                    $formatData['update'] .= "update " . $tableName . " set update_time=" . $nowTime . ",content='" . $fv['content'] . "' where id=" . $messageReminderData[$fv['data_id']]['id'] . ';';
                }
            } else {
                //新增
                if ($fv['award_status'] == ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED ||
                    $fv['award_status'] == ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING) {
                    $formatData['insert'][] = [
                        'title'        => $fv['title'],
                        'content'      => $fv['content'],
                        'data_id'      => $fv['data_id'],
                        'student_uuid' => $fv['student_uuid'],
                        'type'         => $fv['type'],
                        'href_url'     => $fv['href_url'],
                        'status'       => Constants::STATUS_TRUE,
                        'create_time'  => $fv['create_time'],
                    ];
                }
            }
        }
        StudentMessageReminderModel::eventTaskAwardMessageReminderAddAndUpdate($formatData['insert'], $formatData['update']);
        return true;
    }
}
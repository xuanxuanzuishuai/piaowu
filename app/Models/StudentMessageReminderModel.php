<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class StudentMessageReminderModel extends Model
{
    public static $table = "student_message_reminder";
    //已读状态:1未读 2已读
    const STATUS_UNREAD = 1;
    const STATUS_READ = 2;

    //消息类型
    const MESSAGE_TYPE_GOLD_LEAF_60_EXPIRATION_REMINDER = 1;//智能金叶子60天后到期提醒
    const MESSAGE_TYPE_GOLD_LEAF_REFEREE_TRIAL_AWARD_WAITING_RECORDED_REMINDER = 2;//智能转介绍付费体验卡获取到的金叶子待入帐提醒
    const MESSAGE_TYPE_GOLD_LEAF_REFEREE_BUY_TRIAL_AWARD_WAITING_RECORDED_REMINDER = 3;//智能转介绍付费体验卡获取到的金叶子待入帐提醒
    const MESSAGE_TYPE_GOLD_LEAF_REFEREE_NORMAL_AWARD_WAITING_RECORDED_REMINDER = 4;//智能转介绍付费付费年卡获取到的金叶子待入帐提醒
    const MESSAGE_TYPE_GOLD_LEAF_WEEK_ACTIVITY_WAITING_RECORDED_REMINDER = 5;//智能周周领奖活动获取到的金叶子待入帐提醒
    const MESSAGE_TYPE_GOLD_LEAF_CUMULATIVE_INVITE_BUY_YEAR_CARD_WAITING_RECORDED_REMINDER = 6;//智能付费年卡累计奖励获取到的金叶子待入帐提醒
    const MESSAGE_TYPE_GOLD_LEAF_REFERRAL_RT_COUPON_WAITING_RECORDED_REMINDER = 7;//智能亲友专属福利获取到的金叶子待入帐提醒

    //奖励节点与提醒消息类型映射关系
    const AWARD_NODE_MAP_MESSAGE_TYPE = [
        'week_award'                      => self::MESSAGE_TYPE_GOLD_LEAF_WEEK_ACTIVITY_WAITING_RECORDED_REMINDER,
        'normal_award'                    => self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_NORMAL_AWARD_WAITING_RECORDED_REMINDER,
        'trial_award'                     => self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_TRIAL_AWARD_WAITING_RECORDED_REMINDER,
        'buy_trial_card'                  => self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_BUY_TRIAL_AWARD_WAITING_RECORDED_REMINDER,
        'cumulative_invite_buy_year_card' => self::MESSAGE_TYPE_GOLD_LEAF_CUMULATIVE_INVITE_BUY_YEAR_CARD_WAITING_RECORDED_REMINDER,
        'referral_rt_coupon'              => self::MESSAGE_TYPE_GOLD_LEAF_REFERRAL_RT_COUPON_WAITING_RECORDED_REMINDER,
    ];

    //金叶子商城可以读取的消息类型
    const GOLD_LEAF_SHOP_REMINDER_TYPE = [
        self::MESSAGE_TYPE_GOLD_LEAF_60_EXPIRATION_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_WEEK_ACTIVITY_WAITING_RECORDED_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_NORMAL_AWARD_WAITING_RECORDED_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_TRIAL_AWARD_WAITING_RECORDED_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_REFEREE_BUY_TRIAL_AWARD_WAITING_RECORDED_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_CUMULATIVE_INVITE_BUY_YEAR_CARD_WAITING_RECORDED_REMINDER,
        self::MESSAGE_TYPE_GOLD_LEAF_REFERRAL_RT_COUPON_WAITING_RECORDED_REMINDER,

    ];

    /**
     * 写入数据库
     * @param $insertData
     * @return bool
     */
    public static function add($insertData): bool
    {
        return self::batchInsert($insertData);
    }

    /**
     * 写入/修改数据库
     * @param $insertData
     * @param $updateSqlData
     * @return bool
     */
    public static function eventTaskAwardMessageReminderAddAndUpdate($insertData, $updateSqlData): bool
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (!empty($insertData)) {
            $insertRes = self::batchInsert($insertData);
            if (empty($insertRes)) {
                $db->rollBack();
                return false;
            }
        }
        if (!empty($updateSqlData)) {
            $updateRes = self::batchUpdateRecordDifferentWhereAndData($updateSqlData);
            SimpleLogger::info('update res', ['update_sql' => $updateSqlData,'res'=>$updateRes]);
            if (empty($updateRes)) {
                $db->rollBack();
                return false;
            }
        }
        $db->commit();
        return true;
    }

    /**
     * 获取消息列表
     * @param string $studentUuid
     * @param array $messageTypes
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function list(string $studentUuid, array $messageTypes, int $page, int $count): array
    {
        $data = [
            'total_count' => 0,
            'list'        => [],
        ];
        $where = [
            'student_uuid' => $studentUuid,
            'type'         => $messageTypes,
            'status'       => Constants::STATUS_TRUE,
        ];
        $dataCount = self::getCount($where);
        if ($dataCount == 0) {
            return $data;
        }
        $data['total_count'] = $dataCount;
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $where['ORDER'] = ["id" => "DESC"];
        $data['list'] = self::getRecords($where, ['type', 'title', 'content', 'create_time',]);
        return $data;
    }
}
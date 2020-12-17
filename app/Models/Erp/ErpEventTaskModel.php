<?php

namespace App\Models\Erp;

class ErpEventTaskModel extends ErpModel
{
    public static $table = 'erp_event_task';

    const STATUS_NORMAL = 1;
    //award列里定义的json格式数据，data['awards'][0]['to']含义:
    const AWARD_TO_REFERRER = 1; //奖励介绍人
    const AWARD_TO_BE_REFERRER = 2; //奖励被介绍人


    //event_task_type
    const BUY = 4; //购买
    const COMMUNITY_DURATION_POSTER = 6; //课时达标且审核通过
    const REISSUE_AWARD = 13; //补发红包
    /**
     * 检测任务奖励完成状态
     * @param $userId
     * @param $eventId
     * @return array|null
     */
    public static function checkUserTaskAwardStatus($userId, $eventId)
    {
        $db = self::dbRO();
        $sql = 'SELECT
                    ue.user_id,
                    ueta.status as "award_status",
                    uet.id AS "task_id",
                    uet.NAME AS "task_name",
                    uet.DESC AS "task_desc" 
                FROM
                    ' . self::$table . ' AS uet
                    LEFT JOIN ' . ErpUserEventTaskModel::$table . ' AS ue ON uet.id = ue.event_task_id 
                    AND ue.user_id = ' . $userId . '
                    LEFT JOIN ' . ErpUserEventTaskAwardModel::$table . ' AS ueta ON ue.id = ueta.uet_id 
                WHERE
                    uet.event_id = ' . $eventId;
        return $db->queryAll($sql);
    }
}
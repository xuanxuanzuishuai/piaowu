<?php

namespace App\Models\Erp;


class ErpEventModel extends ErpModel
{
    public static $table = 'erp_event';

    const STATUS_NORMAL = 1;

    const TYPE_IS_REFERRAL = 1; //转介绍
    const TYPE_IS_REISSUE_AWARD = 10; //补发红包
    const TYPE_IS_DURATION_POSTER = 5; //课时达标并且上传海报
    const DAILY_UPLOAD_POSTER = 4; //日常上传截图活动
    const TYPE_IS_CHECKIN_POSTER = 14; // 体验营打卡

    /**
     * 拉取event事件
     * @param $params
     * @return array
     */
    public static function wholeEvents($params)
    {
        $where = [
            'ORDER' => ['id' => 'DESC'],
        ];
        if (!empty($params['app_id'])) {
            $where['app_id'] = $params['app_id'];
        }
        if (!empty($params['event_id'])) {
            $where['id'] = $params['event_id'];
        }
        if (!empty($params['type'])) {
            $where['type'] = $params['type'];
        }

        $where['status'] = ErpEventModel::STATUS_NORMAL;

        $events = ErpEventModel::getRecords($where);
        if (empty($events)) {
            return [];
        }

        $tasks = ErpEventTaskModel::getRecords([
            'event_id' => array_column($events, 'id'),
            'status' => [ErpEventTaskModel::STATUS_NORMAL]
        ]);
        $map = [];
        foreach ($tasks as $task) {
            $map[$task['event_id']][] = $task;
        }
        foreach ($events as $k => $event) {
            if (isset($map[$event['id']])) {
                $event['tasks'] = $map[$event['id']];
            } else {
                $event['tasks'] = [];
            }
            $event['img_url'] = json_decode($event['img_url'], true);
            $events[$k] = $event;
        }
        return $events;
    }
}
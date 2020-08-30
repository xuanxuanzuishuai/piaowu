<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/2/14
 * Time: 上午10:47
 */

namespace App\Services;

use App\Models\EventModel;
use App\Models\EventTaskModel;

class EventService
{
    //DSS按页拉取全部事件
    public static function wholeEvents($params)
    {
        $where = [
            'ORDER'  => ['id' => 'DESC'],
        ];
        if(!empty($params['app_id'])) {
            $where['app_id'] = $params['app_id'];
        }
        if (!empty($params['event_id'])) {
            $where['id'] = $params['event_id'];
        }
        if (!empty($params['type'])) {
            $where['type'] = $params['type'];
        }

        $where['status'] = EventModel::STATUS_NORMAL;

        $events = EventModel::getRecords($where);
        if(empty($events)) {
            return [];
        }

        $tasks = EventTaskModel::getRecords([
            'event_id' => array_column($events, 'id'),
            'status' => [EventTaskModel::STATUS_NORMAL]
        ]);
        $map = [];
        foreach($tasks as $task) {
            $map[$task['event_id']][] = $task;
        }
        foreach($events as $k => $event) {
            if(isset($map[$event['id']])) {
                $event['tasks'] = $map[$event['id']];
            } else {
                $event['tasks'] = [];
            }
            $events[$k] = $event;
        }
        return $events;
    }
}
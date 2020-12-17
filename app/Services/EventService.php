<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/18
 * Time: 17:36
 */

namespace App\Services;


use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;

class EventService
{
    /**
     * 全部事件
     * @param $params
     * @return array|mixed
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
            $events[$k] = $event;
        }
        return $events;
    }

    /**
     * 获取活动下对应任务
     * @param int $eventId
     * @param int $eventType
     * @return array|mixed
     */
    public static function getEventTasksList($eventId = 0, $eventType = 0)
    {
        if (empty($eventId) && empty($eventType)) {
            return [];
        }
        $params = [];
        if (!empty($eventId)) {
            $params['event_id'] = $eventId;
        }

        if (!empty($eventType)) {
            $params['type'] = $eventType;
        }
        $list = self::wholeEvents($params);
        foreach ($list as $key => &$value) {
            if (!empty($value['condition'])) {
                $value['condition'] = json_decode($value['condition'], true);
            }
        }
        return $list;
    }
}
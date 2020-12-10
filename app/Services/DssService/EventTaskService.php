<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 11:08 AM
 */

namespace App\Services\DssService;

use App\Libs\RedisDB;
use App\Models\Dss\DssErpEventTaskModel;
use App\Models\Dss\DssErpEventModel;


/**
 * Class EventTaskService
 * @package App\Services\DssService
 */
class EventTaskService
{

    /**
     * 获取缓存key
     * @param $eventId
     * @return string
     */
    private static function getEventTaskCacheKey($eventId)
    {
        return DssErpEventModel::EVENT_TASK_CACHE_PREFIX . $eventId;
    }


    /**
     * 获取事件任务模板数据
     * @param $eventId
     * @param $field
     * @param $date
     * @return mixed
     */
    public static function getEventTaskCache($eventId, $field = '')
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getEventTaskCacheKey($eventId);
        //检测数据是否存在
        if (empty($redis->exists($cacheKey))) {
            self::setEventTaskCache($eventId);
        }
        //获取数据
        if (empty($field)) {
            $cacheData = $redis->hgetall($cacheKey);
        } else {
            $cacheData[$field] = $redis->hget($cacheKey, $field);
        }
        if (empty($cacheData)) {
            return [];
        }
        array_walk($cacheData, function ($cv, $ck) use (&$data) {
            $data[$ck] = json_decode($cv, true);
        });
        return $data;
    }


    /**
     * 设置事件活动缓存数据
     * @param $eventId
     */
    private static function setEventTaskCache($eventId)
    {
        $cacheData = [];
        $cacheKey = self::getEventTaskCacheKey($eventId);
        $eventTaskData = DssErpEventModel::eventTaskData($eventId);
        if (empty($eventTaskData)) {
            $cacheData = [];
        } else {
            array_map(function ($item) use (&$cacheData) {
                //event数据
                if (empty($cacheData['event'])) {
                    $cacheData['event'] = json_encode([
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'desc' => $item['desc'],
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'setting' => json_decode($item['settings'], true),
                    ]);
                }
                //task数据
                $cacheData['task'][$item['task_id']] = [
                    'id' => $item['task_id'],
                    'condition' => json_decode($item['condition'], true),
                    'award' => json_decode($item['award'], true),
                ];
            }, $eventTaskData);
            $cacheData['task'] = json_encode($cacheData['task']);
        }
        $redis = RedisDB::getConn();
        $redis->hmset($cacheKey, $cacheData);
        $redis->expire($cacheKey, DssErpEventModel::CACHE_EXPIRE_TIME);
    }
}
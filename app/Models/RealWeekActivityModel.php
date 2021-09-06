<?php
/**
 * 真人 - 周周有奖
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class RealWeekActivityModel extends Model
{
    public static $table = 'real_week_activity';
    //真人周周有奖并发锁缓存key
    const REAL_WEEK_LOCK_KEY = 'real_week_lock_';

    /**
     * 获取周周领奖活动列表和总数
     * @param $params
     * @param $limit
     * @param array $order
     * @return array
     */
    public static function searchList($params, $limit, $order = [])
    {
        $where = [];
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (!empty($params['enable_status'])) {
            $where['enable_status'] = $params['enable_status'];
        }
        if (!empty($params['start_time_s'])) {
            $where['start_time[>=]'] = $params['start_time_s'];
        }
        if (!empty($params['start_time_e'])) {
            $where['start_time[<=]'] = $params['start_time_e'];
        }
        if (isset($params['id']) && !Util::emptyExceptZero($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (isset($params['activity_id']) && !Util::emptyExceptZero($params['activity_id'])) {
            $where['activity_id'] = $params['activity_id'];
        }
        if (!empty($params['status'])) {
            $where['enable_status'] = $params['status'];
        }

        $total = self::getCount($where);
        if ($total <= 0) {
            return [[], 0];
        }

        // 计算是否超过总数 - 超过总数说明数据已经到最后一页
        if ($total < $limit[0]) {
            return [[], $total];
        }

        if (!empty($limit)) {
            $where['LIMIT'] = $limit;
        }
        if (empty($order)) {
            $order = ['id' => 'DESC'];
        }
        $where['ORDER'] = $order;

        $list = self::getRecords($where);
        return [$list, $total];
    }

    /**
     * 根据总的活动id获取信息
     * @param $activityId
     * @return array|mixed
     */
    public static function getDetailByActivityId($activityId)
    {
        $db = MysqlDB::getDB();
        $records = $db->select(
            self::$table . '(w)',
            [
                '[>]' . ActivityExtModel::$table . '(a)' => ['w.activity_id' => 'activity_id'],
            ],
            [
                'w.id',
                'w.name',
                'w.activity_id',
                'w.event_id',
                'w.guide_word',
                'w.share_word',
                'w.start_time',
                'w.end_time',
                'w.enable_status',
                'w.banner',
                'w.share_button_img',
                'w.award_detail_img',
                'w.upload_button_img',
                'w.strategy_img',
                'w.create_time',
                'w.update_time',
                'w.operator_id',
                'w.personality_poster_button_img',
                'w.poster_prompt',
                'w.poster_make_button_img',
                'w.share_poster_prompt',
                'w.retention_copy',
                'w.poster_order',
                'a.award_rule',
                'a.remark',
            ],
            [
                'w.activity_id' => $activityId
            ]
        );
        return $records[0] ?? [];
    }

    /**
     * 检查活动时间，与已启用活动有时间冲突，不可启用
     * @param $startTime
     * @param $endTime
     * @param $eventId
     * @param $exceptActId
     * @return array
     */
    public static function checkTimeConflict($startTime, $endTime, $eventId, $exceptActId = 0)
    {
        $where = [
            'start_time[<=]' => $endTime,
            'end_time[>=]' => $startTime,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        if (!empty($eventId)) {
            $where['event_id'] = $eventId;
        }
        if (!empty($exceptActId)) {
            $where['id[!]'] = $exceptActId;
        }

        return self::getRecords($where);
    }

    /**
     * 获取可分享活动列表
     * @param $params
     * @return array
     */
    public static function getSelectList($params)
    {
        $limit = $params['limit'] ?? 2;
        $active = OperationActivityModel::getActiveActivity(TemplatePosterModel::STANDARD_POSTER);
        if (empty($active)) {
            return self::getRecords(['ORDER' => ['id' => 'DESC'], 'LIMIT' => [0, $limit]]);
        }
        $list = self::getRecords(
            [
                'id[<=]' => $active['id'],
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [0, $limit]
            ]
        );
        $now = time();
        foreach ($list as $key => &$item) {
            $item['active'] = Constants::STATUS_FALSE;
            if ($now - $item['start_time'] >= Util::TIMESTAMP_12H) {
                $item['active'] = Constants::STATUS_TRUE;
            }
            $item = self::formatOne($item);
        }
        return array_reverse($list);
    }

    /**
     * 格式化数据
     * @param $item
     * @return mixed
     */
    private static function formatOne($item)
    {
        if (!empty($item['name'])) {
            $item['name'] = $item['name'] . '(' . date('m月d日', $item['start_time']) . '-' . date('m月d日', $item['end_time']) . ')';
        }
        return $item;
    }

    /**
     * 获取时间可参与的周周有奖活动:数量和时间指定
     * @param int $limit
     * @param int $time
     * @return array|mixed
     */
    public static function getStudentCanSignWeekActivity($limit, $time)
    {
        $activityData = self::getRecords(
            [
                'start_time[<]' => $time,
                'end_time[>]' => $time,
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'ORDER' => ['id' => "DESC"],
                'LIMIT' => $limit
            ],
            [
                'id',
                'activity_id',
                'poster_order',
                'end_time',
                'name',
            ]);
        return empty($activityData) ? [] : $activityData;
    }
}

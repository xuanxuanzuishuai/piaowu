<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 6:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class ReferralActivityModel extends Model
{
    public static $table = 'referral_activity';
    // 活动状态 0未启用 1启用 2禁用
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;
    const STATUS_DOWN = 2;


    //海报的位置/大小信息
    public static $studentWXActivityPosterConfig = [
        'qr_x' => 533,
        'qr_y' => 92,
        'poster_width' => 750,
        'poster_height' => 1334,
        'qr_width' => 154,
        'qr_height' => 154
    ];

    public static function insert($data, $taskId)
    {
        $now = time();
        return self::insertRecord([
            'event_id' => $data['event_id'],
            'task_id' => $taskId,
            'name' => $data['name'],
            'guide_word' => $data['guide_word'],
            'share_word' => $data['share_word'],
            'poster_url' => $data['poster_url'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 0,
            'create_time' => $now,
            'update_time' => $now,
            'operator_id' => $data['operator_id'],
            'remark' => $data['remark']
        ], false);
    }

    /**
     * 修改活动
     * @param $data
     * @param $activityId
     * @return int|null
     */
    public static function modify($data, $activityId)
    {
        $now = time();
        return self::updateRecord($activityId, [
            'name' => $data['name'],
            'guide_word' => $data['guide_word'],
            'share_word' => $data['share_word'],
            'poster_url' => $data['poster_url'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'update_time' => $now,
            'remark' => $data['remark']
        ], false);
    }

    /**
     * 活动列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = " where 1=1 ";
        $map = [];
        if (!empty($params['name'])) {
            $where .= " and name like :name ";
            $map[':name'] = "%{$params['name']}%";
        }
        if (isset($params['status']) && is_numeric($params['status'])) {
            $where .= " and status = :status ";
            $map[':status'] =  $params['status'];
        }
        if (!empty($params['status']) && is_array($params['status'])) {
            $status = implode(',', $params['status']);
            $where .= " and status in ( {$status} ) ";
        }

        if (!empty($params['start_time'])) {
            $where .= " and start_time >= :start_time ";
            $map[':start_time'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where .= " and start_time <= :end_time";
            $map[':end_time'] = strtotime($params['end_time']);
        }

        $now = time();
        if (!empty($params['going'])) {
            // 进行中
            $where .= " and start_time <= " . $now . " and end_time >= " . $now;
        }

        $table = ReferralActivityModel::$table;
        $totalCount = MysqlDB::getDB()->queryAll("select count(id) count from {$table}" . $where, $map);
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }

        $order = ' order by id desc ';
        $limit = Util::limitation($params['page'], $params['count']);

        $results = MysqlDB::getDB()->queryAll("
            select *,
             case when start_time > unix_timestamp() then 1 when end_time < unix_timestamp() then 3 else 2 end activity_time_status
            from " . $table . $where . $order . $limit , $map);

        return [$results, $totalCount];
    }

    /**
     * 检查活动时间，与已启用活动有时间冲突，不可启用
     * @param $startTime
     * @param $endTime
     * @param $eventId
     * @param $exceptActId
     * @return bool
     */
    public static function checkTimeConflict($startTime, $endTime, $eventId, $exceptActId = 0)
    {
        $where = [
            'start_time[<=]' => $endTime,
            'end_time[>=]' => $startTime,
            'status' => self::STATUS_ENABLE,
            'event_id' => $eventId
        ];
        if (!empty($exceptActId)) {
            $where['id[!]'] = $exceptActId;
        }

        $act = ReferralActivityModel::getRecords($where);
        return !empty($act) ? true : false;
    }
}
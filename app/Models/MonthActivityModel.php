<?php
/**
 * 月月有奖
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;

class MonthActivityModel extends Model
{
    public static $table = 'month_activity';

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
                'w.start_time',
                'w.end_time',
                'w.enable_status',
                'w.banner',
                'w.make_poster_button_img',
                'w.make_poster_tip_word',
                'w.award_detail_img',
                'w.create_poster_button_img',
                'w.share_poster_tip_word',
                'w.create_time',
                'w.update_time',
                'w.operator_id',
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
     * @param $exceptActId
     * @return array
     */
    public static function checkTimeConflict($startTime, $endTime, $exceptActId = 0)
    {
        $where = [
            'start_time[<=]' => $endTime,
            'end_time[>=]' => $startTime,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        if (!empty($exceptActId)) {
            $where['id[!]'] = $exceptActId;
        }

        return self::getRecords($where);
    }
}

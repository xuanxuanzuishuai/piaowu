<?php
/**
 * 限时活动基础信息
 */

namespace App\Models\LimitTimeActivity;

use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Model;

class LimitTimeActivityModel extends Model
{
    public static $table = 'limit_time_activity';

    const MAX_TASK_NUM = 10;    // 最大分享任务数量

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
        if (!empty($params['activity_name'])) {
            $where['a.activity_name[~]'] = $params['activity_name'];
        }
        if (!empty($params['enable_status'])) {
            $where['a.enable_status'] = $params['enable_status'];
        }
        if (!empty($params['start_time_s'])) {
            $where['a.start_time[>=]'] = $params['start_time_s'];
        }
        if (!empty($params['start_time_e'])) {
            $where['a.start_time[<=]'] = $params['start_time_e'];
        }
        if (isset($params['id']) && !Util::emptyExceptZero($params['id'])) {
            $where['a.id'] = $params['id'];
        }
        if (isset($params['activity_id']) && !Util::emptyExceptZero($params['activity_id'])) {
            $where['a.activity_id'] = $params['activity_id'];
        }
        $db = MysqlDB::getDB();
        $total = $db->count(self::$table . '(a)', $where);
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
            $order = ['a.id' => 'DESC'];
        }
        $where['ORDER'] = $order;

        $list = $db->select(
            self::$table . '(a)',
            [
                "[>]" . LimitTimeActivityHtmlConfigModel::$table.'(c)'=> ['a.activity_id' => 'activity_id']
            ],
            [
                'a.activity_id',
                'a.activity_name',
                'a.start_time',
                'a.end_time',
                'a.enable_status',
                'a.create_time',
                'c.remark',
            ],
            $where
        );
        return [$list, $total];
    }
}

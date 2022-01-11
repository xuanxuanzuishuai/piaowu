<?php
/**
 * 真人 - 周周有奖
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use Medoo\Medoo;

class RealWeekActivityModel extends Model
{
    public static $table = 'real_week_activity';
    //真人周周有奖并发锁缓存key
    const REAL_WEEK_LOCK_KEY = 'real_week_lock_';

    const TARGET_USER_ALL = 1;  // 有效付费用户范围 - 所有
    const TARGET_USER_PART = 2; // 有效付费用户范围 - 部分

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
                'w.share_poster_prompt',
                'w.retention_copy',
                'w.poster_order',
                'w.target_user_type',
                'w.delay_second',
                'w.send_award_time',
                'w.priority_level',
                'w.target_use_first_pay_time_start',
                'w.target_use_first_pay_time_end',
                'w.award_prize_type',
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
     * 获取当前时间可参与的周周有奖活动:数量和时间指定
     * @param int $limit
     * @param int $time
     * @return array|mixed
     */
    public static function getStudentCanSignWeekActivity($limit, $time = 0)
    {
        $where = [
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
            'ORDER' => ['start_time' => "DESC"],
            'start_time[<]' => !empty($time) ? $time : time(),    // 确保当前活动已经开始 - 过滤掉预先创建但未到开始时间的活动
            'LIMIT' => $limit
        ];
        if (!empty($time)) {
            $where['start_time[<]'] = $time;
            $where['end_time[>]'] = $time;
        }
        $activityData = self::getRecords(
            $where,
            [
                'name',
                'activity_id',
                'share_word',
                'banner',
                'share_button_img',
                'award_detail_img',
                'upload_button_img',
                'strategy_img',
                'personality_poster_button_img',
                'share_poster_prompt',
                'retention_copy',
                'poster_order',
                'start_time',
                'end_time',
            ]
        );
        return empty($activityData) ? [] : $activityData;
    }

    /**
     * 获取活动分享任务活动数据
     * @param $activityIds
     * @return array
     */
    public static function getActivityAndTaskData($activityIds)
    {
        $db = MysqlDB::getDB();
        $list = $db->select(self::$table,
            [
                '[>]' . RealSharePosterPassAwardRuleModel::$table => ['activity_id' => 'activity_id'],
            ],
            [
                self::$table . '.name',
                self::$table . '.activity_id',
                self::$table . '.start_time',
                self::$table . '.end_time',
                self::$table . '.enable_status',
                self::$table . '.delay_second',
                self::$table . '.award_prize_type',
                'task_num_count' => Medoo::raw('max('.RealSharePosterPassAwardRuleModel::$table . '.success_pass_num'.')'),
                'task_data' => Medoo::raw('group_concat(concat_ws(:separator,'.
                    RealSharePosterPassAwardRuleModel::$table . '.success_pass_num,'.
                    RealSharePosterPassAwardRuleModel::$table . '.award_amount,'.
                    RealSharePosterPassAwardRuleModel::$table . '.award_type) ORDER BY '.
                    RealSharePosterPassAwardRuleModel::$table . '.success_pass_num)',[":separator"=>'-']),
            ],
            [
                self::$table . '.activity_id' => $activityIds,
                "GROUP" => [self::$table . '.activity_id'],
                'ORDER' => ['activity_id' => 'DESC'],
            ]);
        return empty($list) ? [] : $list;
    }
}

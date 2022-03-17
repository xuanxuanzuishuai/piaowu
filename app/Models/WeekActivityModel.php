<?php
/**
 * 周周有奖
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpEventModel;
use Medoo\Medoo;

class WeekActivityModel extends Model
{
    public static $table = 'week_activity';
    const TARGET_USER_ALL = 1;  // 有效付费用户范围 - 所有
    const TARGET_USER_PART = 2; // 有效付费用户范围 - 部分

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
                'w.target_user_type',
                'w.target_use_first_pay_time_end',
                'w.target_use_first_pay_time_start',
                'w.delay_second',
                'w.send_award_time',
                'w.priority_level',
                'w.activity_country_code',
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
        //已开始的活动，按照开始时间倒叙排序，第一活动如果处在有效期内则返回最近的两个活动，否则返回第一个活动
        $now = time();
        $limit = $params['limit'] ?? 2;
        // 获取当前有效活动
        $active = OperationActivityModel::getActiveActivity(TemplatePosterModel::STANDARD_POSTER);
        if (empty($active)) {
            return self::getRecords(['enable_status' => OperationActivityModel::ENABLE_STATUS_ON, 'ORDER' => ['start_time' => 'DESC'], 'LIMIT' => [0, 1]]);
        }
        // 获取上一期
        $list = self::getRecords(
            [
                'id[<=]' => $active['id'],
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'ORDER' => ['start_time' => 'DESC'],
                'LIMIT' => [0, $limit]
            ]
        );
        // 组装数据
        foreach ($list as $key => &$item) {
            $item['active'] = Constants::STATUS_FALSE;
            if ($now - $item['start_time'] >= Util::TIMESTAMP_12H) {
                $item['active'] = Constants::STATUS_TRUE;
            }
            $item = self::formatOne($item);
        }
        unset($item);

        // XYZOP-1262 限时活动
        list($oneActivityId, $twoActivityId, $wkIds, $threeActivityId) = DictConstants::get(DictConstants::XYZOP_1262_WEEK_ACTIVITY, [
            'xyzop_1262_week_activity_one',
            'xyzop_1262_week_activity_two',
            'xyzop_1262_week_activity_ids',
            'xyzop_1262_week_activity_three',
        ]);
        $wkIds = explode(',', $wkIds);
        $twoActivityId = explode(',', $twoActivityId);
        if ($active['activity_id'] == $oneActivityId) {
            // 10.18-10.31  追加四期活动,最后一期活动标记为活跃
            $activityList = WeekActivityModel::getRecords([
                'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
                'activity_id' => $wkIds,
                'ORDER' => ['id' => 'DESC'],
            ]);
            foreach ($activityList as &$item) {
                $item = self::formatOne($item);
            }
            // 重新组装顺序  当前生效活动创建时间是12小时内 - 上期活动， 1，2，3，4，5
            if ($now - $active['start_time'] < Util::TIMESTAMP_12H) {
                // 5,4,3,2,1, 上期活动
                $list = array_merge([$list[0]], $activityList, [$list[1]]);
                $activeKey = count($list) - 1;
            } else {
                // 超过12小时 - 上期活动,5,4,3,2,1
                $list = array_merge([$list[1]], [$list[0]], $activityList);
                $activeKey = count($list) - 1;
            }
            // 重新设置选中的活动
            foreach ($list as $key => &$_activity) {
                if ($key == $activeKey) {
                    $_activity['active'] = Constants::STATUS_TRUE;
                } else {
                    $_activity['active'] = Constants::STATUS_FALSE;
                }
            }
            unset($_activity);
        } elseif (in_array($active['activity_id'], $twoActivityId)) {
            // 11月第一期
            $activityList = WeekActivityModel::getRecords([
                'activity_id' => array_merge($wkIds, [$oneActivityId], $twoActivityId),
                'ORDER' => ['id' => 'DESC'],
            ]);
            $activityGroup = [
                'curr' => [],
                'up' => []
            ];
            foreach ($activityList as $key => $item) {
                // 格式化数据
                $_tmpInfo = self::formatOne($item);
                // 区分是当期活动还是上期活动
                if (in_array($item['activity_id'], $twoActivityId)) {
                    $activityGroup['curr'][] = $_tmpInfo;
                } else {
                    $activityGroup['up'][] = $_tmpInfo;
                }
            }
            // 重新组装顺序 当前生效活动创建时间是12小时内
            if ($now - $active['start_time'] < Util::TIMESTAMP_12H) {
                // 11-3,11-2,11-1，5,4,3,2,1
                $list = array_merge($activityGroup['curr'], $activityGroup['up']);
            } else {
                // 5,4,3,2,1,11-3,11-2,11-1
                $list = array_merge($activityGroup['up'], $activityGroup['curr']);
            }
            // 重新设置选中的活动
            $activeKey = count($list) - 1;  // 数组最后一个选中， 这里需要注意的是方法最后做了array_reverse 所以相当于是第一个选中
            foreach ($list as $key => &$_activity) {
                if ($key == $activeKey) {
                    $_activity['active'] = Constants::STATUS_TRUE;
                } else {
                    $_activity['active'] = Constants::STATUS_FALSE;
                }
            }
            unset($_activity);
        } elseif ($active['activity_id'] == $threeActivityId) {
            // 第二次活动开始，需要补前几期活动
            $activityList = WeekActivityModel::getRecords([
                'activity_id' => array_merge($twoActivityId, [$threeActivityId]),
                'ORDER' => ['id' => 'DESC'],
            ]);
            $activityGroup = [
                'curr' => [],
                'up' => []
            ];
            foreach ($activityList as $key => $item) {
                // 格式化数据
                $_tmpInfo = self::formatOne($item);
                // 区分是当期活动还是上期活动
                if ($item['activity_id'] == $threeActivityId) {
                    $activityGroup['curr'][] = $_tmpInfo;
                } else {
                    $activityGroup['up'][] = $_tmpInfo;
                }
            }
            // 重新组装顺序 当前生效活动创建时间是12小时内
            if ($now - $active['start_time'] < Util::TIMESTAMP_12H) {
                // 11-2-1,11-1-3,11-1-2,11-1-1
                $list = array_merge($activityGroup['curr'], $activityGroup['up']);
            } else {
                // 11-1-3,11-1-2,11-1-1,11-2-1
                $list = array_merge($activityGroup['up'], $activityGroup['curr']);
            }
            // 重新设置选中的活动
            $activeKey = count($list) - 1;  // 数组最后一个选中， 这里需要注意的是方法最后做了array_reverse 所以相当于是第一个选中
            foreach ($list as $key => &$_activity) {
                if ($key == $activeKey) {
                    $_activity['active'] = Constants::STATUS_TRUE;
                } else {
                    $_activity['active'] = Constants::STATUS_FALSE;
                }
            }
            unset($_activity);
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
     * 获取活动分享任务活动数据
     * @param $activityIds
     * @return array
     */
    public static function getActivityAndTaskData($activityIds): array
    {
        $db = MysqlDB::getDB();
        $list = $db->select(self::$table,
            [
                '[>]' . SharePosterPassAwardRuleModel::$table => ['activity_id' => 'activity_id'],
            ],
            [
                self::$table . '.name',
                self::$table . '.activity_id',
                self::$table . '.start_time',
                self::$table . '.end_time',
                self::$table . '.enable_status',
                self::$table . '.delay_second',
                self::$table . '.award_prize_type',
                'task_num_count' => Medoo::raw('max('.SharePosterPassAwardRuleModel::$table . '.success_pass_num'.')'),
                'task_data' => Medoo::raw('group_concat(concat_ws(:separator,'.
                    SharePosterPassAwardRuleModel::$table . '.success_pass_num,'.
                    SharePosterPassAwardRuleModel::$table . '.award_amount,'.
                    SharePosterPassAwardRuleModel::$table . '.award_type) ORDER BY '.
                    SharePosterPassAwardRuleModel::$table . '.success_pass_num)',[":separator"=>'-']),
            ],
            [
                self::$table . '.activity_id' => $activityIds,
                "GROUP" => [self::$table . '.activity_id'],
                'ORDER' => ['activity_id' => 'DESC'],
            ]);
        return empty($list) ? [] : $list;
    }
}

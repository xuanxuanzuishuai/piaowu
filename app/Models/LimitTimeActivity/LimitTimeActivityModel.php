<?php
/**
 * 限时活动基础信息
 */

namespace App\Models\LimitTimeActivity;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Model;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;

class LimitTimeActivityModel extends Model
{
    public static $table = 'limit_time_activity';

    const MAX_TASK_NUM = 10;    // 最大分享任务数量
    const MAX_TASK_JOIN_NUM = 30;    // 最大活动可参与数

    /**
     * 获取周周领奖活动列表和总数
     * @param $params
     * @param $limit
     * @param array $order
     * @param array $fields
     * @return array
     */
    public static function searchList($params, $limit, array $order = [], array $fields = []): array
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
        if (!empty($params['end_time_s'])) {
            $where['a.end_time[>=]'] = $params['end_time_s'];
        }
        if (!empty($params['end_time_e'])) {
            $where['a.end_time[<=]'] = $params['end_time_e'];
        }
        if (!empty($params['app_id'])) {
            $where['a.app_id'] = (int)$params['app_id'];
        }
        if (!empty($params['activity_country_code'])) {
            $where['a.activity_country_code'] = $params['activity_country_code'];
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
        //查询字段
        $fields = array_unique(array_merge([
            'a.activity_id',
            'a.activity_name',
            'a.start_time',
            'a.end_time',
            'a.enable_status',
            'a.create_time',
            'c.remark',
        ], $fields));
        $list = $db->select(
            self::$table . '(a)',
            [
                "[>]" . LimitTimeActivityHtmlConfigModel::$table . '(c)' => ['a.activity_id' => 'activity_id']
            ],
            $fields,
            $where
        );
        return [$list, $total];
    }

    /**
     * 新增一条活动
     * @param $operationActivityData
     * @param $activityData
     * @param $htmlConfig
     * @param $awardRuleData
     * @param $time
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function add($operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time = 0)
    {
        $logTitle = 'LimitTimeActivityModel:add';
        SimpleLogger::info("$logTitle insert limit time activity data", [$operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time]);
        empty($time) && $time = time();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $operationActivityData['create_time'] = $time;
        $activityId = OperationActivityModel::insertRecord($operationActivityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert operation_activity fail", ['data' => $operationActivityData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存奖配置信息
        $activityData['activity_id'] = $activityId;
        $activityData['create_time'] = $time;
        $res = LimitTimeActivityModel::insertRecord($activityData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert week_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动配置页面
        $htmlConfig['activity_id'] = $activityId;
        $htmlConfig['create_time'] = $time;
        $res = LimitTimeActivityHtmlConfigModel::insertRecord($htmlConfig);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert week_activity html config fail", ['data' => $htmlConfig]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动奖励规则
        array_walk($awardRuleData, function (&$item) use ($activityId) {
            $item['activity_id'] = $activityId;
        });
        $res = LimitTimeActivityAwardRuleModel::batchInsert($awardRuleData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert limit time activity html config fail", ['data' => $awardRuleData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动奖励规则版本
        $awardRuleVersionData = [
            'activity_id' => $activityId,
            'award_info'  => json_encode($awardRuleData),
            'create_time' => $time,
        ];
        $res = LimitTimeActivityAwardRuleVersionModel::insertRecord($awardRuleVersionData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert limit time activity award rule version config fail", ['data' => $awardRuleVersionData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        $db->commit();
        SimpleLogger::info("$logTitle insert limit time activity success", ['activity_id' => $activityId]);
        return $activityId;
    }

    /**
     * 修改活动
     * @param $activityInfo
     * @param $operationActivityData
     * @param $activityData
     * @param $htmlConfig
     * @param $awardRuleData
     * @param $time
     * @return bool
     * @throws RunTimeException
     */
    public static function modify($activityInfo, $operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time = 0)
    {
        $logTitle = 'LimitTimeActivityModel:modify';
        $activityId = $activityInfo['activity_id'] ?? 0;
        SimpleLogger::info("$logTitle insert limit time activity data", [$activityInfo, $operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time]);
        if (empty($activityId)) {
            throw new RunTimeException(["record_not_found"]);
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        $operationActivityData['update_time'] = $time;
        $res = OperationActivityModel::batchUpdateRecord($operationActivityData, ['id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle update operation_activity fail", ['data' => $operationActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update activity fail"]);
        }
        // 更新周周领信息
        $activityData['activity_id'] = $activityId;
        $activityData['update_time'] = $time;
        $res = LimitTimeActivityModel::batchUpdateRecord($activityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle update activity_ext fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update activity fail"]);
        }
        // 更新页面配置
        $htmlConfig['activity_id'] = $activityId;
        $htmlConfig['update_time'] = $time;
        $res = LimitTimeActivityHtmlConfigModel::batchUpdateRecord($htmlConfig, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle update activity html config fail", ['data' => $htmlConfig, 'activity_id' => $activityId]);
            throw new RunTimeException(["update activity fail"]);
        }
        // 先删除奖励规则
        LimitTimeActivityAwardRuleModel::batchDelete(['activity_id' => $activityId]);
        // 更新奖励规则
        array_walk($awardRuleData, function (&$item) use ($activityId) {
            $item['activity_id'] = $activityId;
        });
        $res = LimitTimeActivityAwardRuleModel::batchInsert($awardRuleData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle update limit time activity award rule fail", ['data' => $awardRuleData]);
            throw new RunTimeException(["update activity fail"]);
        }
        // 保存活动奖励规则版本
        $awardRuleVersionData = [
            'activity_id' => $activityId,
            'award_info'  => json_encode($awardRuleData),
            'create_time' => $time,
        ];
        $res = LimitTimeActivityAwardRuleVersionModel::insertRecord($awardRuleVersionData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("$logTitle insert limit time activity award rule version config fail", ['data' => $awardRuleVersionData]);
            throw new RunTimeException(["update activity fail"]);
        }
        // TODO qingfeng.lian 当海报有变化时 考虑通知日志系统
        $db->commit();
        return true;
    }

    /**
     * 根据活动id获取信息
     * @param $activityId
     * @return array|mixed
     */
    public static function getActivityDetail($activityId)
    {
        $db = MysqlDB::getDB();
        $records = $db->get(
            self::$table . '(a)',
            [
                '[>]' . LimitTimeActivityHtmlConfigModel::$table . '(c)' => ['a.activity_id' => 'activity_id'],
            ],
            [
                'a.id',
                'a.app_id',
                'a.activity_name',
                'a.activity_id',
                'a.activity_type',
                'a.start_time',
                'a.end_time',
                'a.enable_status',
                'a.create_time',
                'a.update_time',
                'a.operator_id',
                'a.target_user_type',
                'a.target_user_first_pay_time_start',
                'a.target_user_first_pay_time_end',
                'a.activity_country_code',
                'a.target_user',
                'a.award_prize_type',
                'a.delay_second',
                'a.send_award_time',
                'c.share_poster',
                'c.guide_word',
                'c.share_word',
                'c.banner',
                'c.share_button_img',
                'c.award_detail_img',
                'c.upload_button_img',
                'c.strategy_img',
                'c.personality_poster_button_img',
                'c.poster_prompt',
                'c.share_poster_prompt',
                'c.retention_copy',
                'c.award_rule',
                'c.remark',
                'c.poster_make_button_img',
            ],
            [
                'a.activity_id' => $activityId
            ]
        );
        return $records ?? [];
    }

    /**
     * 获取审核截图搜索条件中下拉框的活动列表
     * 场景1：运营系统审核截图列表中搜索条件中获取活动列表
     * @param $where
     * @return array
     */
    public static function getFilterAfterActivityList($where)
    {
        $sql = "SELECT {{columns}} from " . self::$table . ' as a  {{join}} where 1=1 ';
        !empty($where['app_id']) && $sql .= ' and a.app_id=' . intval($where['app_id']);
        !empty($where['activity_id']) && $sql .= ' and a.activity_id=' . intval($where['activity_id']);
        !empty($where['activity_name']) && $sql .= " and a.activity_name like '%" . trim($where['activity_name']) . "%'";
        if (!empty($where['OR'])) {
            $childSql = '';
            foreach ($where['OR'] as $key => $item) {
                foreach ($item as $_c => $_v) {
                    // 循环拼接sql 如果不是首位存在[~]认为是模糊搜索，否则用等号作为条件
                    // 最后和外面的SQL 拼接好格式： 1=1 and (n like '%1%' or f=1)
                    !empty($childSql) && $childSql .= ' or ';
                    if (stripos($_c, '[~]')) {
                        $childSql .= ' ' . str_replace('[~]', '', $_c) . " like '%" . $_v . "%'";
                    } else {
                        $childSql .= ' ' . $_c . "='" . $_v . "'";
                    }
                }
                unset($_c, $_v);
            }
            unset($key, $item);
            // 把 OR作为一个整体和外面的其他条件做and操作
            !empty($childSql) && $sql .= ' and (' . $childSql . ')';
        }
        if (!empty($where['enable_status'])) {
            if (is_array($where['enable_status'])) {
                $sql .= ' and a.enable_status in (' . implode(',', $where['enable_status']) . ')';
            } else {
                $sql .= ' and a.enable_status=' . intval($where['enable_status']);
            }
        }
        if ($where['share_poster_verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
            $join = ' LEFT JOIN ' . LimitTimeActivitySharePosterModel::$table . " as sp on sp.activity_id=a.activity_id and sp.verify_status=" . SharePosterModel::VERIFY_STATUS_WAIT;
            $sql .= ' and sp.verify_status=' . SharePosterModel::VERIFY_STATUS_WAIT;
        } else {
            $join = '';
        }
        $db = MysqlDB::getDB();
        $countSql = str_replace(['{{columns}}', "{{join}}"], ['count(distinct a.activity_id) as total_count', $join], $sql);
        $res = $db->queryAll($countSql);
        SimpleLogger::info("getFilterAfterActivityList_sql", [$where, $countSql, $res]);
        if (empty($res[0]['total_count'])) {
            return [0, []];
        }
        $columns = 'a.activity_id,a.activity_name';
        $sql .= ' GROUP BY a.activity_id ORDER BY a.id DESC ';
        !empty($where['LIMIT']) && $sql .= ' LIMIT ' . $where['LIMIT'][0] . ',' . $where['LIMIT'][1];
        $listSql = str_replace(['{{columns}}', "{{join}}"], [$columns, $join], $sql);
        $list = $db->queryAll($listSql);
        SimpleLogger::info("getFilterAfterActivityList_sql", [$where, $listSql, $list]);
        return [intval($res[0]['total_count']), is_array($list) ? $list : []];
    }
}

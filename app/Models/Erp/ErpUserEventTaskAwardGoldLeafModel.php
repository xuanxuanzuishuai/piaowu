<?php
/**
 * 金叶子积分表
 */
namespace App\Models\Erp;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Util;
use App\Models\OperationActivityModel;

class ErpUserEventTaskAwardGoldLeafModel extends ErpModel
{
    public static $table = 'erp_user_event_task_award_gold_leaf';

    //奖励发放状态
    const STATUS_NOT_OWN = -1;  // 未获取
    const STATUS_DISABLED = 0; // 不发放
    const STATUS_WAITING = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE = 3; // 发放成功
    const STATUS_GIVE_ING = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    // 拒绝发放原因
    const REASON_RETURN_COST = 'return_cost'; // 退费
    const REASON_NO_PLAY = 'no_play'; // 未练琴
    const REASON_RETURN_DICT = [
        self::REASON_RETURN_COST => '已废除',
        self::REASON_NO_PLAY => '已废除',
    ];

    // 奖励节点
    const AWARD_NODE_BUY_TRIAL = 'buy_trial_card';    // 购买体验卡
    const AWARD_NODE_CUMULATIVE_INVITE_BUY_YEAR = 'cumulative_invite_buy_year_card';    // 累计邀请购买年卡
    const WEEK_WHITE_WEEK_AWARD = 'week_award';

    /**
     * 获取任务积分奖励列表
     * @param array $where
     * @param array $limit
     * @param array $order
     * @param array $fields
     * @return array
     */
    public static function getList(array $where, array $limit = [], array $order = ['id' => 'DESC'], array $fields = []): array
    {
        //获取库+表完整名称
        $awardTableName = self::getTableNameWithDb();
        $eventTaskTableName = ErpEventTaskModel::getTableNameWithDb();
        $studentTableName = ErpStudentModel::getTableNameWithDb();

        $returnList = ['list' => [], 'total' => 0, 'total_award_num' => 0];
        $sqlWhere = "1=1";
        if (!empty($where['id'])) {
            if (is_array($where['id'])) {
                $sqlWhere .= ' AND a.id in ('.implode(',', $where['id']).')';
            } else {
                $sqlWhere .= ' AND a.id=' . $where['id'];
            }
        }
        if (!empty($where['user_id'])) {
            $sqlWhere .= ' AND a.user_id=' . $where['user_id'];
        }
        if (!empty($where['uuid'])) {
            $sqlWhere .= " AND a.uuid='" . $where['uuid'] . "'";
        }
        if (!empty($where['status'])) {
            if (is_array($where['status'])) {
                $sqlWhere .= ' AND a.status in (' . implode(',', $where['status']) . ')';
            } else {
                $sqlWhere .= ' AND a.status=' . $where['status'];
            }
        }
        $db = self::dbRO();
        $count = $db->queryAll('select count(*) as total from ' . $awardTableName . ' as a where ' . $sqlWhere);
        if ($count[0]['total'] <= 0) {
            return $returnList;
        }
        $returnList['total'] = $count[0]['total'];

        // 计算金额总数 - 等于作废不计算总数
        $awardNumList = $db->queryAll('select `a`.`status`, `a`.`award_num` from ' . $awardTableName .' as a  where ' . $sqlWhere);
        foreach ($awardNumList as $item) {
            if ($item['status'] != self::STATUS_DISABLED) {
                $returnList['total_award_num'] += $item['award_num'];
            }
        }
        if (!empty($order)) {
            foreach ($order as $_key => $_sort) {
                $sqlOrder[] = $_key . ' '. $_sort;
            }
            $sqlOrder = !empty($sqlOrder) ? ' order by ' . implode(',', $sqlOrder) : '';
        } else {
            $sqlOrder = "";
        }

        $sqlFields = !empty($fields) ? implode(',', $fields) : 'a.*,et.name as event_name,s.mobile';
        $sqlLimit  = !empty($limit) ? ' limit ' . $limit[1] . ' offset ' . $limit[0] : '';
        $returnList['list'] = $db->queryAll('select ' . $sqlFields . ' from ' . $awardTableName . ' as a' .
                ' left join ' . $eventTaskTableName . ' as et on et.id=a.event_task_id' .
                ' left join ' . $studentTableName . ' as s on s.id=a.user_id' .
                ' where ' . $sqlWhere . $sqlOrder . $sqlLimit) ?? [];

        return $returnList;
    }

    /**
     * 获取学生奖励列表
     * @param $where
     * @param string $group
     * @return array|null
     */
    public static function getStudentAwardList($where, $group = '')
    {
        $returnList = [];
        $whereSqlStr = [];
        if (!empty($where['start_time'])) {
            $whereSqlStr[] = ' `create_time`>=' . $where['start_time'];
        }
        if (!empty($where['end_time'])) {
            $whereSqlStr[] = ' `create_time`<' . $where['end_time'];
        }
        if (!Util::emptyExceptZero($where['status'])) {
            $whereSqlStr[] = ' `status`=' . intval($where['status']);
        }
        if (!Util::emptyExceptZero($where['to'])) {
            $whereSqlStr[] = ' `to`=' . $where['to'];
        }
        if (!Util::emptyExceptZero($where['package_type'])) {
            $whereSqlStr[] = ' `package_type`=' . $where['package_type'];
        }
        if (!empty($where['event_task_id'])) {
            if (is_array($where['event_task_id'])) {
                $whereSqlStr[] = ' `event_task_id` in (' . implode(',', $where['event_task_id']) . ')';
            } else {
                $whereSqlStr[] = ' `event_task_id`=' . $where['event_task_id'];
            }
        }

        if (empty($whereSqlStr)) {
            return $returnList;
        }
        $sql = "select count(*) as total,uuid,finish_task_uuid from " . self::getTableNameWithDb() . ' where ' . implode(' AND ', $whereSqlStr) . ' ' . $group;

        return self::dbRO()->queryAll($sql);
    }

    /**
     * 智能 - 获取学生周周领奖上传分享海报截图审核通过发放的奖励记录
     * 条件：周周领奖的奖励，新规则的奖励， 时间倒序
     * @param $studentUUID
     * @param $where
     * @param $page
     * @param $count
     * @param array $order
     * @return array
     */
    public static function getDssStudentWeekActivitySendAwardList($studentUUID, $where, $page, $count, $order = [])
    {
        $returnList            = ['list' => [], 'total_count' => 0];
        $oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        $awardTableName        = self::getTableNameWithDb();
        $activityTableName     = OperationActivityModel::getTableNameWithDb();
        // 组合sql
        $sqlWhere = 'a.uuid=' . "'" . $studentUUID . "'";
        $sqlWhere .= ' AND a.award_node=' . "'" . Constants::WEEK_SHARE_POSTER_AWARD_NODE . "'";
        $sqlWhere .= ' AND a.activity_id>' . $oldRuleLastActivityId;

        $db    = self::dbRO();
        $totalCount = $db->queryAll('select count(*) as total from ' . $awardTableName . ' as a where ' . $sqlWhere);
        if ($totalCount[0]['total'] <= 0) {
            return $returnList;
        }
        $returnList['total_count'] = $totalCount[0]['total'];

        // 重新组合查询条件的sql
        if (!empty($order)) {
            $sqlWhere .= ' ORDER BY ';
            foreach ($order as $_ok => $_ov) {
                $sqlWhere .= $_ok . ' ' . $_ov;
            }
            unset($_ok, $_ok);
        } else {
            $sqlWhere .= ' ORDER BY a.id DESC';
        }
        $sqlWhere .= ' LIMIT ' . ($page - 1) * $count . ',' . $count;
        $fields = 'o.id activity_id,o.name activity_name,a.award_num,a.status,a.passes_num,a.create_time,a.update_time';
        $list               = $db->queryAll('select ' . $fields . ' from ' . $awardTableName . ' as a ' .
            ' left join ' . $activityTableName . ' as o on a.activity_id=o.id ' .
            ' where ' . $sqlWhere);
        $returnList['list'] = is_array($list) ? $list : [];
        return $returnList;
    }
}

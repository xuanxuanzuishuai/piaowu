<?php


namespace App\Models\Erp;


use App\Models\Dss\DssStudentModel;

class ErpUserEventTaskAwardGoldLeafModel extends ErpModel
{
    public static $table = 'erp_user_event_task_award_gold_leaf';

    //奖励发放状态
    const STATUS_DISABLED = 0; // 不发放
    const STATUS_WAITING = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE = 3; // 发放成功
    const STATUS_GIVE_ING = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    // 拒绝发放原因
    const REASON_RETURN_COST = 'return_cost'; // 退费
    const REASON_RETURN_DICT = [
        self::REASON_RETURN_COST => '已废除',
    ];

    /**
     * 获取任务积分奖励列表
     * @param array $where
     * @param array $limit
     * @param array $order
     * @param array $fields
     * @return array
     */
    public static function getList(array $where, array $limit, array $order = ['ID_DESC'], array $fields = []): array
    {
        //获取库+表完整名称
        $awardTableName = self::getTableNameWithDb();
        $eventTaskTableName = ErpEventTaskModel::getTableNameWithDb();
        $studentTableName = ErpStudentModel::getTableNameWithDb();

        $returnList = ['list' => [], 'total' => 0, 'total_award_num' => 0];
        $sqlWhere = [];
        if (!empty($where['id'])) {
            $sqlWhere[] = 'a.id=' . $where['id'];
        }
        if (!empty($where['user_id'])) {
            $sqlWhere[] = 'a.user_id=' . $where['user_id'];
        }
        if (!empty($where['uuid'])) {
            $sqlWhere[] = 'a.uuid=' . $where['uuid'];
        }
        if (!empty($where['status'])) {
            if (is_array($where['status'])) {
                $sqlWhere[] = 'a.status in (' . implode(',', $where['status']) . ')';
            }else {
                $sqlWhere[] = 'a.status=' . $where['status'];
            }
        }
        $db = self::dbRO();
        $count = $db->queryAll('select count(*) as total from ' . $awardTableName . ' as a where ' . implode(" AND ", $sqlWhere));
        if ($count[0]['total'] <= 0) {
            return $returnList;
        }
        $returnList['total'] = $count[0]['total'];

        // 计算金额总数 - 等于作废不计算总数
        $awardNumList = $db->queryAll('select `a`.`status`, `a`.`award_num` from ' . $awardTableName .' as a  where ' . implode(' AND ', $sqlWhere));
        foreach ($awardNumList as $item) {
            if ($item['status'] != self::STATUS_DISABLED) {
                $returnList['total_award_num'] += $item['award_num'];
            }
        }

        $listSql = 'select a.*,et.name as event_name,s.mobile from ' . $awardTableName . ' as a' .
            ' left join ' . $eventTaskTableName . ' as et on et.id=a.event_task_id' .
            ' left join ' . $studentTableName . ' as s on s.id=a.user_id' .
            ' where ' . implode(' AND ', $sqlWhere);

        $sqlOrder = [];
        foreach ($order as $_sort) {
            switch ($_sort) {
                default:
                    $sqlOrder[] = 'id DESC';
            }
        }
        if (!empty($sqlOrder)) {
            $listSql .= ' order by ' . implode(',', $sqlOrder);
        }

        if (!empty($limit)) {
            $listSql .= ' limit ' . $limit[1] . ' offset ' . $limit[0];
        }

        $returnList['list'] = $db->queryAll($listSql);

        return $returnList;
    }
}
<?php
/**
 * 真人发放魔法石奖励记录信息表
 */

namespace App\Models;

use App\Libs\MysqlDB;

class RealUserAwardMagicStoneModel extends Model
{
    public static $table = 'real_user_award_magic_stone';

    //奖励发放状态
    const STATUS_DISABLED = 0; // 不发放
    const STATUS_WAITING = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE = 3; // 发放成功
    const STATUS_GIVE_ING = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    /**
     * 获取列表
     * @param $where
     * @param $page
     * @param $limit
     * @param $order
     * @return array
     */
    public static function getList($where, $page, $limit, $order): array
    {
        $db = MysqlDB::getDB();
        $sqlWhere = [];
        $awardTable = self::$table . '(a)';
        $weekActivityTable = RealWeekActivityModel::$table . '(w)';
        if (!empty($where['user_id'])) {
            $sqlWhere['a.user_id'] = $where['user_id'];
        }
        if (!empty($where['user_type'])) {
            $sqlWhere['a.user_type'] = $where['user_type'];
        }
        if (!empty($where['award_node'])) {
            $sqlWhere['a.award_node'] = $where['award_node'];
        }
        if (!empty($where['passes_num[>=]'])) {
            $sqlWhere['a.passes_num[>=]'] = $where['passes_num[>=]'];
        }
        if (empty($sqlWhere)) {
            return [0, []];
        }
        $total = $db->count($awardTable, $where);
        if (empty($total)) {
            return [0, []];
        }
        // 列表
        $sqlWhere['LIMIT'] = [($page-1)*$limit, $limit];
        $sqlWhere['ORDER'] = !empty($order) ? $order : ['a.id' => 'DESC'];
        $list = $db->select(
            $awardTable,
            [
                '[>]' . $weekActivityTable => ['a.activity_id' => 'activity_id']
            ],
            [
                'a.activity_id',
                'a.award_amount',
                'a.award_status',
                'a.passes_num',
                'a.other_data',
                'a.create_time',
                'a.update_time',
                'w.name (activity_name)',
            ],
            $sqlWhere
        );


        return [$total, $list];
    }

}


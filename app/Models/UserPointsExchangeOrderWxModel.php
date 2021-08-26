<?php
/**
 * 积分兑换红包记录表
 */
namespace App\Models;

use App\Libs\Util;
use App\Models\Dss\DssStudentModel;

class UserPointsExchangeOrderWxModel extends Model
{
    public static $table = 'user_points_exchange_order_wx';

    // 发放状态 - 保持和ErpEventTaskAward表发放状态保持一致，方便后期整合数据
    const STATUS_DISABLED  = 0; // 不发放
    const STATUS_WAITING   = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE      = 3; // 发放成功
    const STATUS_GIVE_ING  = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    const STATUS_DICT = [
        self::STATUS_DISABLED => '不发放',
        self::STATUS_WAITING => '待发放',
        self::STATUS_REVIEWING => '审核中',
        self::STATUS_GIVE => '发放成功',
        self::STATUS_GIVE_ING => '发放中',
        self::STATUS_GIVE_FAIL => '发放失败',
    ];

    /**
     * 更新状态 - 作废
     * @param $id
     * @return int|null
     */
    public static function updateStatusDisabled($id, $statusCode)
    {
        return self::updateRecord($id, ['status' => self::STATUS_DISABLED, 'status_code' => $statusCode]);
    }

    /**
     * 更新状态 - 失败
     * @param $id
     * @return int|null
     */
    public static function updateStatusFailed($id, $statusCode)
    {
        return self::updateRecord($id, ['status' => self::STATUS_GIVE_FAIL, 'status_code' => $statusCode]);
    }

    /**
     * 获取积分兑换红包记录列表
     * @param $params
     * @param $page
     * @param $count
     * @param array $order
     * @return array[]
     */
    public static function getList($params, $page, $count, array $order = []): array
    {
        $studentTable = DssStudentModel::getTableNameWithDb();
        $pointsTable = self::getTableNameWithDb();
        $returnList = ['list' => [], 'total_count' => 0];
        $where = ' where 1=1 ';
        $whereUuid = [];
        $joinTable = '';
        if (!empty($params['student_uuid'])) {
            $whereUuid[] = $params['student_uuid'];
        }
        if (!empty($params['student_mobile'])) {
            $studentList = DssStudentModel::getRecords(['mobile' => $params['student_mobile']], ['uuid']);
            $whereUuid = array_merge(array_column($studentList, 'uuid'), $whereUuid);
        }
        if (!empty($whereUuid)) {
            $where .= " AND {$pointsTable}.uuid' in ('" . implode("','", $whereUuid) . "')";
        }
        if (!empty($params['reviewer_id'])) {
            $where .= " AND {$pointsTable}.operator_id={$params['reviewer_id']}";
        }
        if (isset($params['award_status']) && !Util::emptyExceptZero($params['award_status'])) {
            $where .= " AND {$pointsTable}.status={$params['award_status']}";
        }
        if (!empty($params['s_create_time'])) {
            $where .= " AND {$pointsTable}.create_time>={$params['s_create_time']}";
        }
        if (!empty($params['e_create_time'])) {
            $where .= " AND {$pointsTable}.create_time<{$params['e_create_time']}";
        }
        if (!empty($params['assistant_ids'])) {
            $joinTable .= " LEFT JOIN {$studentTable} ON {$studentTable}.id={$pointsTable}.user_id";
            array_walk($params['assistant_ids'], function (&$id) {
                $id = intval($id);
            });
            $where .= " AND {$studentTable}.assistant_id in(" . implode(",", $params['assistant_ids']) . ")";
        }
        if (empty($where)) {
            return $returnList;
        }
        $db = self::dbRO();

        $totalCount = $db->queryAll("SELECT COUNT(*) as count FROM {$pointsTable} {$joinTable} {$where}");
        $returnList['total_count'] = $totalCount[0]['count'] ?? 0;
        if ($returnList['total_count'] <= 0) {
            return $returnList;
        }
        $returnList['total_count'] = $totalCount[0]['count'] ?? 0;

        $offset = ($page - 1) * $count;

        if (!empty($order)) {
            foreach ($order as $_k => $_v) {
                if (empty($sqlOrder)) {
                    $sqlOrder =  $_k . ' ' . $_v;
                } else {
                    $sqlOrder .= ',' . $_k . ' ' . $_v;
                }
            }
        } else {
            $sqlOrder =" {$pointsTable}.id desc";
        }
        $list = $db->queryAll("SELECT {$pointsTable}.* FROM {$pointsTable} {$joinTable} {$where} ORDER BY {$sqlOrder} LIMIT {$offset},{$count}");
        $returnList['list'] = is_array($list) ? $list : [];
        return $returnList;
    }
}

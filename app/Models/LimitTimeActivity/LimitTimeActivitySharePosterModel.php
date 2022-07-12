<?php
/**
 * 限时活动参与记录
 */

namespace App\Models\LimitTimeActivity;

use App\Libs\MysqlDB;
use App\Models\Model;

class LimitTimeActivitySharePosterModel extends Model
{
    public static $table = 'limit_time_activity_share_poster';

    /**
     * 搜索参与记录
     * @param int $appId
     * @param string $studentUuId
     * @param array $params
     * @param int $page
     * @param int $limit
     * @return array
     */
    public static function searchJoinRecords(
        int $appId,
        string $studentUuId,
        array $params,
        int $page = 1,
        int $limit = 10
    ): array {
        if (!empty($appId)) {
            $where['app_id'] = $appId;
        }
        if (!empty($studentUuId)) {
            $where['student_uuid'] = $studentUuId;
        }
        if (!empty($params['activity_id'])) {
            $where['activity_id'] = $params['activity_id'];
        }
        if (!empty($params['verify_status'])) {
            $where['verify_status'] = $params['verify_status'];
        }
        if (!empty($params['award_type'])) {
            $where['award_type'] = $params['award_type'];
        }
        if (!empty($params['order'])) {
            $where['order'] = $params['order'];
        }
        if (!empty($params['group'])) {
            $where['GROUP'] = $params['group'];
        }
        if ($page > 0) {
            $where['LIMIT'] = [($page - 1) * $limit, $limit];
        }
        //获取数据
        $db = MysqlDB::getDB();
        $total = $db->count(self::$table, $where);
        if ($total <= 0) {
            return [[], 0];
        }
        $list = $db->select(self::$table, '*', $where);
        return [$list, $total];
    }
}

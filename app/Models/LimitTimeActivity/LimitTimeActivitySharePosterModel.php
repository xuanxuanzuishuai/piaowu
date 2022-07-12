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
     * @param array $studentUuId
     * @param array $params
     * @param int $page
     * @param int $limit
     * @param array $fields
     * @return array
     */
    public static function searchJoinRecords(
        int   $appId,
        array $studentUuId,
        array $params,
        int   $page = 1,
        int   $limit = 10,
        array $fields = []
    ): array
    {
        if (!empty($appId)) {
            $where['sp.app_id'] = $appId;
        }
        if (!empty($studentUuId)) {
            $where['sp.student_uuid'] = $studentUuId;
        }
        if (!empty($params['activity_id'])) {
            $where['sp.activity_id'] = $params['activity_id'];
        }
        if (!empty($params['verify_status'])) {
            $where['sp.verify_status'] = $params['verify_status'];
        }
        if (!empty($params['award_type'])) {
            $where['sp.award_type'] = $params['award_type'];
        }
        if (!empty($params['start_time'])) {
            $where['sp.create_time[>=]'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where['sp.create_time[<=]'] = strtotime($params['end_time']);
        }
        if (!empty($params['order'])) {
            $where['ORDER'] = $params['order'];
        }
        if (!empty($params['group'])) {
            $where['GROUP'] = $params['group'];
        }
        if ($page > 0) {
            $where['LIMIT'] = [($page - 1) * $limit, $limit];
        }
        // 搜索条件不能为空
        if (empty($where)) {
            return [[], 0];
        }
        //获取数据
        $db = MysqlDB::getDB();
        $total = $db->count(self::$table . '(sp)', $where);
        if ($total <= 0) {
            return [[], 0];
        }
        $fields = array_merge(
            [
                'sp.id',
                'sp.activity_id',
                'sp.image_path',
                'sp.image_path',
                'sp.student_uuid',
                'sp.verify_status',
                'sp.verify_time',
                'sp.verify_user',
            ],
            $fields
        );
        $list = $db->select(
            self::$table . '(sp)',
            [
                "[>]" . LimitTimeActivityModel::$table . '(a)' => ['sp.activity_id' => 'activity_id']
            ],
            $fields,
            $where);
        return [$list, $total];
    }
}

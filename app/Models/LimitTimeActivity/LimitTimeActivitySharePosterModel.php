<?php
/**
 * 限时活动参与记录
 */

namespace App\Models\LimitTimeActivity;

use App\Libs\MysqlDB;
use App\Models\Model;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;

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
        int $appId,
        array $studentUuId,
        array $params,
        int $page = 1,
        int $limit = 10,
        array $fields = []
    ): array {
        $where = [];
        if (!empty($params['id'])) {
            $where['sp.id'] = $params['id'];
        }
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
        if (!empty($params['task_num'])) {
            $where['sp.task_num'] = $params['task_num'];
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
        $db    = MysqlDB::getDB();
        $total = $db->count(self::$table . '(sp)', $where);
        if ($total <= 0) {
            return [[], 0];
        }
        $fields = array_merge(
            [
                'sp.id',
                'sp.activity_id',
                'sp.image_path',
                'sp.student_uuid',
                'sp.verify_status',
                'sp.verify_time',
                'sp.verify_user',
                'sp.verify_reason',
                'sp.task_num',
                'sp.create_time',
                'sp.send_award_status',
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

    /**
     * 获取活动中用户审核通过数量
     * @param $studentUUID
     * @param $activityId
     * @return int|number
     */
    public static function getActivityVerifyPassNum($studentUUID, $activityId)
    {
        return self::getCount([
            'student_uuid'    => $studentUUID,
            'activity_id'   => $activityId,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
        ]);
    }

    /**
     * 更新发送状态为奖励发放成功
     * @param $recordId
     * @return int|null
     */
    public static function updateSendAwardStatusIsSuccess($recordId)
    {
        return self::updateRecord($recordId, [
            'send_award_status' => OperationActivityModel::SEND_AWARD_STATUS_GIVE,
            'send_award_time' => time(),
        ]);
    }
}
<?php
/**
 * 清晨用户分享海报表
 */

namespace App\Models;

class MorningSharePosterModel extends Model
{
    public static $table = 'morning_share_poster';

    /**
     * 获取学生清晨5日打卡上传截图记录
     * @param       $studentUuid
     * @param array $verifyStatus
     * @param null $taskNum
     * @return array
     */
    public static function getFiveDayUploadSharePosterList($studentUuid, $verifyStatus = [], $taskNum = null)
    {
        $where = [
            'student_uuid'  => $studentUuid,
            'activity_type' => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
            'ORDER'         => ['id' => 'DESC'],
        ];
        if (!empty($verifyStatus)) {
            $where['verify_status'] = $verifyStatus;
        }
        !empty($taskNum) && $where['task_num'] = $taskNum;
        $list = self::getRecords($where);
        return is_array($list) ? $list : [];
    }

    /**
     * 获取学生清晨5日打卡指定天的上传截图记录
     * @param       $studentUuid
     * @param       $day
     * @return array
     */
    public static function getFiveDayUploadSharePosterByTask($studentUuid, $day)
    {
        $where = [
            'student_uuid'  => $studentUuid,
            'activity_type' => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
            'task_num'      => $day,
            'ORDER'         => ['id' => 'DESC'],
        ];
        $list = self::getRecords($where);
        return is_array($list) ? $list : [];
    }

    /**
     * 搜索列表
     * @param $params
     * @param $page
     * @param $count
     * @param $order
     * @param $fields
     * @return array
     */
    public static function searchList($params, $page, $count, $order = 'sp.id DESC', $fields = [])
    {
        $where = '1=1';
        if (!empty($params['student_uuid'])) {
            $studentUuid = is_array($params['student_uuid']) ? implode("','", $params['student_uuid']) : $params['student_uuid'];
            $where .= " and sp.student_uuid in ('" . $studentUuid . "')";
        }
        !empty($params['create_time_start']) && $where .= ' and sp.create_time>=' . $params['create_time_start'];
        !empty($params['create_time_end']) && $where .= ' and sp.create_time<=' . $params['create_time_end'];
        !empty($params['task_num']) && $where .= ' and sp.task_num=' . $params['task_num'];
        !empty($params['verify_status']) && $where .= ' and sp.verify_status=' . $params['verify_status'];
        $db = self::dbRO();
        $spTable = self::getTableNameWithDb() . ' sp';
        $eTable = EmployeeModel::getTableNameWithDb() . ' e';

        $total = $db->queryAll("SELECT count(*) as count FROM " . $spTable . " WHERE " . $where);
        if (empty($total[0]['count'])) {
            return [0, []];
        }
        $offset = ($page - 1) * $count;
        $where .= " ORDER BY " . $order;
        $where .= " LIMIT " . $offset . "," . $count;
        $fields = implode(',', array_merge([
            'sp.*',
            "ifnull(e.name, '') verify_user_name",
        ], $fields));
        $list = $db->queryAll("SELECT " . $fields . " FROM " . $spTable .
            " left join " . $eTable . " on sp.verify_user=e.id" .
            " WHERE " . $where);
        return [$total[0]['count'], $list];
    }
}
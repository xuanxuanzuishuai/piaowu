<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 3:52 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Services\ReferralService;


class SharePosterModel extends Model
{
    public static $table = "share_poster";
    //审核状态 1待审核 2合格 3不合格
    const VERIFY_STATUS_WAIT = 1;
    const VERIFY_STATUS_QUALIFIED = 2;
    const VERIFY_STATUS_UNQUALIFIED = 3;

    //节点状态
    const NODE_STATUS_LOCK = 1;//待解锁
    const NODE_STATUS_ING = 2;//进行中
    const NODE_STATUS_VERIFY_ING = 3;//审核中
    const NODE_STATUS_VERIFY_UNQUALIFIED = 4;//审核不通过
    const NODE_STATUS_HAVE_SIGN = 5;//已打卡
    const NODE_STATUS_EXPIRED = 6;//已过期
    const NODE_STATUS_UN_PLAY = 7;//未练琴

    //海报类型：1上传打卡截图
    const TYPE_CHECKIN_UPLOAD = 1;

    // 每个班级学生相关打卡数据
    const CHECKIN_POSTER_TMP_DATA = 'CHECKIN_POSTER_';

    /**
     * 获取打卡活动节点截图数据
     * @param $studentId
     * @param $nodeId
     * @return array|null
     */
    public static function signInNodePoster($studentId, $nodeId)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                id,
                student_id,
                image_path,
                verify_status,
                verify_time,
                verify_reason,
                remark,
                ext ->> '$.node_id' AS node_id,
                ext ->> '$.valid_time' AS valid_time 
            FROM
                " . self::$table . " 
            WHERE
                student_id = " . $studentId . " 
                AND ext ->> '$.node_id' in( " . $nodeId . ")";
        return $db->queryAll($sql);

    }

    /**
     * 打卡截图列表
     * @param $params
     * @return array
     */
    public static function posterList($params)
    {
        if ($params['type'] == self::TYPE_CHECKIN_UPLOAD) {
            return self::getCheckinPosterList($params);
        }
    }

    /**
     * 打卡上传截图列表
     * @param $params
     * @return array
     */
    private static function getCheckinPosterList($params)
    {
        $where = " WHERE sp.type = :type ";
        $map = [':type' => $params['type'] ?? self::TYPE_CHECKIN_UPLOAD];
        if (!empty($params['student_name'])) {
            $where .= " AND s.name like :student_name ";
            $map[':student_name'] = "%" . $params['student_name'] . "%";
        }
        if (!empty($params['student_mobile'])) {
            $where .= " AND s.mobile = :student_mobile ";
            $map[':student_mobile'] = $params['student_mobile'];
        }
        if (!empty($params['task_id'])) {
            $where .= " AND erp_t.event_task_id = :task_id ";
            $map[':task_id'] = $params['task_id'];
        }
        if (!empty($params['start_time'])) {
            $where .= " AND sp.create_time >= :start_time";
            $map[':start_time'] = $params['start_time'];
        }
        if (!empty($params['end_time'])) {
            $where .= " AND sp.create_time <= :end_time";
            $map[':end_time'] = $params['end_time'];
        }
        if (!empty($params['poster_status'])) {
            $where .= " AND sp.verify_status = :poster_status ";
            $map[':poster_status'] = $params['poster_status'];
        }
        if (!empty($params['day'])) {
            $where .= " AND sp.ext->>'$.node_order' = :day";
            $map[':day'] = $params['day'];
        }
        
        $sp = self::getTableNameWithDb();
        $s  = DssStudentModel::getTableNameWithDb();
        $e  = DssEmployeeModel::getTableNameWithDb();
        $erp_a = ErpUserEventTaskAwardModel::getTableNameWithDb();
        $erp_t = ErpUserEventTaskModel::getTableNameWithDb();
        $join = " INNER JOIN $s s ON s.id = sp.student_id ";
        $join .= " LEFT JOIN $erp_a erp_a ON erp_a.id = sp.award_id ";
        $join .= " LEFT JOIN $erp_t erp_t ON erp_t.id = erp_a.uet_id ";
        $join .= " LEFT JOIN $e e ON e.id = sp.verify_user ";
        $db = self::dbRO();
        $totalCount = $db->queryAll("SELECT count(sp.id) count FROM $sp sp $join $where ", $map);
        $totalCount = $totalCount[0]['count'] ?? 0;
        if ($totalCount == 0) {
            return [[], 0];
        }
        $sql = "
        SELECT
            sp.id,
            sp.student_id,
            sp.activity_id,
            sp.image_path,
            sp.verify_status poster_status,
            sp.create_time,
            sp.verify_time,
            sp.verify_user,
            sp.verify_reason,
            sp.remark,
            sp.ext->>'$.node_order' node_order,
            sp.ext->>'$.valid_time' valid_time,
            sp.type,
            s.name student_name,
            s.mobile,
            e.name operator_name
        FROM
        $sp sp ";

        $order = " ORDER BY id DESC ";
        $limit = Util::limitation($params['page'], $params['count']);
        $sql = $sql . $join . $where . $order . $limit;
        $posters = $db->queryAll($sql, $map);
        return [$posters, $totalCount];
    }

    /**
     * 获取海报信息
     * @param $posterIds
     * @return array|null
     */
    public static function getPostersByIds($posterIds)
    {
        $sp = self::getTableNameWithDb();
        $s  = DssStudentModel::getTableNameWithDb();
        $uw = DssUserWeiXinModel::getTableNameWithDb();
        $sql = "
        SELECT
            sp.id,
            sp.student_id,
            sp.activity_id,
            sp.verify_status poster_status,
            sp.award_id,
            sp.ext->>'$.valid_time' valid_time,
            sp.ext->>'$.node_order' day,
            uw.open_id,
            s.uuid,
            s.mobile
        FROM
            $sp sp
        INNER JOIN $s s ON s.id = sp.student_id
        LEFT JOIN $uw uw ON uw.user_id = sp.student_id
            AND uw.user_type = " . DssUserWeiXinModel::USER_TYPE_STUDENT . "
            AND uw.status = " . DssUserWeiXinModel::STATUS_NORMAL . "
            AND uw.busi_type = " . DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER . "
            AND uw.app_id = " . Constants::SMART_APP_ID . "
        WHERE sp.id in ( " . implode(',', $posterIds) . " )
            AND sp.verify_status = " . self::VERIFY_STATUS_WAIT . "
            AND sp.type = " . self::TYPE_CHECKIN_UPLOAD;
        $db = self::dbRO();
        return $db->queryAll($sql);
    }

    /**
     * 获取学生练琴百分比数据
     * @param $collectionId
     * @param $userId
     * @param int $day
     * @param int $duration
     * @return int|mixed
     */
    public static function getUserCheckInPercent($collectionId, $userId, $day = 0, $duration = 0)
    {
        if (empty($collectionId) || empty($userId)) {
            return 0;
        }
        $redis = RedisDB::getConn();
        $data = $redis->hget(self::CHECKIN_POSTER_TMP_DATA . $collectionId, $userId . '_' . $day);
        if (!empty($data)) {
            $data = json_decode($data, true);
            return $data['percent'] ?? 0;
        }
        $percent = ReferralService::getRandScore($duration);
        $jsonData = json_encode(['percent' => $percent]);
        $redis->hset(self::CHECKIN_POSTER_TMP_DATA . $collectionId, $userId . '_' . $day, $jsonData);
        $redis->expire(self::CHECKIN_POSTER_TMP_DATA . $collectionId, Util::TIMESTAMP_ONEWEEK);
        return $percent;
    }
}
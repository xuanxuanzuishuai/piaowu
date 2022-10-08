<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-09-02 11:19:55
 * Time: 3:52 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserWeiXinModel;

class RealSharePosterModel extends Model
{
    public static $table = "real_share_poster";
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

    // 海报类型：
    // 1上传打卡截图
    // 2上传截图返现
    // 3周周领奖
    const TYPE_CHECKIN_UPLOAD = 1;
    const TYPE_RETURN_CASH = 2;
    const TYPE_WEEK_UPLOAD = 3;

    // 每个班级学生相关打卡数据
    const CHECKIN_POSTER_TMP_DATA = 'CHECKIN_POSTER_';

    //系统审核code
    const SYSTEM_REFUSE_CODE_NEW    = -1; //未使用最新海报
    const SYSTEM_REFUSE_CODE_TIME   = -2; //朋友圈保留时长不足12小时，请重新上传
    const SYSTEM_REFUSE_CODE_GROUP  = -3; //分享分组可见
    const SYSTEM_REFUSE_CODE_FRIEND = -4; //请发布到朋友圈并截取朋友圈照片
    const SYSTEM_REFUSE_CODE_UPLOAD = -5; //上传截图出错
    const SYSTEM_REFUSE_CODE_USER = -6; //海报生成和上传非同一用户
    const SYSTEM_REFUSE_CODE_ACTIVITY_ID = -7; //海报生成和上传非同一活动
    const SYSTEM_REFUSE_CODE_UNIQUE_USED = -8; //作弊码已经被使用
    const SYSTEM_REFUSE_CODE_COMMENT = -9; //分享无分享语
    const SYSTEM_REFUSE_CODE_UNIQUE    = -10; //作弊码识别失败

    //系统审核拒绝原因code
    const SYSTEM_REFUSE_REASON_CODE_NEW    = 2; //未使用最新海报
    const SYSTEM_REFUSE_REASON_CODE_COMMENT = 4; //分享无分享语
    const SYSTEM_REFUSE_REASON_CODE_TIME   = 12; //朋友圈保留时长不足12小时，请重新上传
    const SYSTEM_REFUSE_REASON_CODE_GROUP  = 1; //分享分组可见
    const SYSTEM_REFUSE_REASON_CODE_FRIEND = 11; //请发布到朋友圈并截取朋友圈照片
    const SYSTEM_REFUSE_REASON_CODE_UPLOAD = 3; //上传截图出错
    const SYSTEM_REFUSE_REASON_CODE_USER = 13; //海报生成和上传非同一用户
    const SYSTEM_REFUSE_REASON_CODE_ACTIVITY_ID = 14; //海报生成和上传非同一活动
    const SYSTEM_REFUSE_REASON_UNIQUE_USED = 15; //作弊码已经被使用
    const SYSTEM_REFUSE_REASON_CODE_UNIQUE = 16; //作弊码识别失败
    
    /**
     * 获取海报信息
     * @param $posterIds
     * @param int $type
     * @return array|null
     */
    public static function getPostersByIds($posterIds, $type = self::TYPE_WEEK_UPLOAD)
    {
        $sp = self::getTableNameWithDb();
        $s  = ErpStudentModel::getTableNameWithDb();
        $ac = OperationActivityModel::getTableNameWithDb();
        $sql = "
        SELECT
            sp.id,
            sp.student_id,
            sp.activity_id,
            sp.verify_status poster_status,
            sp.ext->>'$.valid_time' valid_time,
            sp.ext->>'$.node_order' day,
            sp.create_time,
            sp.type,
            sp.task_num,
            ac.name activity_name,
            s.uuid,
            s.mobile
        FROM
            $sp sp
        INNER JOIN $s s ON s.id = sp.student_id 
        LEFT JOIN $ac ac ON ac.id = sp.activity_id 
        WHERE sp.id in ( " . implode(',', $posterIds) . " )
            AND sp.verify_status = " . self::VERIFY_STATUS_WAIT . "
            AND sp.type = " . $type;
        $db = self::dbRO();
        return $db->queryAll($sql);
    }
    
    /**
     * 截图列表
     * @param $params
     * @return array
     */
    public static function getPosterList($params)
    {
        $where = " WHERE 1 = 1 ";
        $map = [];
        if (!empty($params['activity_id'])) {
            $where .= " AND sp.activity_id = :activity_id ";
            $map[':activity_id'] = $params['activity_id'];
        }
        if (!empty($params['poster_status'])) {
            $where .= " AND sp.verify_status = :poster_status ";
            $map[':poster_status'] = $params['poster_status'];
        }
        if (!empty($params['start_time'])) {
            $where .= " AND sp.create_time >= :start_time";
            $map[':start_time'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where .= " AND sp.create_time <= :end_time";
            $map[':end_time'] = strtotime($params['end_time']);
        }
        if (!empty($params['type'])) {
            $where .= " AND sp.type = :type";
            $map[':type'] = $params['type'];
        }
        if (!empty($params['student_mobile'])) {
            $where .= " AND s.mobile = :mobile ";
            $map[':mobile'] = $params['student_mobile'];
        }
        if (!empty($params['student_name'])) {
            $where .= " AND s.name LIKE '%{$params['student_name']}%' ";
        }
        if (!empty($params['uuid'])) {
            $where .= " AND s.uuid = :uuid ";
            $map[':uuid'] = $params['uuid'];
        }
        if (!empty($params['id'])) {
            $where .= " AND sp.id = :id ";
            $map[':id'] = $params['id'];
        }
        if (!empty($params['assistant_ids']) && is_array($params['assistant_ids'])) {
            array_walk($params['assistant_ids'], function (&$val) {
                $val = intval($val);
            });
            $where .= " AND s.assistant_id in (" . implode(',', $params['assistant_ids']) . ") ";
        }
        $s = ErpStudentModel::getTableNameWithDb();
        $ac = OperationActivityModel::getTableNameWithDb();
        $e = EmployeeModel::getTableNameWithDb();
        $sp = self::getTableNameWithDb();
        $join = "
            LEFT JOIN {$s} s ON s.id = sp.student_id
            STRAIGHT_JOIN {$ac} ac ON ac.id = sp.activity_id
            LEFT JOIN {$e} e ON e.id = sp.verify_user
        ";
        $db = self::dbRO();
        $totalCount = $db->queryAll(
            "SELECT count(sp.id) count FROM $sp sp $join $where ",
            $map
        );
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }
        
        $sql = "
        SELECT
            sp.id,
            sp.student_id,
            sp.activity_id,
            sp.image_path img_url,
            sp.verify_status poster_status,
            sp.create_time,
            sp.verify_time check_time,
            sp.verify_user operator_id,
            sp.verify_reason reason,
            sp.remark,
            sp.type,
            s.name student_name,
            s.mobile,
            s.uuid,
            ac.name activity_name,
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
     * 获取上传截图历史记录
     * @param $data
     * @param $page
     * @param $count
     * @return array
     */
    public static function getSharePosterHistory($data, $page, $count)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        $where = [
        ];
        if (!empty($data['student_id'])) {
            $where['student_id'] = $data['student_id'];
        }

        if (!empty($data['activity_id'])) {
            $where['activity_id'] = $data['activity_id'];
        }

        $returnData['total_count'] = self::getCount($where);
        if (empty($returnData['total_count'])) {
            return $returnData;
        }

        // 读取具体信息
        $where['LIMIT'] = [($page-1)*$count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        $returnData['list'] = self::getRecords($where);
        return $returnData;
    }

    /**
     * 查询用户参与记录：活动和分享任务次数分组
     * @param $studentId
     * @param $activityIds
     * @return array|null
     */
    public static function getSharePosterHistoryGroupActivityIdAndTaskNum($studentId, $activityIds)
    {
        $sql = "SELECT
                    	tmp.*,
                        3001 as award_type,
                        ms.award_amount,
                        ms.award_status 
                FROM
                    (
                    SELECT
                        id,
                        activity_id,
                        student_id,
                        task_num,
                        verify_status,
                        create_time,
                        dense_rank() over ( PARTITION BY activity_id, task_num ORDER BY id DESC ) AS upload_order 
                    FROM
                        " . self::$table . " 
                    WHERE
                        student_id = " . $studentId . " 
                        AND
                        activity_id in (".implode(',', $activityIds).")
                    ) AS tmp 
                    LEFT join ".RealUserAwardMagicStoneModel::$table." AS ms ON
                        tmp.student_id = ms.user_id 
                        AND tmp.activity_id = ms.activity_id 
                        AND tmp.task_num = ms.passes_num 
                WHERE
                    tmp.upload_order = 1";
        $db = MysqlDB::getDB();
        $data = $db->queryall($sql);
        return $data;
    }

    /**
     * 获取上传截图的活动
     * @param $studentId
     * @return array
     */
    public static function getStudentJoinActivityList($studentId)
    {
        $where = [
            'student_id' => $studentId,
            'GROUP' => ['activity_id'],
            'ORDER' => ['activity_id' => 'DESC'],
        ];
        $data = self::getRecords($where, ['activity_id']);
        return empty($data) ? [] : array_column($data, 'activity_id');
    }
}

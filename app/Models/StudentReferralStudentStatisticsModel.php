<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/13
 * Time: 19:52
 */


namespace App\Models;


use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentCouponV1Model;

class StudentReferralStudentStatisticsModel extends Model
{
    public static $table = 'student_referral_student_statistics';
    // 进度:0注册 1体验 2年卡(定义与代理转介绍学生保持一致)
    const STAGE_REGISTER = AgentUserModel::STAGE_REGISTER;
    const STAGE_TRIAL = AgentUserModel::STAGE_TRIAL;
    const STAGE_FORMAL = AgentUserModel::STAGE_FORMAL;

    /**
     * 获取推荐人指定起始时间到当前的指定数量的推荐人
     * @param $refereeId
     * @param $stage
     * @param $createTime
     * @param $limit
     * @return array
     */
    public static function getStudentList ($refereeId, $stage, $createTime, $limit) {
        $db = MysqlDB::getDB();
        $statisTable = StudentReferralStudentStatisticsModel::$table;
        $detailTable = StudentReferralStudentDetailModel::$table;
        $list = $db->select(
            $statisTable,
            [
                "[>]" . $detailTable  => ['student_id' => 'student_id'],
            ],
            [
                $detailTable.'.id',
                $detailTable.'.student_id',
            ],
            [
                $statisTable . '.referee_id' => $refereeId,
                $detailTable . '.stage' => $stage,
                $detailTable . '.create_time[>=]' => $createTime,
                'ORDER' => [$detailTable.'.create_time' => 'ASC'],
                'LIMIT' => $limit,
            ]
        );
        return $list;
    }

    /**
     * 获取助教下领取Rt学员优惠券的转介绍学员数量
     * 当前Rt学员优惠券未过期+当前阶段为付费体验课（有可用的优惠券，未付费的学员）
     * @param array $assistantIds 助教id
     * @return int|mixed
     */
    public static function getAssistantStudentCount(array $assistantIds)
    {
        $time         = time();
        $db           = self::dbRO();
        $refTable     = self::getTableNameWithDb();
        $studentTable = DssStudentModel::getTableNameWithDb();
        $couponTable  = ErpStudentCouponV1Model::getTableNameWithDb();
        $recordTable  = RtCouponReceiveRecordModel::getTableNameWithDb();
        $sql          = "SELECT count(1) as total_count from " . $refTable . " as r" .
            " INNER JOIN " . $studentTable . " as s ON s.id=r.student_id" .
            " INNER JOIN " . $recordTable . " as rr ON rr.receive_uuid = s.uuid" .   // Rt学员
            " INNER JOIN " . $couponTable . " as c ON c.id=rr.student_coupon_id" .
            " WHERE c.expired_end_time>=" . $time . " AND c.status = 1" .        // 有可用优惠券并且未过期
            " AND s.has_review_course=" . DssStudentModel::REVIEW_COURSE_49 .     // 当前是体验卡或体验卡过期身份
            " AND s.assistant_id in(" . implode(',', $assistantIds) . ")";  // 指定助教
        $res = $db->queryAll($sql);

        return $res[0]['total_count'] ?? 0;
    }

    /**
     * 转介绍学员列表，包括rt活动优惠券信息
     * @param $where
     * @param int[] $limit
     * @param array $fields
     * @return array
     */
    public static function getStudentReferralList($where, $limit = [0, 20], array $fields = [])
    {
        $db           = self::dbRO();
        $refTable     = self::getTableNameWithDb() . " as r ";
        $studentTable = DssStudentModel::getTableNameWithDb() . " as s ";
        $studentRTable = DssStudentModel::getTableNameWithDb() . " as s_r ";
        $couponTable  = ErpStudentCouponV1Model::getTableNameWithDb() . " as c ";
        $recordTable  = RtCouponReceiveRecordModel::getTableNameWithDb() . " as rr ";

        if (empty($fileds)) {
            $fields = [
                "r.id",
                "r.student_id",
                "r.referee_id",
                "r.activity_id",
                "r.buy_channel",
                "r.buy_channel",
                "r.create_time",
                "r.last_stage",
                'c.expired_start_time as coupon_expired_start_time',
                'c.expired_end_time as coupon_expired_end_time',
                'c.status as coupon_status',
                'rr.employee_uid',
                'rr.employee_uuid',
                // 推荐人、被推荐人 姓名和uuid这里sql确定不了
                // 课管也确定不了
            ];
        }

        $sqlWhere = ' WHERE 1=1';

        // 学生id
        if (!empty($where['student_id'])) {
            if (is_array($where['student_id'])) {
                $sqlWhere .= ' AND r.student_id in (' . implode(',', $where['student_id']) . ')';
            } else {
                $sqlWhere .= ' AND r.student_id=' . $where['student_id'];
            }
        }
        // 推荐人id
        if (!empty($where['referee_id'])) {
            if (is_array($where['referee_id'])) {
                $sqlWhere .= ' AND r.referee_id in (' . implode(',', $where['referee_id']) . ')';
            } else {
                $sqlWhere .= ' AND r.referee_id=' . $where['referee_id'];
            }
        }
        //转介绍活动id
        if (!empty($where['activity_id'])) {
            if (is_array($where['activity_id'])) {
                $sqlWhere .= ' AND r.activity_id in (' . implode(',', $where['activity_id']) . ')';
            } else {
                $sqlWhere .= ' AND r.activity_id=' . $where['activity_id'];
            }
        }
        // DSS分享海报的员工
        if (!empty($where['share_employee_uuid'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            $joinSql[md5($recordTable)]  = " INNER JOIN " . $recordTable . " ON rr.receive_uid = s.id";
            if (is_array($where['share_employee_uuid'])) {
                $uuidStr  = "'" . implode("','", $where['share_employee_uuid']) . "'";
                $sqlWhere .= ' AND rr.employee_uuid in (' . $uuidStr . ')';
            } else {
                $sqlWhere .= ' AND r.employee_uuid=' . $where['share_employee_uuid'];
            }
        }
        // 推荐人初次购买时间
        if (!empty($where['ref_first_buy_year_s_create_time'])) {
            $sqlWhere .= ' AND s_r.pay_vip_time >=' . $where['ref_first_buy_year_s_create_time'];
        }
        if (!empty($where['ref_first_buy_year_e_create_time'])) {
            $sqlWhere .= ' AND s_r.pay_vip_time <=' . $where['ref_first_buy_year_e_create_time'];
        }
        if (!empty($where['ref_first_buy_year_s_create_time']) || !empty($where['ref_first_buy_year_e_create_time'])) {
            $joinSql[md5($studentRTable)] = " INNER JOIN " . $studentRTable . " ON s_r.id=r.referee_id and s_r.has_review_course=" . DssStudentModel::REVIEW_COURSE_1980;
        }
        //当前阶段
        if (isset($where['has_review_course']) && !Util::emptyExceptZero($where['has_review_course'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            if (is_array($where['has_review_course'])) {
                $sqlWhere .= ' AND s.has_review_course in (' . implode(',', $where['has_review_course']) . ')';
            } else {
                $sqlWhere .= ' AND s.has_review_course=' . $where['has_review_course'];
            }
        }
        //转介绍标识
        if (isset($where['referral_sign']) && !Util::emptyExceptZero($where['referral_sign'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            $channelIds                  = array_values(DictConstants::getSet(DictConstants::RT_CHANNEL_CONFIG));
            if ($where['referral_sign'] == 1) {
                // 查领取额的优惠券
                $sqlWhere .= ' AND r.buy_channel in (' . implode(',', $channelIds) . ')';
            } else {
                // 查未领取的优惠券
                $sqlWhere .= ' AND r.buy_channel not in (' . implode(',', $channelIds) . ')';
            }
        }
        //优惠券过期时间
        if (!empty($where['coupon_expire_end_time'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            $joinSql[md5($recordTable)]  = " INNER JOIN " . $recordTable . " ON rr.receive_uid = s.id";
            $joinSql[md5($couponTable)]  = " INNER JOIN " . $couponTable . " ON c.id=rr.student_coupon_id";
            $sqlWhere                    .= ' AND c.expired_end_time<=' . $where['coupon_expire_end_time'];
        }
        if (!empty($where['coupon_expire_start_time'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            $joinSql[md5($recordTable)]  = " INNER JOIN " . $recordTable . " ON rr.receive_uid = s.id";
            $joinSql[md5($couponTable)]  = " INNER JOIN " . $couponTable . " ON c.id=rr.student_coupon_id";
            $sqlWhere                    .= ' AND c.expired_end_time>=' . $where['coupon_expire_start_time'];
        }

        //助教id
        if (!empty($where['assistant_id'])) {
            $joinSql[md5($studentTable)] = " INNER JOIN " . $studentTable . " ON s.id=r.student_id";
            $sqlWhere .= ' AND s.assistant_id in (' . $where['assistant_id'] . ')';
        }
        //课管id
        if (!empty($where['course_manage_id'])) {
            $joinSql[md5($studentRTable)] = " INNER JOIN " . $studentRTable . " ON s_r.id=r.referee_id";
            if (is_array($where['course_manage_id'])) {
                $sqlWhere .= ' AND s_r.course_manage_id in (' . implode(',', $where['course_manage_id']) . ')';
            } else {
                $sqlWhere .= ' AND s_r.course_manage_id =' . $where['course_manage_id'];
            }
        }
        // 转介绍关系创建时间
        if (!empty($where['create_time[>=]'])) {
            $sqlWhere .= ' AND r.create_time >=' . $where['create_time[>=]'];
        }
        if (!empty($where['create_time[<=]'])) {
            $sqlWhere .= ' AND r.create_time <=' . $where['create_time[<=]'];
        }

        $countSql = "SELECT COUNT(1) as total_count from " . $refTable;
        $totalCountSql = $countSql . implode(' ', $joinSql) . $sqlWhere;
        $totalCount    = $db->queryAll($totalCountSql);

        $returnData = [
            'total_count' => $totalCount[0]['total_count'] ?? 0,
            'list'        => [],
        ];
        if (empty($returnData['total_count'])) {
            return $returnData;
        }

        // 必要字段所需的left join, select count(*) 排除 left join
        !isset($joinSql[md5($studentTable)]) && $joinSql[md5($studentTable)] = " LEFT JOIN " . $studentTable . " ON s.id=r.student_id";
        !isset($joinSql[md5($recordTable)]) && $joinSql[md5($recordTable)]  = " LEFT JOIN " . $recordTable . " ON rr.receive_uid = s.id";
        !isset($joinSql[md5($couponTable)]) && $joinSql[md5($couponTable)]  = " LEFT JOIN " . $couponTable . " ON c.id=rr.student_coupon_id";

        $listSql  = "SELECT " . implode(',', $fields) . " from " . $refTable;
        $pageListSql = $listSql . implode(' ', $joinSql) . $sqlWhere . ' order by r.id desc limit ' . $limit[0] . ',' . $limit[1];
        $pageList  = $db->queryAll($pageListSql);

        $returnData['list'] = is_array($pageList) ? $pageList : [];
        return $returnData;
    }

    /**
     * 批量获取转介绍人数
     * @param      $refereeIds
     * @param null $activityId
     * @param null $refereeType
     * @return array|null
     */
    public static function getReferralCount($refereeIds, $activityId = null, $refereeType = null)
    {
        $refTable = self::getTableNameWithDb();
        $stuTable = DssStudentModel::getTableNameWithDb();
        $db = self::dbRO();
        if (is_array($refereeIds)) {
            $refereeIds = implode(',', $refereeIds);
        }
        $sql = " SELECT ref.`referee_id`, stu.uuid, count( 1 ) AS num
                FROM {$refTable} as ref
                left join " . $stuTable . " as stu on stu.id=ref.referee_id
                WHERE 1=1 ";
        if (!is_null($activityId)) {
            $sql .= " AND `activity_id` = {$activityId} ";
        }
        if (!is_null($refereeType)) {
            $sql .= " AND `referee_type` = " . StudentInviteModel::REFEREE_TYPE_STUDENT;
        }
        $sql .= " AND `referee_id` IN ({$refereeIds}) GROUP BY `referee_id`";
        return $db->queryAll($sql);
    }


    /**
     * 获取推荐人信息-按照推荐购买年卡数排序
     * @param int $limit
     * @return array|null
     */
    public static function getReferralBySort($limit = 20)
    {
        $db        = MysqlDB::getDB();
        $table     = self::$table;
        $lastStage = self::STAGE_FORMAL;

        $sql = " SELECT
                    `referee_id`,
                    count( 1 ) AS num 
                FROM {$table}
                WHERE
                `last_stage` = {$lastStage} 
                GROUP BY `referee_id` ORDER BY num DESC LIMIT {$limit}
        ";

        return $db->queryAll($sql);
    }

    /**
     * 获取推荐人和被推荐人，完成了某个阶段的人数数量
     * @param int $refereeId
     * @param int $stage
     * @return int
     */
    public static function getReferralCountGroupByStage(int $refereeId, int $stage):int
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table . ' (a)',
            [
                '[>]' . StudentReferralStudentDetailModel::$table . ' (b)' => ['a.student_id' => 'student_id']
            ],
            ['a.student_id'],
            [
                'a.referee_id' => $refereeId,
                'b.stage'      => $stage,
            ]);
    }
}

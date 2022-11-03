<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/4/17
 * Time: 11:37 PM
 */

namespace App\Models\Erp;

use App\Libs\Constants;

class ErpReferralUserRefereeModel extends ErpModel
{
    public static $table = 'referral_user_referee';
    const REFEREE_TYPE_STUDENT = 1; // 学生

    /**
     * 获取推荐人信息数据
     * @param array $ids
     * @return array|null
     */
    public static function getRefereeList($ids = [])
    {
        if (empty($ids)) {
            return [];
        }
        $db = self::dbRO();
        $s = ErpStudentModel::getTableNameWithDb();
        $rur = self::getTableNameWithDb();
        $sql = "
        SELECT
            %s
        FROM
            {$rur} rur
        %s
        WHERE
            %s
        LIMIT 0, 1000;
        ";
        $field = 'rur.referee_id, rur.referee_type, rur.user_id, s.uuid, r.uuid as r_uuid';
        $join  = " 
        INNER JOIN {$s} s on s.id = rur.user_id 
        INNER JOIN {$s} r on r.id = rur.referee_id 
        ";
        $where = "s.uuid in ('".implode("','", $ids)."')";
        return $db->queryAll(sprintf($sql, $field, $join, $where));
    }

    /**
     * 获取推荐人信息-按照推荐购买年卡数排序
     * @param numeric $limit
     * @return array
     */
    public static function getReferralBySort($limit = 20): array
    {
        $db              = self::dbRO();
        $table           = self::getTableNameWithDb();
        $studentAppTable = ErpStudentAppModel::getTableNameWithDb();
        $lastStage       = ErpStudentAppModel::STATUS_PAID;
        $appId           = Constants::REAL_APP_ID;
        $refereeType     = self::REFEREE_TYPE_STUDENT;

        $sql = 'SELECT r.`referee_id`,count( 1 ) AS num FROM ' . $table . ' as r' .
            ' INNER JOIN ' . $studentAppTable . ' as s ON s.student_id=r.referee_id AND s.status=' . $lastStage .
            ' WHERE r.app_id=' . $appId . ' AND r.referee_type=' . $refereeType .
            ' GROUP BY r.`referee_id` ORDER BY num DESC LIMIT ' . $limit;

        $list = $db->queryAll($sql);
        return is_array($list) ? $list : [];
    }

    /**
     * 获取学生转介绍关系信息
     * @param $studentId
     * @return array
     */
    public static function getStudentReferral($studentId): array
    {
        $arr =  ErpReferralUserRefereeModel::getRecord([
            'user_id'   => $studentId,
            'referee_type' => ErpReferralUserRefereeModel::REFEREE_TYPE_STUDENT,
            'app_id'       => Constants::REAL_APP_ID
        ]);
        return is_array($arr) ? $arr : [];
    }

    /**
     * 批量获取转介绍人数
     * @param $refereeIds
     * @param $limit
     * @return array|null
     */
    public static function getReferralCountList($refereeIds, $limit = 0)
    {
        $refTable = self::getTableNameWithDb();
        $stuTable = ErpStudentModel::getTableNameWithDb();
        $db = self::dbRO();
        if (is_array($refereeIds)) {
            $refereeIds = implode(',', $refereeIds);
        }
        $sql = 'select s.uuid,count(*) as num from ' . $refTable . ' as r' .
            ' left join ' . $stuTable . ' as s on s.id=r.referee_id' .
            ' where app_id='. Constants::REAL_APP_ID;
        $sql .= " AND `referee_id` IN ({$refereeIds}) GROUP BY `referee_id`";
        if (!empty($limit)) {
            $sql .= ' LIMIT 0,' . $limit;
        }
        return $db->queryAll($sql);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/4/17
 * Time: 11:37 PM
 */

namespace App\Models\Erp;


class ErpReferralUserRefereeModel extends ErpModel
{
    public static $table = 'referral_user_referee';

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
}
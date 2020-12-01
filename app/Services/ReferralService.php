<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeActivityModel;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\StudentInviteModel;

class ReferralService
{
    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getReferralList($params)
    {
        $where = ' where 1=1 ';
        $map = [];
        if (!empty($params['referral_mobile'])) {
            $where .= ' and r.mobile like :referral_mobile ';
            $map[':referral_mobile'] = "{$params['referral_mobile']}%";
        }
        if (!empty($params['mobile'])) {
            $where .= ' and s.mobile like :mobile ';
            $map[':mobile'] = "{$params['mobile']}%";
        }
        if (!empty($params['has_review_course'])) {
            $where .= ' and s.has_review_course = :has_review_course ';
            $map[':has_review_course'] = "{$params['has_review_course']}%";
        }
        if (!empty($params['s_create_time'])) {
            $where .= ' and si.create_time >= :s_create_time ';
            $map[':s_create_time'] = $params['s_create_time'];
        }
        if (!empty($params['e_create_time'])) {
            $where .= ' and si.create_time <= :e_create_time ';
            $map[':e_create_time'] = $params['e_create_time'];
        }
        if (!empty($params['channel_id'])) {
            $where .= ' and s.channel_id = :channel_id ';
            $map[':channel_id'] = $params['channel_id'];
        }
        if (!empty($params['activity'])) {
            $where .= ' and ea.name like :activity ';
            $map[':activity'] = "{$params['activity']}%";
        }
        if (!empty($params['employee_name'])) {
            $where .= ' and e.name like :employee_name ';
            $map[':employee_name'] = "{$params['employee_name']}%";
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $limit = Util::limitation($params['page'], $params['count']);

        $s  = DssStudentModel::getTableName();
        $si = StudentInviteModel::$table;
        $ea = EmployeeActivityModel::$table;
        $e  = EmployeeModel::$table;
        $c  = DssChannelModel::getTableName();
        
        $order = " ORDER BY si.create_time desc ";
        $countField = 'COUNT(s.id) as total';
        $field = "
            s.name,
            s.mobile,
            s.has_review_course,
            s.create_time,
            s.channel_id,
            si.activity_id,
            ea.name as activity_name,
            e.name as employee_name,
            si.referee_empoyee_id,
            si.referee_id,
            c.name as channel_name,
            r.mobile as referral_mobile
        ";
        $sql = "
        SELECT 
            %s
        FROM 
            $si si
        INNER JOIN $s s ON si.student_id = s.id
        INNER JOIN $s r on r.id = si.referee_id
        LEFT JOIN $ea ea ON ea.id = si.activity_id
        LEFT JOIN $e e ON e.id = si.referee_empoyee_id
        LEFT JOIN $c c ON s.channel_id = c.id
        {$where} {$order}
        ";
        $total   = MysqlDB::getDB()->queryAll(sprintf($sql, $countField), $map);
        $records = MysqlDB::getDB()->queryAll(sprintf($sql, $field) . " $limit", $map);
        foreach ($records as &$item) {
            $item = self::formatStudentInvite($item);
        }
        return [$records, $total[0]['total'] ?? 0];
    }

    public static function formatStudentInvite($item)
    {
        $hasReviewCourseSet = DictConstants::getSet(DictConstants::HAS_REVIEW_COURSE);
        $item['mobile_hidden'] = Util::hideUserMobile($item['mobile']);
        $item['referrer_mobile_hidden'] = Util::hideUserMobile($item['referral_mobile']);
        $item['has_review_course_show'] = $hasReviewCourseSet[$item['has_review_course']];
        $item['create_time_show'] = date('Y-m-d H:i', $item['create_time']);
        return $item;
    }

}

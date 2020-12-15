<?php
namespace App\Models\Dss;

use App\Libs\Constants;
use App\Libs\Util;
use App\Models\EmployeeActivityModel;
use App\Models\StudentInviteModel;

class DssStudentModel extends DssModel
{
    public static $table = 'student';

    // 点评课学生标记
    const REVIEW_COURSE_NO = 0; // 非点评课学生
    const REVIEW_COURSE_49 = 1; // 体验课课包
    const REVIEW_COURSE_1980 = 2; // 正式课课包

    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getInviteList($params)
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

        if (!empty($params['student_uuid'])){
            $where .= ' and s.uuid = :student_uuid ';
            $map[':student_uuid'] = "{$params['student_uuid']}";
        }

        if (!empty($params['referral_uuid'])){
            $where .= ' and r.uuid = :referral_uuid ';
            $map[':referral_uuid'] = "{$params['referral_uuid']}";
        }
        if (!Util::emptyExceptZero($params['has_review_course'])) {
            $where .= ' and s.has_review_course = :has_review_course ';
            $map[':has_review_course'] = "{$params['has_review_course']}";
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
            $map[':activity'] = "%{$params['activity']}%";
        }
        if (!empty($params['employee_name'])) {
            $where .= ' and e.name like :employee_name ';
            $map[':employee_name'] = "%{$params['employee_name']}%";
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $limit = Util::limitation($params['page'], $params['count']);

        $s  = self::$table;
        $si = StudentInviteModel::getTableNameWithDb();
        $ea = EmployeeActivityModel::getTableNameWithDb();
        $e  = DssEmployeeModel::$table;
        $c  = DssChannelModel::$table;

        $order = " ORDER BY si.create_time desc ";
        $countField = 'COUNT(s.id) as total';
        $field = "
            s.id as student_id,
            s.name as student_name,
            s.uuid as student_uuid,
            s.mobile,
            s.has_review_course,
            s.create_time,
            s.channel_id,
            si.activity_id,
            ea.name as activity_name,
            e.name as employee_name,
            si.referee_employee_id,
            si.referee_id,
            c.name as channel_name,
            r.mobile as referral_mobile,
            r.uuid as referrer_uuid,
            r.name as referrer_name,
            r.id as referral_student_id
        ";
        $sql = "
        SELECT 
            %s
        FROM 
            $si si
        INNER JOIN $s s ON si.student_id = s.id
        INNER JOIN $s r on r.id = si.referee_id
        LEFT JOIN $ea ea ON ea.id = si.activity_id
        LEFT JOIN $e e ON e.id = si.referee_employee_id
        LEFT JOIN $c c ON s.channel_id = c.id
        {$where} {$order}
        ";
        $db = self::dbRO();
        $total   = $db->queryAll(sprintf($sql, $countField), $map);
        $records = $db->queryAll(sprintf($sql, $field) . " $limit", $map);
        return [$records, $total];
    }

    /**
     * 根据班级ID获取学生
     * @param $collectionIds
     * @param false $withOpenId 是否带openid
     * @return array|null
     */
    public static function getByCollectionId($collectionIds, $withOpenId = false)
    {
        if (empty($collectionIds)) {
            return [];
        }
        $s = DssStudentModel::getTableNameWithDb();
        $wx = DssUserWeiXinModel::getTableNameWithDb();
        $c = DssCollectionModel::getTableNameWithDb();

        $field = "s.id,s.collection_id,s.thumb,s.name, c.teaching_start_time";
        $join  = "";
        $order = " ORDER BY s.id";
        $where = "";
        $where .= " s.collection_id in (" . implode(',', $collectionIds) . ")";
        $join .= " left join $c c on s.collection_id = c.id";
        if ($withOpenId) {
            $join .= " LEFT JOIN $wx wx ON wx.user_id = s.id ";
            $field .= ",wx.open_id";
            $where .= " AND wx.user_type = ".DssUserWeiXinModel::USER_TYPE_STUDENT;
            $where .= " AND wx.status = ".DssUserWeiXinModel::STATUS_NORMAL;
            $where .= " AND wx.app_id = ".Constants::SMART_APP_ID;
        }

        $sql = "
        SELECT
            {$field}
        FROM
            $s s
            {$join}
        WHERE
            {$where} {$order}
        ";
        return self::dbRO()->queryAll($sql);
    }
}
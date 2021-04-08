<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\OperationActivityModel;
use App\Models\StudentInviteModel;

class DssStudentModel extends DssModel
{
    public static $table = 'student';

    // 点评课学生标记
    const REVIEW_COURSE_NO = 0; // 非点评课学生
    const REVIEW_COURSE_49 = 1; // 体验课课包
    const REVIEW_COURSE_1980 = 2; // 正式课课包

    // 学生的状态
    const STATUS_REGISTER = 0;//注册
    const STATUS_BUY_TEST_COURSE = 1;//付费体验课
    const STATUS_BUY_TEST_COURSE_EXPIRED = 6; // 体验已过期
    const STATUS_BUY_NORMAL_COURSE = 2;//付费正式课
    const STATUS_UNBIND = 3;//未绑定微信
    const STATUS_BIND = 4;//已绑定微信
    const STATUS_HAS_EXPIRED = 5;//已过期

    // 订阅
    const SUB_STATUS_ON = 1;
    const SUB_STATUS_OFF = 0;

    const STUDENT_IDENTITY_ZH_MAP = [
        0 => '未付费', // 注册
        1 => '体验期', // 付费体验课
        2 => '年卡',  // 付费正式课
        3 => '未绑定微信',
        4 => '已绑定微信',
        5 => '年卡过期',
        6 => '体验期过期',
    ];

    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getInviteList($params)
    {
        $where = ' where si.referee_type=:referee_type';
        $map = [
            ':referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT
        ];
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
        if (!empty($params['task_id'])) {
            $where .= ' and erp_ut.event_task_id in (' . implode(',', $params['task_id']) . ')';
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
            $where .= ' and oa.name like :activity ';
            $map[':activity'] = "%{$params['activity']}%";
        }
        if (!empty($params['employee_name'])) {
            $where .= ' and e.name like :employee_name ';
            $map[':employee_name'] = "%{$params['employee_name']}%";
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $limit = Util::limitation($params['page'], $params['count']);

        $s  = self::getTableNameWithDb();
        $si = StudentInviteModel::getTableNameWithDb();
        $oa = OperationActivityModel::getTableNameWithDb();
        $e  = DssEmployeeModel::getTableNameWithDb();
        $c  = DssChannelModel::getTableNameWithDb();
        $erp_ut = ErpUserEventTaskModel::getTableNameWithDb();
        $erp_s = ErpStudentModel::getTableNameWithDb();

        $order = " ORDER BY invite_create_time desc ";
        $countField = 'COUNT(DISTINCT s.id) as total';
        $field = "
            s.id as student_id,
            s.name as student_name,
            s.uuid as student_uuid,
            s.mobile,
            s.has_review_course,
            s.create_time,
            s.channel_id,
            si.activity_id,
            oa.name as activity_name,
            e.name as employee_name,
            si.referee_employee_id,
            si.create_time as invite_create_time,
            si.referee_id,
            c.name as channel_name,
            r.mobile as referral_mobile,
            r.uuid as referrer_uuid,
            r.name as referrer_name,
            r.id as referral_student_id,
            erp_ut.event_task_id as max_task_id,
            ROW_NUMBER() OVER (PARTITION BY erp_ut.user_id ORDER BY erp_ut.event_task_id DESC) erp_ut_task_order
        ";
        $join = "
            INNER JOIN $s s ON si.student_id = s.id
            INNER JOIN $s r on r.id = si.referee_id
            LEFT JOIN $oa oa ON oa.id = si.activity_id
            LEFT JOIN $e e ON e.id = si.referee_employee_id
            LEFT JOIN $c c ON s.channel_id = c.id
            INNER JOIN $erp_s erp_s on erp_s.uuid = s.uuid
            INNER JOIN $erp_ut erp_ut on erp_ut.user_id = erp_s.id
        ";
        $sql = "
        SELECT 
            %s
        FROM 
            $si si
        {$join}
        {$where}
        ";
        $db = self::dbRO();
        $total   = $db->queryAll(sprintf($sql, $countField) . " ORDER BY si.create_time DESC ", $map);
        $records = $db->queryAll(
            "SELECT * FROM (" .
            sprintf($sql, $field) .
            ") t WHERE t.erp_ut_task_order = 1 $order $limit",
            $map
        );
        return [$records, $total];
    }

    /**
     * 根据班级ID获取学生
     * @param $collectionId
     * @param false $withOpenId 是否带openid
     * @return array|null
     */
    public static function getByCollectionId($collectionId, $withOpenId = false, $appId = null)
    {
        if (empty($collectionId)) {
            return [];
        }
        if (empty($appId)) {
            $appId = Constants::SMART_APP_ID;
        }
        $s  = DssStudentModel::getTableNameWithDb();
        $c  = DssCollectionModel::getTableNameWithDb();
        $wx = DssUserWeiXinModel::getTableNameWithDb();

        $field = "s.id,s.collection_id,s.thumb,s.name, c.teaching_start_time";
        $join  = " LEFT JOIN $c c ON s.collection_id = c.id";

        if (is_array($collectionId)) {
            $where = " s.collection_id in (" . implode(',', $collectionId) . ")";
        } else {
            $where = " s.collection_id = $collectionId";
        }
        if ($withOpenId) {
            $join .= " LEFT JOIN $wx wx ON wx.user_id = s.id ";
            $field .= ",wx.open_id";
            $where .= " AND wx.user_type = ".DssUserWeiXinModel::USER_TYPE_STUDENT;
            $where .= " AND wx.status = ".DssUserWeiXinModel::STATUS_NORMAL;
            $where .= " AND wx.busi_type = ".DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER;
            $where .= " AND wx.app_id = ".$appId;
        }

        $sql = "
        SELECT
            {$field}
        FROM
            $s s
            {$join}
        WHERE
            {$where}
        ";
        return self::dbRO()->queryAll($sql);
    }

    public static function getStudentInfo($studentID, $mobile, $uuid = null)
    {
        if (empty($studentID) && empty($mobile) && empty($uuid)) {
            return null;
        }

        $where = [];
        if (!empty($studentID)) {
            $where[self::$table . '.id'] = $studentID;
        }
        if (!empty($mobile)) {
            $where[self::$table . '.mobile'] = $mobile;
        }
        if (!empty($uuid)) {
            $where[self::$table . '.uuid'] = $uuid;
        }

        $db = self::dbRO();
        return $db->get(self::$table, [
            self::$table . '.id',
            self::$table . '.uuid',
            self::$table . '.mobile',
            self::$table . '.country_code',
            self::$table . '.create_time',
            self::$table . '.channel_id',
            self::$table . '.status',
            self::$table . '.sub_status',
            self::$table . '.sub_start_date',
            self::$table . '.sub_end_date',
            self::$table . '.trial_start_date',
            self::$table . '.trial_end_date',
            self::$table . '.act_sub_info',
            self::$table . '.first_pay_time',
            self::$table . '.name',
            self::$table . '.thumb',
            self::$table . '.flags',
            self::$table . '.last_play_time',
            self::$table . '.has_review_course',
            self::$table . '.collection_id',
            self::$table . '.password',
            self::$table . '.is_join_ranking',
        ], $where);
    }

    /**
     * 获取学生助教/课管信息
     * @param $studentId
     * @param bool $assistant
     * @return array|mixed
     */
    public static function getAssistantInfo($studentId, $assistant = true)
    {
        if (empty($studentId)) {
            return [];
        }
        $s = self::$table;
        $e = DssEmployeeModel::$table;
        if ($assistant) {
            $join = " s.assistant_id = e.id";
        } else {
            $join = " s.course_manage_id = e.id";
        }
        $sql = "
        SELECT 
            e.wx_qr,
            e.wx_num,
            e.wx_thumb,
            e.wx_nick
        FROM {$s} s
        INNER JOIN {$e} e ON {$join}
        WHERE s.id = :id
        ";
        $data = self::dbRO()->queryAll($sql, [':id' => $studentId]);
        if (empty($data[0])) {
            return [];
        }
        $data = $data[0];
        $urlList = ['wx_qr', 'wx_thumb'];
        foreach ($urlList as $path) {
            if (!empty($data[$path])) {
                $data[$path] = AliOSS::replaceCdnDomainForDss($data[$path]);
            }
        }
        return $data;
    }
}
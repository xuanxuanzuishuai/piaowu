<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;

class DssStudentModel extends DssModel
{
    public static $table = 'student';

    // 点评课学生标记
    const REVIEW_COURSE_NO = 0; // 非点评课学生
    const REVIEW_COURSE_49 = 1; // 体验课课包
    const REVIEW_COURSE_1980 = 2; // 正式课课包
    const REVIEW_COURSE_BE_OVERDUE = 3; //年卡已过期

    // 学生的状态
    const STATUS_REGISTER = 0;//注册
    const STATUS_BUY_TEST_COURSE = 1;//付费体验课
    const STATUS_BUY_TEST_COURSE_EXPIRED = 6; // 体验已过期
    const STATUS_BUY_NORMAL_COURSE = 2;//付费正式课
    const STATUS_UNBIND = 3;//未绑定微信
    const STATUS_BIND = 4;//已绑定微信
    const STATUS_HAS_EXPIRED = 5;//已过期

    // 学生账号状态
    const STATUS_NORMAL = 1; //正常
    const STATUS_STOP = 2; //禁用

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

    //当前进度
    const CURRENT_PROGRESS = [
        self::REVIEW_COURSE_NO   => '已注册',
        self::REVIEW_COURSE_49   => '付费体验课',
        self::REVIEW_COURSE_1980 => '付费正式课',
    ];

    //有效状态
    const VALID_STATUS = [
        1 => '未过期',
        2 => '已过期',
    ];

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
        $c = DssCollectionModel::$table;
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
            e.wx_nick,
            c.teaching_start_time,
            s.has_review_course,
            s.collection_id   
        FROM {$s} s
        INNER JOIN {$e} e ON {$join}
        LEFT JOIN {$c} c ON s.collection_id = c.id 
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

    /**
     * 根据uuid获取信息
     * @param $uuids
     * @return array
     */
    public static function getUuids($uuids){
        return self::dbRO()->select(self::$table, ['id', 'uuid','mobile','course_manage_id'], ['uuid'=>$uuids, 'status'=>self::STATUS_NORMAL]);
    }
}
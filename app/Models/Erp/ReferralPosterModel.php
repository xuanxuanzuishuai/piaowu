<?php
/**
 * 真人周周领奖上传分享截图数据
 * 2021.9.27前后的数据已经迁移到了op系统
 * 需求分支: XYZOP-998
 */

namespace App\Models\Erp;

class ReferralPosterModel extends ErpModel
{
    public static $table = 'referral_poster';

    //客户端类型 0学生端 1教师端
    const CLIENT_TYPE_USER = 0;
    const CLIENT_TYPE_TEACHER = 1;

    //审核状态 1未审核 2审核不合格 3审核合格
    const CHECK_STATUS_WAIT = 1;
    const CHECK_STATUS_UNQUALIFIED = 2;
    const CHECK_STATUS_QUALIFIED = 3;

    //系统审核code
    const SYSTEM_REFUSE_CODE_NEW    = -1; //未使用最新海报
    const SYSTEM_REFUSE_CODE_TIME   = -2; //朋友圈保留时长不足12小时，请重新上传
    const SYSTEM_REFUSE_CODE_GROUP  = -3; //分享分组可见
    const SYSTEM_REFUSE_CODE_FRIEND = -4; //请发布到朋友圈并截取朋友圈照片
    const SYSTEM_REFUSE_CODE_UPLOAD = -5; //上传截图出错

    //系统审核拒绝原因code
    const SYSTEM_REFUSE_REASON_CODE_NEW    = 3; //未使用最新海报
    const SYSTEM_REFUSE_REASON_CODE_TIME   = 10; //朋友圈保留时长不足12小时，请重新上传
    const SYSTEM_REFUSE_REASON_CODE_GROUP  = 1; //分享分组可见
    const SYSTEM_REFUSE_REASON_CODE_FRIEND = 9; //请发布到朋友圈并截取朋友圈照片
    const SYSTEM_REFUSE_REASON_CODE_UPLOAD = 4; //上传截图出错


    /**
     * 获取erp中学生审核通过的上传截图奖励
     * @param $studentId
     * @param $createTime
     * @return number
     */
    public static function getStudentSharePosterSuccessNum($studentId, $createTime)
    {
        $sqlWhere = [
            'student_id' => $studentId,
            'status'     => self::CHECK_STATUS_QUALIFIED,
            'create_time[>=]' => $createTime,
        ];
        $db = self::dbRO();
        return $db->count(self::$table, $sqlWhere);
    }
}

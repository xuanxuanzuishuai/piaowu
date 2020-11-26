<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

class DssUserWeiXinModel extends DssModel
{
    public static $table = "user_weixin";

    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;

    const USER_TYPE_STUDENT = 1; // 学生
    const USER_TYPE_TEACHER = 2;  // 老师 (废弃)
    const USER_TYPE_STUDENT_ORG = 3; // 学生机构号 (废弃)

    const BUSI_TYPE_STUDENT_SERVER = 1; // 学生服务号
    const BUSI_TYPE_TEACHER_SERVER = 2; // 老师服务号 (废弃)
    const BUSI_TYPE_EXAM_MINAPP = 6; // 音基小程序
    const BUSI_TYPE_STUDENT_MINAPP = 7; // 学生app推广小程序
    const BUSI_TYPE_REFERRAL_MINAPP = 8; // 转介绍小程序
}
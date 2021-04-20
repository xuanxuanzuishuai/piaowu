<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/13
 * Time: 19:52
 */

namespace App\Models;


class StudentReferralStudentStatisticsModel extends Model
{
    public static $table = 'student_referral_student_statistics';
    // 进度:0注册 1体验 2年卡(定义与代理转介绍学生保持一致)
    const STAGE_REGISTER = AgentUserModel::STAGE_REGISTER;
    const STAGE_TRIAL = AgentUserModel::STAGE_TRIAL;
    const STAGE_FORMAL = AgentUserModel::STAGE_FORMAL;
}

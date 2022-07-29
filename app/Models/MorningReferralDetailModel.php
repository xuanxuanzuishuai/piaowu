<?php
/**
 * 清晨转介绍关系-被推荐人进度表
 */

namespace App\Models;

class MorningReferralDetailModel extends Model
{
    public static $table = 'morning_referral_detail';

    // 进度:0注册 1体验 2年卡(定义与代理转介绍学生保持一致)
    const STAGE_REGISTER = AgentUserModel::STAGE_REGISTER;
    const STAGE_TRIAL = AgentUserModel::STAGE_TRIAL;
    const STAGE_FORMAL = AgentUserModel::STAGE_FORMAL;
}
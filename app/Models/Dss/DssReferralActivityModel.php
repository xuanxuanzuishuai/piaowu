<?php
namespace App\Models\Dss;

class DssReferralActivityModel extends DssModel
{
	public static $table = "referral_activity";

    // 活动状态 0未启用 1启用 2禁用
    const STATUS_ENABLE = 1;
}
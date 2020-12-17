<?php
namespace App\Models\Erp;

class ErpEventModel extends ErpModel
{
    public static $table = 'erp_event';

    const TYPE_IS_REFERRAL = 1; //转介绍
    const TYPE_IS_REISSUE_AWARD = 10; //补发红包
    const TYPE_IS_DURATION_POSTER = 5; //课时达标并且上传海报
    const DAILY_UPLOAD_POSTER = 4; //日常上传截图活动
}
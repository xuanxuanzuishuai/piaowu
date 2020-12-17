<?php
namespace App\Models\Erp;

class ErpEventTaskModel extends ErpModel
{
    public static $table = 'erp_event_task';

    //award列里定义的json格式数据，data['awards'][0]['to']含义:
    const AWARD_TO_REFERRER = 1; //奖励介绍人
    const AWARD_TO_BE_REFERRER = 2; //奖励被介绍人
}
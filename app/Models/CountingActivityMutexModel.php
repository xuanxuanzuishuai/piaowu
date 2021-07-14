<?php
/**
 * 计数任务互斥屏蔽任务表
 *
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:35 AM
 */
namespace App\Models;

class CountingActivityMutexModel extends Model
{
    public static $table = 'counting_activity_mutex';

    const NORMAL_STATUS = 1; //有效
    const DISABLE_STATUS = 2; //无效


}

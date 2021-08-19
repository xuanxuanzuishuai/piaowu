<?php
/**
 * Created by PhpStorm.
 * User: ypeng
 * Date: 2021/8/13
 * Time: 10:52
 */

namespace App\Models;


class WhiteGrantRecordModel extends Model
{
    public static $table = "white_grant_record";

    const STATUS_GIVE = 1; // 发放成功待领取
    const STATUS_GIVE_FAIL = 2; // 发放失败
    const STATUS_GIVE_NOT_GRANT = 3; //不予发放
    const STATUS_GIVE_NOT_SUCC  = 4; //发放成功用户领取成功


    //发放步骤
    const GRANT_STEP_0 = 0; //成功
    const GRANT_STEP_1 = 1; //用户不存在
    const GRANT_STEP_2 = 2; //金叶子发放失败
    const GRANT_STEP_3 = 3; //扣减金叶子失败
    const GRANT_STEP_4 = 4; //未绑定公众号
    const GRANT_STEP_5 = 5; //微信发红包失败

    const ENVIRONMENT_NOE_EXISTS = 'NOT_ENV_SATISFY';  // 环境不对
}

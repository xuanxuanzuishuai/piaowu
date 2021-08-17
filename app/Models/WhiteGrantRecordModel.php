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

    const STATUS_GIVE = 1; // 发放成功
    const STATUS_GIVE_FAIL = 2; // 发放失败
    const STATUS_GIVE_NOT_GRANT = 3; // 不予发放

    //是否绑定微信
    const BIND_WX_NORMAL = 1; //已绑定
    const BIND_WX_DESIABLE = 2; //未绑定

    //是否绑定公众号
    const BIND_GZH_NORMAL = 1; //已绑定
    const BIND_GZH_DESIABLE = 2; //未绑定

    //发放步骤
    const GRANT_STEP_0 = 0; //成功
    const GRANT_STEP_1 = 1; //用户不存在
    const GRANT_STEP_2 = 2; //金叶子发放失败
    const GRANT_STEP_3 = 3; //扣减金叶子失败
    const GRANT_STEP_4 = 4; //未绑定公众号
    const GRANT_STEP_5 = 5; //微信发红包失败
}

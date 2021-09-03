<?php
/**
 * 真人发放魔法石奖励记录信息表
 */

namespace App\Models;

class RealUserAwardMagicStoneModel extends Model
{
    public static $table = 'real_user_award_magic_stone';

    //奖励发放状态
    const STATUS_DISABLED = 0; // 不发放
    const STATUS_WAITING = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE = 3; // 发放成功
    const STATUS_GIVE_ING = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败


}

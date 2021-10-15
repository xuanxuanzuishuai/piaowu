<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021.10.18
 * Time: 00:10
 */

namespace App\Models;

use App\Libs\Constants;

class RealLandingPromotionRecordModel extends Model
{
    public static $table = "real_landing_promotion_record";
    //推广页类型
    const MAIN_COURSE_PROMOTED_V1 = 1;//2021.10.15主课落地页

    //推广页类型与意向激活类型关系映射
    const LANDING_TYPE_MAP_LOGIN_TYPE = [
        self::MAIN_COURSE_PROMOTED_V1 => Constants::REAL_STUDENT_LOGIN_TYPE_MAIN_LESSON_H5
    ];

}

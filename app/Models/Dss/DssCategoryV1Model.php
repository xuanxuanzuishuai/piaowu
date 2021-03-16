<?php
namespace App\Models\Dss;

class DssCategoryV1Model extends DssModel
{
    public static $table = 'erp_category_v1';

    // 1 课程 2 时长 3 实物 4 奖章
    const TYPE_COURSE = 1;
    const TYPE_DURATION = 2;
    const TYPE_OBJECT = 3;
    const MEDAL_AWARD_TYPE = 4;

    // 2001 正式时长 2002 体验时长 2003 赠送时长
    const DURATION_TYPE_NORMAL = 2001;
    const DURATION_TYPE_TRAIL = 2002;
    const DURATION_TYPE_GIFT = 2003;

    public static function containObject($goods)
    {
        return in_array(self::TYPE_OBJECT, array_column($goods, 'category_type'));
    }
}

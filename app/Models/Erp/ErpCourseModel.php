<?php

namespace App\Models\Erp;


class ErpCourseModel extends ErpModel
{
    public static $table = 'erp_course';

    const TYPE_TEST = 1; // 体验课
    const TYPE_NORMAL = 2; // 正式课
    const TYPE_DEVICE = 31; // 设备测试课
}
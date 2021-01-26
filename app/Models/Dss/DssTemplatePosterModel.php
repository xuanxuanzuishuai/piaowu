<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models\Dss;

class DssTemplatePosterModel extends DssModel
{
    //表名称
    public static $table = "template_poster";

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const INDIVIDUALITY_POSTER = 1; //个性化海报
    const STANDARD_POSTER = 2; //标准海报
}
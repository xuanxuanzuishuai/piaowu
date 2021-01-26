<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models\Dss;

use App\Libs\Constants;

class DssTemplatePosterWordModel extends DssModel
{
    //表名称
    public static $table = "template_poster_word";
    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线
}
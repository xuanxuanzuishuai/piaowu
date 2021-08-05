<?php


namespace App\Models;


class ShareMaterialConfig extends Model
{
    public static $table = "share_material_config";

    const POSTER_TYPE = 1;//海报
    const POSTER_WORD_TYPE = 2;//海报分享语

    const DISABLE_SHOW_STATUS = 1; //下线
    const NORMAL_SHOW_STATUS = 2; //上线

    const DISABLE_STATUS = 0; //删除
    const NORMAL_STATUS = 1; //正常

}
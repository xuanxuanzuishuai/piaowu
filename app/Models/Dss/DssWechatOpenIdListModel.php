<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models\Dss;

class DssWechatOpenIdListModel extends DssModel
{
    protected static $table = "wechat_openid_list";
    //关注公众号信息
    const SUBSCRIBE_WE_CHAT = 1;
    const UNSUBSCRIBE_WE_CHAT = 2;
}
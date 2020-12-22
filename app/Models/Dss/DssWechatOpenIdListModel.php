<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models\Dss;

use App\Libs\Constants;

class DssWechatOpenIdListModel extends DssModel
{
    protected static $table = "wechat_openid_list";
    //关注公众号信息
    const SUBSCRIBE_WE_CHAT = 1;
    const UNSUBSCRIBE_WE_CHAT = 2;

    /**
     * @param $uuid
     * @return array|null
     * 用户的微信绑定和关注情况
     */
    public static function getUuidOpenIdInfo($uuid)
    {
        $db = self::dbRO();

        $s   = DssStudentModel::$table;
        $uw  = DssUserWeiXinModel::$table;
        $wol = self::$table;
        $sql = "
        SELECT 
            s.uuid,
            uw.`status` bind_status,
            wol.status subscribe_status
        FROM 
            {$s} s
            LEFT JOIN {$uw} uw ON s.uuid IN ('" . implode("','", $uuid) . "')
                        AND s.id = uw.user_id
                        AND uw.app_id =:app_id
                        AND uw.busi_type =:busi_type
                        AND uw.user_type =:user_type
                        AND uw.`status` =:uwstatus
            LEFT JOIN {$wol} wol ON uw.open_id = wol.openid";
        $map = [
            ':app_id'    => Constants::SMART_APP_ID,
            ':user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
            ':busi_type' => Constants::SMART_MINI_BUSI_TYPE,
            ':uwstatus'  => DssUserWeiXinModel::STATUS_NORMAL
        ];
        return $db->queryAll($sql, $map);
    }
}
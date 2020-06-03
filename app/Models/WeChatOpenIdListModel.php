<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Services\WeChatService;

class WeChatOpenIdListModel extends Model
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
        $studentModel = StudentModel::$table;
        $userWeixinModel = UserWeixinModel::$table;
        $wechatOpenidListModel = self::$table;
        $sql = "select st.uuid,
                       uw.`status` bind_status,
                       wol.status subscribe_status
                    from {$studentModel} st
                    inner join {$userWeixinModel} uw
                        on st.uuid in ('" . implode("','", $uuid) . "')
                        and st.id = uw.user_id
                        and uw.app_id =:app_id
                        and uw.busi_type =:busi_type
                        and uw.user_type =:user_type
                        and uw.`status` =:uwstatus
                    left join {$wechatOpenidListModel} wol on uw.open_id = wol.openid";

        $map = [
            ':app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            ':user_type' => WeChatService::USER_TYPE_STUDENT,
            ':busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
            ':uwstatus' => UserWeixinModel::STATUS_NORMAL
        ];
        return MysqlDB::getDB()->queryAll($sql, $map);
    }
}
<?php


namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;

class UserWeiXinInfoModel extends Model
{
    public static $table = "user_weixin_info";
    const REDIS_HASH_USER_WEIXIN_INFO_PREFIX = 'user_weixin_info_';

    /**
     * 根据用户openid更新用户微信昵称和头像地址
     * @param $userId
     * @param $wxInfo
     * @return bool
     */
    public static function updateWxInfo($where, $wxInfo)
    {
        //更新表
        $update = [];
        if (isset($wxInfo['nickname']) && !empty($wxInfo['nickname'])) {
            $update['nickname'] = $wxInfo['nickname'];
        }
        if (isset($wxInfo['head_url']) && !empty($wxInfo['head_url'])) {
            $update['head_url'] = $wxInfo['head_url'];
        }

        //没有需要更新的数据直接返回
        if (empty($update)) {
            return false;
        }
        $update['update_time'] = time();
        $db = MysqlDB::getDB();
        $result = $db->updateGetCount(self::$table, $update, $where);
        if ($result && $result > 0) {
            return true;
        }
        return false;
    }
}
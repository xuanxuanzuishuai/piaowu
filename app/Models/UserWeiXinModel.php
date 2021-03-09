<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/01/26
 * Time: 5:14 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\UserCenter;

class UserWeiXinModel extends Model
{
    //表名称
    public static $table = "user_weixin";
    //用户微信信息存储redis key
    const REDIS_KEY_USER_WX_INFO_PREFIX = 'wechat_agent_student_';

    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;

    const USER_TYPE_STUDENT = 1; // 学生
    const USER_TYPE_TEACHER = 2;  // 老师 (废弃)
    const USER_TYPE_STUDENT_ORG = 3; // 学生机构号 (废弃)
    const USER_TYPE_AGENT = 4;

    const BUSI_TYPE_STUDENT_SERVER = 1; // 学生服务号
    const BUSI_TYPE_TEACHER_SERVER = 2; // 老师服务号 (废弃)
    const BUSI_TYPE_EXAM_MINAPP = 6; // 音基小程序
    const BUSI_TYPE_STUDENT_MINAPP = 7; // 学生app推广小程序
    const BUSI_TYPE_REFERRAL_MINAPP = 8; // 转介绍小程序
    const BUSI_TYPE_AGENT_MINI = 9; // 代理小程序
    const BUSI_TYPE_SHOW_MINI = 10; //评测分享小程序

    /**
     * 检测账户与微信是否绑定
     * @param $agentId
     * @param $userType
     * @param $busiType
     * @param $appId
     * @return array
     */
    public static function userBindData($agentId, $userType, $busiType, $appId)
    {
        $where = [
            'user_id' => $agentId,
            'user_type' => $userType,
            'status' => self::STATUS_NORMAL,
            'busi_type' => $busiType,
            'app_id' => $appId,
        ];
        return self::getRecords($where, ['id']);
    }
    /**
     * 根据用户id更新用户微信昵称和头像地址
     * @param $userId
     * @param $wxInfo
     * @return bool
     */
    public static function updateWxInfoByUserid($userId, $wxInfo)
    {
        //更新redis
        $redis = RedisDB::getConn();
        $redis->set(self::REDIS_KEY_USER_WX_INFO_PREFIX . $userId, json_encode($wxInfo));
        $redis->expire(self::REDIS_KEY_USER_WX_INFO_PREFIX . $userId, 86400);  //24小时

        //更新表
        $update = [];
        if (isset($wxInfo['nickname']) && !empty($wxInfo['nickname'])) {
            $update['nickname'] = $wxInfo['nickname'];
        }
        if (isset($wxInfo['thumb']) && !empty($wxInfo['thumb'])) {
            $update['thumb'] = $wxInfo['thumb'];
        }

        //没有需要更新的数据直接返回
        if (empty($update)) {
            return false;
        }
        $result = self::updateRecord($userId, $update);
        return ($result && $result > 0);
    }

    /**
     * 获取代理小程序 ，智能陪练app 的 学生信息
     * @param $userId
     * @param $fields
     * @return array
     */
    public static function getUserWeiXinListByUserid($userId,$fields) {
        $where = [
            'user_id' => $userId,
            'status' => self::STATUS_NORMAL,
            'user_type' => self::USER_TYPE_AGENT,
            'busi_type' => self::BUSI_TYPE_AGENT_MINI,
            'app_id' => UserCenter::AUTH_APP_ID_OP_AGENT,
        ];
        return self::getRecords($where, $fields);
    }
}
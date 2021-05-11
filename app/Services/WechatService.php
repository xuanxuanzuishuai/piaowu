<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/04/13
 * Time: 11:37 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;

class WechatService
{
    const KEY_WECHAT_DAILY_ACTIVE         = 'WECHAT_LOGIN_LOG_'; // hash
    const KEY_WECHAT_DAILY_ACTIVE_EXPIRE  = 691200; // 8 days
    const KEY_WECHAT_USER_NOT_EXISTS      = 'WECHAT_USER_NOT_EXISTS_'; // list
    const KEY_WECHAT_USER_NOT_EXISTS_MAP  = 'WECHAT_USER_NOT_EXISTS_MAP_'; // hash
    const KEY_UPDATE_TAG_WAITING          = 'WECHAT_UPDATE_TAG_WAITING_'; // list
    const KEY_UPDATE_TAG_WAITING_MAP      = 'WECHAT_UPDATE_TAG_WAITING_MAP_'; // hash
    const KEY_USER_CURRENT_MENU_TAG       = 'WECHAT_USER_CURRENT_MENU_TAG'; // hash

    const USER_TYPE_1_1 = '1_1';// 未绑定
    const USER_TYPE_1_2 = '1_2';// 解除绑定
    const USER_TYPE_2_1 = '2_1';// 绑定中-仅注册-7天内登录过app
    const USER_TYPE_3_1 = '3_1';// 绑定中-仅注册-7天内未登录过app
    const USER_TYPE_4_1 = '4_1';// 绑定中-有加入班级-开班前-购买年卡（付费正式课）
    const USER_TYPE_4_2 = '4_2';// 绑定中-有加入班级-开班中-购买年卡（付费正式课）
    const USER_TYPE_4_3 = '4_3';// 绑定中-有加入班级-结班后-14天内-购买年卡（付费正式课）
    const USER_TYPE_5_1 = '5_1';// 绑定中-有加入班级-开班前-未购买年卡
    const USER_TYPE_5_2 = '5_2';// 绑定中-有加入班级-开班中-未购买年卡
    const USER_TYPE_5_3 = '5_3';// 绑定中-有加入班级-结班后-14天内-未购买年卡
    const USER_TYPE_6_1 = '6_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期未过期-大于30天
    const USER_TYPE_6_2 = '6_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期未过期-大于30天
    const USER_TYPE_7_1 = '7_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期未过期-小于等于30天
    const USER_TYPE_7_2 = '7_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期未过期-小于等于30天
    const USER_TYPE_8_1 = '8_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期已过期
    const USER_TYPE_8_2 = '8_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期已过期
    const USER_TYPE_9_1 = '9_1';// 绑定中-有加入班级-结班后-14天外-未购买年卡

    /**
     * 记录每天用户"登录"和未绑定用户
     * @param $openId
     * @return bool
     */
    public static function wechatInteractionLog($openId)
    {
        if (empty($openId)) {
            return false;
        }
        $date = date('Ymd');
        $key = self::KEY_WECHAT_DAILY_ACTIVE . $date;
        $redis = RedisDB::getConn();
        $redis->hset($key, $openId, time());
        $redis->expire($key, self::KEY_WECHAT_DAILY_ACTIVE_EXPIRE);
        $user = DssUserWeiXinModel::getByOpenId($openId);
        if (empty($user)) {
            $key = self::KEY_WECHAT_USER_NOT_EXISTS . $date;
            $mapKey = self::KEY_WECHAT_USER_NOT_EXISTS_MAP . $date;
            $exists = $redis->hget($mapKey, $openId);
            if ($exists) {
                return false;
            }
            $redis->lpush($key, [$openId]);
            $redis->expire($key, 172800); // 2 days
            $redis->hset($mapKey, $openId, time());
            $redis->expire($mapKey, 172800); // 2 days
        }
        return true;
    }

    /**
     * 获取用户类型对应菜单
     * @param $openId
     * @return string
     */
    public static function getUserTypeByOpenid($openId)
    {
        if (empty($openId)) {
            return '';
        }
        $user        = null;
        $lastPayInfo = null;
        $isNormal    = false; // 用户当前状态是否是年卡
        $today       = strtotime(date('Y-m-d'));

        $userWx = DssUserWeiXinModel::getByOpenId($openId);
        if (empty($userWx)) {
            $userWx = DssUserWeiXinModel::getByOpenId(
                $openId,
                Constants::SMART_APP_ID,
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
                ['status' => DssUserWeiXinModel::STATUS_DISABLE]
            );
            if (!empty($userWx)) {
                return self::USER_TYPE_1_2;
            }
            return self::USER_TYPE_1_1;
        }

        if (!empty($userWx['user_id'])) {
            $user = DssStudentModel::getById($userWx['user_id']);
        }
        if (empty($user)) {
            return self::USER_TYPE_1_1;
        }
        // 订阅结束时间
        $subEndDate = strtotime($user['sub_end_date']);
        // 班级信息
        $collection = null;
        if (!empty($user['collection_id'])) {
            // 去除公海班级
            $defaultCollection = DssCollectionModel::getRecords(
                [
                    "type" => DssCollectionModel::TYPE_PUBLIC,
                ],
                ['id']
            );
            $defaultIds = array_column($defaultCollection, 'id');
            if (in_array($user['collection_id'], $defaultIds)) {
                return '';
            } else {
                $collection = DssCollectionModel::getById($user['collection_id']);
            }
        }
        // 注册
        if ($user['has_review_course'] == DssStudentModel::REVIEW_COURSE_NO && empty($collection)) {
            // 七天内活跃记录：
            $active = self::getUserActiveRecord($openId);
            if ($active && empty($collection)) {
                return self::USER_TYPE_2_1;
            }
            return self::USER_TYPE_3_1;
        }

        // 上次购买年卡信息
        if ($user['has_review_course'] == DssStudentModel::REVIEW_COURSE_1980) {
            $isNormal = true;
            $lastPayInfo = DssGiftCodeModel::getUserFirstPayInfo($user['id'], DssCategoryV1Model::DURATION_TYPE_NORMAL, 'desc');
        }

        // 有班级
        if (!empty($collection)) {
            // 购买年卡
            $teachingStatus = self::getCollectionTeachingStatus($collection);
            if ($isNormal) {
                // 当前班级状态：开班前
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_BEFORE) {
                    // 购买年卡时间在开班前
                    if ($lastPayInfo['buy_time'] < $collection['teaching_start_time']) {
                        return self::USER_TYPE_4_1;
                    }
                }
                // 当前班级状态：开班中
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_ONGOING) {
                    // 年卡购买时间在开班中
                    if ($lastPayInfo['buy_time'] > $collection['teaching_start_time']
                    && $lastPayInfo['buy_time'] <= $collection['teaching_end_time']) {
                        return self::USER_TYPE_4_2;
                    }
                }
                // 当前班级状态：结班2周内
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_FINISHED) {
                    // 年卡购买时间在结班14天内
                    if ($lastPayInfo['buy_time'] < ($collection['teaching_end_time'] + Util::TIMESTAMP_TWOWEEK)) {
                        return self::USER_TYPE_4_3;
                    } else {
                        // 年卡已过期
                        if ($subEndDate < $today) {
                            return self::USER_TYPE_8_1;
                        }
                    }
                }
                // 当前班级状态：结班2周+
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_FINISHED_TWO_WEEK) {
                    // 年卡购买时间为结班后2周外
                    if ($lastPayInfo['buy_time'] > ($collection['teaching_end_time'] + Util::TIMESTAMP_TWOWEEK)) {
                        // 年卡有效期剩余超过30天
                        if ($subEndDate - $today >= Util::TIMESTAMP_THIRTY_DAYS) {
                            return self::USER_TYPE_6_1;
                        } elseif ($subEndDate >= $today) {
                            // 年卡未过期
                            return self::USER_TYPE_7_1;
                        } else {
                            // 年卡已过期
                            return self::USER_TYPE_8_1;
                        }
                    }
                }
            } else {
                // 未购买年卡
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_BEFORE) {
                    return self::USER_TYPE_5_1;
                }
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_ONGOING) {
                    return self::USER_TYPE_5_2;
                }
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_FINISHED) {
                    return self::USER_TYPE_5_3;
                }
                if ($teachingStatus == DssCollectionModel::TEACHING_STATUS_FINISHED_TWO_WEEK) {
                    return self::USER_TYPE_9_1;
                }
            }
        } else {
            // 没班级
            if ($isNormal) {
                if ($subEndDate - $today >= Util::TIMESTAMP_THIRTY_DAYS) {
                    return self::USER_TYPE_6_2;
                } elseif ($subEndDate >= $today) {
                    return self::USER_TYPE_7_2;
                } else {
                    return self::USER_TYPE_8_2;
                }
            }
        }
        SimpleLogger::error('GET USER TYPE EMPTY', [$user, $collection, $lastPayInfo]);
        return '';
    }

    /**
     * 获取用户7天内活跃
     * @param $openId
     * @return bool
     */
    public static function getUserActiveRecord($openId)
    {
        $today = strtotime(date('Y-m-d'));
        $start = strtotime('-7 days');
        $redis = RedisDB::getConn();
        for ($i = $start; $i <= $today; $i += Util::TIMESTAMP_ONEDAY) {
            $key = self::KEY_WECHAT_DAILY_ACTIVE . date('Ymd', $i);
            $exists = $redis->hget($key, $openId);
            if ($exists) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断指定时间和班级开班时间状态
     * @param $collection
     * @param null $now
     * @return int
     */
    public static function getCollectionTeachingStatus($collection, $now = null)
    {
        if (is_null($now)) {
            $now = time();
        }
        if ($now < $collection['teaching_start_time']) {
            return DssCollectionModel::TEACHING_STATUS_BEFORE;
        }
        if ($collection['teaching_start_time'] < $now
            && $now < $collection['teaching_end_time']) {
            return DssCollectionModel::TEACHING_STATUS_ONGOING;
        }
        if ($now > $collection['teaching_end_time']) {
            if ($now - $collection['teaching_end_time'] >= Util::TIMESTAMP_TWOWEEK) {
                return DssCollectionModel::TEACHING_STATUS_FINISHED_TWO_WEEK;
            }
        }
        return DssCollectionModel::TEACHING_STATUS_FINISHED;
    }

    /**
     * 更新用户标签-消费者
     * @param $openId
     * @param bool $force
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function updateUserTag($openId, $force = false)
    {
        if (empty($openId)) {
            return false;
        }
        $typeId = self::getUserTypeByOpenid($openId);
        $config = DictConstants::get(DictConstants::WECHAT_CONFIG, 'user_type_tag_dict');
        $config = json_decode($config, true);
        $amount = DictConstants::get(DictConstants::WECHAT_CONFIG, 'tag_update_amount');
        $amount = $amount ?: 50;
        $redis  = RedisDB::getConn();
        $tagId = $config[$typeId];
        if (empty($tagId)) {
            SimpleLogger::error('EMPTY MENU TAG ID', [$config, $typeId]);
            // 不匹配任何菜单，打一个没有菜单的标签
            $tagId = DictConstants::get(DictConstants::WECHAT_CONFIG, 'menu_tag_none');
            if (empty($tagId)) {
                return false;
            }
        }
        // 待更新标签和已有标签对比，无变化不更新
        $userCurrentTag = $redis->hget(self::KEY_USER_CURRENT_MENU_TAG, $openId);
        if (!empty($userCurrentTag) && $userCurrentTag == $tagId) {
            return false;
        }
        $redis->hset(self::KEY_USER_CURRENT_MENU_TAG, $openId, $tagId);

        $key = self::KEY_UPDATE_TAG_WAITING . date('Ymd') . '_' . $tagId;
        $mapKey = self::KEY_UPDATE_TAG_WAITING_MAP . date('Ymd') . '_' . $tagId;
        $exists = $redis->hget($mapKey, $openId);
        if (!$exists) {
            $redis->hset($mapKey, $openId, time());
            $redis->lpush($key, [$openId]);
            $len = $redis->llen($key);
            if ($len >= $amount) {
                self::batchUpdateUserTagList($key, $force);
            }
            $redis->expire($key, 172800); // 2 days
            $redis->expire($mapKey, 172800); // 2 days
        }
        return true;
    }

    /**
     * 购买事件触发更新标签
     * @param $userId
     * @param false $force
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function updateUserTagByUserId($userId, $force = false)
    {
        if (empty($userId)) {
            return false;
        }
        $user = DssUserWeiXinModel::getByUserId($userId);
        if (empty($user['open_id'])) {
            return false;
        }
        return self::updateUserTag($user['open_id'], $force);
    }

    /**
     * 处理列表中待更新数据
     * @param $key
     * @param false $force @deprecated
     * @param array $params
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function batchUpdateUserTagList($key, $force = false, $params = [])
    {
        $redis   = RedisDB::getConn();
        $counter = 0;
        $limit   = 50; // 微信限制
        $list    = [];
        $tagId   = explode('_', $key)[5] ?? '';
        if (empty($tagId)) {
            SimpleLogger::error('EMPTY TAG ID', [$key]);
            return false;
        }
        $wechat = WeChatMiniPro::factory(DssUserWeiXinModel::dealAppId($params['appid'] ?? null), $params['busi_type'] ?? DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        $mapKey = self::KEY_UPDATE_TAG_WAITING_MAP . date('Ymd') . '_' . $tagId;
        // 可强制全部更新，也可多次更新
        while ($counter < $limit) {
            $item = $redis->rpop($key);
            if (empty($item)) {
                break;
            }
            $counter ++;
            $list[] = $item;
            if ($counter >= $limit) {
                $redis->hdel($mapKey, $list);
                $wechat->batchUnTagUsers($list, $tagId);
                $wechat->batchTagUsers($list, $tagId);
                $list = [];
                $counter = 0;
            }
        }

        if (!empty($list)) {
            $redis->hdel($mapKey, $list);
            $wechat->batchUnTagUsers($list, $tagId);
            $wechat->batchTagUsers($list, $tagId);
        }
        return true;
    }

    /**
     * 取关后清除用户标签
     * @param $openId
     * @return int
     */
    public static function clearCurrentTag($openId)
    {
        $redis = RedisDB::getConn();
        return $redis->hdel(self::KEY_USER_CURRENT_MENU_TAG, [$openId]);
    }
}

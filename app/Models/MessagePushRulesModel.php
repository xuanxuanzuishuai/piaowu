<?php
/**
 * User: lizao
 * Date: 2020/9/23
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class MessagePushRulesModel extends Model
{
    public static $table = 'message_push_rules';
    // 状态: 0禁用;1启用;
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE  = 1;

    const RULE_STATUS_DICT = [
        self::STATUS_DISABLE => '禁用',
        self::STATUS_ENABLE => '启用'
    ];

    const PUSH_TYPE_CUSTOMER = 1; // 客服消息
    const PUSH_TYPE_TEMPLATE = 2; // 模板消息
    const PUSH_TYPE_DICT = [
        self::PUSH_TYPE_CUSTOMER => '客服消息',
        self::PUSH_TYPE_TEMPLATE => '模板消息',
    ];

    const PUSH_TARGET_ALL         = 1; // 全部用户
    const PUSH_TARGET_CLASS_DAY_0 = 2; // 当日开班用户
    const PUSH_TARGET_CLASS_DAY_7 = 3; // 开班第7天用户
    const PUSH_TARGET_YEAR_CARD   = 4; // 年卡C级用户
    const PUSH_TARGET_TRIAL       = 5; // 体验C级用户
    const PUSH_TARGET_REGISTER    = 6; // 注册C级用户
    const PUSH_TARGET_ONLY_TRAIL  = 7; // 付费体验课用户
    const PUSH_TARGET_ONLY_YEAR   = 8; // 付费年卡用户
    const PUSH_TARGET_TRAIL_BEFORE_2 = 9;
    const PUSH_TARGET_TRAIL_BEFORE_1 = 10;
    const PUSH_TARGET_TRAIL_NO_1     = 11;
    const PUSH_TARGET_TRAIL_NO_2     = 12;
    const PUSH_TARGET_TRAIL_NO_3     = 13;
    const PUSH_TARGET_TRAIL_NO_4     = 14;
    const PUSH_TARGET_ALL_TRAIL      = 15;
    const PUSH_TARGET_ALL_AIPL       = 16;
    const PUSH_TARGET_DICT = [
        self::PUSH_TARGET_ALL         => '全部用户',
        self::PUSH_TARGET_CLASS_DAY_0 => '当日开班用户',
        self::PUSH_TARGET_CLASS_DAY_7 => '开班第7天用户',
        self::PUSH_TARGET_YEAR_CARD   => '年卡C级用户',
        self::PUSH_TARGET_TRIAL       => '体验C级用户',
        self::PUSH_TARGET_REGISTER    => '注册C级用户',
        self::PUSH_TARGET_ONLY_TRAIL  => '付费体验课用户',
        self::PUSH_TARGET_ONLY_YEAR   => '付费正式课用户',
        self::PUSH_TARGET_TRAIL_BEFORE_2 => '体验营开班前2天',
        self::PUSH_TARGET_TRAIL_BEFORE_1 => '体验营开班前1天',
        self::PUSH_TARGET_TRAIL_NO_1     => '当期五日打卡活动的体验营，DAY1，未练琴用户',
        self::PUSH_TARGET_TRAIL_NO_2     => '当期五日打卡活动的体验营，DAY2，未练琴用户',
        self::PUSH_TARGET_TRAIL_NO_3     => '当期五日打卡活动的体验营，DAY3，未练琴用户',
        self::PUSH_TARGET_TRAIL_NO_4     => '当期五日打卡活动的体验营，DAY4，未练琴用户',
        self::PUSH_TARGET_ALL_TRAIL      => '所有体验营用户',
        self::PUSH_TARGET_ALL_AIPL       => '所有智能注册用户',
    ];

    /**
     * @param string $where
     * @param int $page
     * @param int $count
     * @return array
     * 推送规则列表
     */
    public static function rulesList(string $where, int $page, int $count)
    {
        $limit      = Util::limitation($page, $count);
        $countField = "count(id) as total";
        $field      = "`id`,`name`,`type`,`target`,`is_active`,time->>'$.desc' as `display_time`,`update_time`,`remark`,`app_id`";
        $sql        = "SELECT %s FROM ".self::$table." WHERE ";

        $db    = MysqlDB::getDB();
        $total = $db->queryAll(sprintf($sql, $countField) . $where);
        $rules = $db->queryAll(sprintf($sql, $field) . $where . $limit);
        return [$rules, $total[0]['total'] ?? 0];
    }

    /**
     * 获取规则信息
     * @param $appId
     * @param $name
     * @param $target
     * @return mixed
     */
    public static function getRuleInfo($appId, $name, $target)
    {
        return self::getRecord(['app_id' => $appId, 'is_active' => self::STATUS_ENABLE, 'name' => $name, 'target' => $target]);
    }
}
<?php
/**
 * User: lizao
 * Date: 2020/9/23
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class MessagePushRulesModel extends Model
{
    public static $table = 'message_push_rules';
    // 状态: 0未启用;1启用;2禁用;
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE  = 1;
    const STATUS_DOWN    = 2;

    const RULE_STATUS_DICT = [
        self::STATUS_DISABLE,
        self::STATUS_ENABLE
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
    const PUSH_TARGET_DICT = [
        self::PUSH_TARGET_ALL         => '全部用户',
        self::PUSH_TARGET_CLASS_DAY_0 => '当日开班用户',
        self::PUSH_TARGET_CLASS_DAY_7 => '开班第7天用户',
        self::PUSH_TARGET_YEAR_CARD   => '年卡C级用户',
        self::PUSH_TARGET_TRIAL       => '体验C级用户',
        self::PUSH_TARGET_REGISTER    => '注册C级用户',
    ];

    /**
     * @param $params
     * @return array
     * 推送规则列表
     */
    public static function rulesList($params)
    {
        $sql = "
SELECT `id`,`name`,`type`,`target`,`is_active`,time->>'$.desc' as `display_time`,`update_time`
FROM ".self::$table;

        $db = MysqlDB::getDB();
        $rules = $db->queryAll($sql);
        $totalCount = count($rules);
        return [$rules, $totalCount];
    }
}
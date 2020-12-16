<?php
namespace App\Models\Erp;

class ErpUserEventTaskAwardModel extends ErpModel
{
    public static $table = 'erp_user_event_task_award';
    //奖励发放状态
    const STATUS_DISABLED = 0; // 不发放
    const STATUS_WAITING = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE = 3; // 发放成功
    const STATUS_GIVE_ING = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    // 奖励类型
    const AWARD_TYPE_CASH = 1; // 现金
    const AWARD_TYPE_DURATION = 2; // 有效期时长
    const AWARD_TYPE_INTEGRATION = 3; // 积分
    const AWARD_TYPE_MEDAL = 4; //奖章

    /**
     * 当前奖励对应的用户信息
     * @param $awardId
     * @return mixed
     */
    public static function getAwardUserInfoByAwardInfo($awardId)
    {
        return self::dbRO()->get(self::$table,
        [
            '[><]' . ErpUserEventTaskModel::$table => ['uet_id' => 'id'],
            '[><]' . ErpStudentModel::$table => ['user_id' => 'id']
        ],
        [
            ErpUserEventTaskModel::$table . '.app_id',
            ErpStudentModel::$table . '.uuid'
        ],
        [
            ErpUserEventTaskAwardModel::$table . '.id' => $awardId
        ]);
    }

    /**
     * 需要发放的红包
     * @return array|null
     */
    public static function needSendRedPackAward()
    {
        $time = time() - 1728000; //只处理最近二十天创建的
        return self::dbRO()->queryAll("SELECT id FROM " . self::$table . " force index(create_time) WHERE create_time >= " . $time . " AND `status` IN (" . self::STATUS_WAITING . "," . self::STATUS_GIVE_FAIL .") AND award_type = " . self::AWARD_TYPE_CASH . " AND (create_time + delay) <= " . time());
    }

    /**
     * 奖励对应的活动信息
     * @param $awardId
     * @return mixed
     */
    public static function awardRelateEvent($awardId)
    {
        return self::dbRO()->get(self::$table,
            [
                '[><]' . ErpUserEventTaskModel::$table => ['uet_id' => 'id'],
                '[><]' . ErpStudentModel::$table => [ErpUserEventTaskModel::$table . '.user_id' => 'id'],
                '[><]' . ErpEventTaskModel::$table => [ErpUserEventTaskModel::$table . '.event_task_id' => 'id'],
                '[><]' . ErpEventModel::$table => [ErpEventTaskModel::$table . '.event_id' => 'id'],
            ],
            [
                ErpStudentModel::$table . '.uuid',
                ErpEventModel::$table . '.type',
                ErpUserEventTaskModel::$table . '.app_id',
                ErpUserEventTaskModel::$table . '.event_task_id',
                self::$table . '.status',
                ErpEventTaskModel::$table . '.type (task_type)'
            ],
            [
                self::$table . '.id' => $awardId
            ]);
    }
}
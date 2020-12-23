<?php
namespace App\Models\Erp;

use App\Libs\SimpleLogger;

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
        $a = ErpUserEventTaskAwardModel::$table;
        $ue = ErpUserEventTaskModel::$table;
        $t = ErpEventTaskModel::$table;
        $e = ErpEventModel::$table;
        $sql = "SELECT a.id,a.`status`,e.type,e.`name`,t.`name` 
FROM {$a} a force index(create_time)
inner join {$ue} ue on a.uet_id = ue.id 
inner join {$t} t on ue.event_task_id = t.id
inner join {$e} e on t.event_id = e.id
WHERE a.create_time >= {$time} AND a.status IN (" . self::STATUS_WAITING . "," . self::STATUS_GIVE_FAIL .") AND a.award_type = " .self::AWARD_TYPE_CASH. " AND (a.create_time + a.delay) <= " . time();
        $baseAward = self::dbRO()->queryAll($sql);
        //如果待发放并且是上传截图领奖，过滤掉
        $queueArr = [];
        if (!empty($baseAward)) {
            foreach ($baseAward as $award) {
                if ($award['type'] == ErpEventModel::DAILY_UPLOAD_POSTER && $award['status'] == self::STATUS_WAITING) {
                    SimpleLogger::info('auto send not can give', ['award' => $award]);
                    continue;
                }
                $queueArr[] = ['id' => $award['id']];
            }
        }
        return $queueArr;
    }

    /**
     * 需要更新状态的红包
     * @return array|null
     */
    public static function needUpdateRedPackAward()
    {
        $time = time() - 864000; //只处理最近十天更新的
        return self::dbRO()->queryAll("SELECT id FROM " . self::$table . " WHERE review_time >= " . $time . " AND `status` IN (" . self::STATUS_GIVE_ING .") AND award_type = " . self::AWARD_TYPE_CASH);
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
                '[><]' . ErpStudentModel::$table . ' (r)' => ['user_id' => 'id'],
                '[><]' . ErpEventTaskModel::$table => [ErpUserEventTaskModel::$table . '.event_task_id' => 'id'],
                '[><]' . ErpEventModel::$table => [ErpEventTaskModel::$table . '.event_id' => 'id'],
            ],
            [
                ErpStudentModel::$table . '.uuid',
                'r.uuid (get_award_uuid)',
                ErpEventModel::$table . '.type',
                ErpUserEventTaskModel::$table . '.app_id',
                ErpUserEventTaskModel::$table . '.event_task_id',
                self::$table . '.status',
                ErpEventTaskModel::$table . '.type (task_type)',
                self::$table . '.award_amount'
            ],
            [
                self::$table . '.id' => $awardId
            ]);
    }
}
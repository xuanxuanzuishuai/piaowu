<?php
/**
 * 发放真人周周领奖多次上传分享海报奖励
 * author: qingfeng.lian
 * date: 2021/11/17
 * time: 获取所有当天到当天脚本运行脚本时间内应该结算的活动
 * 发放奖励时间公式：   M(发放奖励时间) = 活动结束时间(天) + 5天 + N天
 * example: 活动结束时间是1号23:59:59， 发放奖励时间是 5+1天 ， 则  M= 1+5+1 = 7, 得出是在7号12点发放奖励
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\RealSharePosterModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptSendRealWeekActivityAward
{
    private static $time           = 0;
    private static $todayFirstTime = 0;
    const LOCK_KEY = 'script_send_real_week_activity_award_lock';

    /**
     * 发放真人周周领奖多次上传分享海报奖励 - 获取所有当天到当天脚本运行脚本时间内应该结算的活动
     * @return bool
     */
    public function run(): bool
    {
        // 加锁
        $lock = self::lock();
        if (!$lock) {
            SimpleLogger::info("ScriptSendRealWeekActivityAward_is_lock", []);
            return self::returnResponse(false, []);
        }
        self::$time           = time();
        self::$todayFirstTime = strtotime(date("Y-m-d", strtotime("-1 day ")));
        // A队列： 获取所有当天应该结算的活动
        $activityList = self::getSendAwardActivityList();
        SimpleLogger::info("ScriptSendRealWeekActivityAward_activity_list", [$activityList]);
        if (empty($activityList)) {
            return self::returnResponse(true, []);
        }
        // 获取活动参与用户
        foreach ($activityList as $item) {
            // 检查活动奖励是否已经发放
            $awardRecord = RealUserAwardMagicStoneModel::getRecord(['activity_id' => $item['activity_id']]);
            if (!empty($awardRecord)) {
                SimpleLogger::info("ScriptSendRealWeekActivityAward_activity_award_is_send", [$item, $awardRecord]);
                continue;
            }
            $studentList = self::getPartakeActivityStudentIdList($item['activity_id']);
            if (empty($studentList)) {
                SimpleLogger::info("ScriptSendRealWeekActivityAward_activity_not_found_success_share_poster_user", [$item, $studentList]);
                continue;
            }
            // 放入用户发奖队列
            foreach ($studentList as $_studentId) {
                $queueData = [
                    'app_id'       => Constants::REAL_APP_ID,
                    'student_id'   => $_studentId,
                    'activity_id'  => $item['activity_id'],
                    'act_status'   => RealUserAwardMagicStoneModel::STATUS_GIVE,
                    'defer_second' => self::getStudentWeekActivitySendAwardDeferSecond($_studentId)
                ];
                QueueService::addRealUserPosterAward($queueData);
                SimpleLogger::info("qingfeng-test-addRealUserPosterAward", [$queueData]);
            }
            unset($_studentId);
        }
        unset($item);

        // 清理队列延时发放时间
        self::clearWeekActivitySendAwardDeferSecond();
        SimpleLogger::info("ScriptSendRealWeekActivityAward_success", []);
        return self::returnResponse(true, []);
    }

    /**
     * 获取学生周周领奖活动奖励发放延时多少秒
     * @param $studentId
     * @return int
     */
    public static function getStudentWeekActivitySendAwardDeferSecond($studentId): int
    {
        $redis        = RedisDB::getConn();
        $redisHashKey = 'real_student_week_activity_send_award_defer_second';
        return $redis->hincrby($redisHashKey, $studentId, 1);
    }

    /**
     * 清理学生发放奖励延时时间
     * @param $studentId
     * @return int
     */
    public static function clearWeekActivitySendAwardDeferSecond(): int
    {
        $redis        = RedisDB::getConn();
        $redisHashKey = 'real_student_week_activity_send_award_defer_second';
        return $redis->del([$redisHashKey]);
    }

    /**
     * 获取所有当天到当天脚本运行脚本时间内应该结算的活动
     * 只查新规则的活动
     * @return array
     */
    public static function getSendAwardActivityList(): array
    {
        $oldRuleLastActivityId = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        return RealWeekActivityModel::getRecords([
            'send_award_time[>=]' => self::$todayFirstTime,
            'send_award_time[<=]' => self::$time,
            'activity_id[>]'      => $oldRuleLastActivityId
        ]);
    }

    /**
     * 获取参与了活动并且是审核通过的用户列表
     * @param $activityId
     * @return array
     */
    public static function getPartakeActivityStudentIdList($activityId): array
    {
        $studentList = RealSharePosterModel::getRecords([
            'activity_id'   => $activityId,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'type'          => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'GROUP'         => ['student_id'],
        ], ['student_id']);
        if (empty($studentList)) {
            return [];
        }
        return array_unique(array_column($studentList, 'student_id'));
    }

    public static function lock()
    {
        $redis      = RedisDB::getConn();
        $expireTime = strtotime(date("Y-m-d 23:59:59", time())) - time();
        return $redis->set(self::LOCK_KEY, 1, 'EX', $expireTime, 'NX');
    }

    public static function unlock()
    {
        return (RedisDB::getConn())->del([self::LOCK_KEY]);
    }

    public static function returnResponse($isUnlock, $data)
    {
        if ($isUnlock) {
            self::unlock();
        }
        return true;
    }
}

(new ScriptSendRealWeekActivityAward())->run();

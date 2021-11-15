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

    /**
     * 发放真人周周领奖多次上传分享海报奖励 - 获取所有当天到当天脚本运行脚本时间内应该结算的活动
     * @return bool
     */
    public function run(): bool
    {
        self::$time           = time();
        self::$todayFirstTime = date("Y-m-d", self::$time);
        // A队列： 获取所有当天应该结算的活动
        $activityList = self::getSendAwardActivityList();
        SimpleLogger::info("ScriptSendRealWeekActivityAward_activity_list", [$activityList]);
        if (empty($activityList)) {
            return true;
        }
        // 获取活动参与用户
        foreach ($activityList as $item) {
            $studentList = self::getPartakeActivityStudentIdList($item['activity_id']);
            if (empty($studentList)) {
                SimpleLogger::info("ScriptSendRealWeekActivityAward_activity_not_found_success_share_poster_user", [$item, $studentList]);
                continue;
            }
            // 放入用户发奖队列
            foreach ($studentList as $_studentId) {
                QueueService::addRealUserPosterAward([
                    'app_id'      => Constants::REAL_APP_ID,
                    'student_id'  => $studentList['student_id'],
                    'activity_id' => $item['activity_id'],
                    'act_status'  => RealUserAwardMagicStoneModel::STATUS_GIVE,
                ]);
            }
            unset($_studentId);
        }
        unset($item);
    }

    /**
     * 获取所有当天到当天脚本运行脚本时间内应该结算的活动
     * @return array
     */
    public static function getSendAwardActivityList(): array
    {
        return RealWeekActivityModel::getRecords(['send_award_time[>=]' => self::$todayFirstTime, 'send_award_time[<=]' => self::$time]);
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
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED
        ], ['student_id']);
        if (empty($studentList)) {
            return [];
        }
        return array_unique(array_column($studentList, 'student_id'));
    }
}

(new ScriptSendRealWeekActivityAward())->run();

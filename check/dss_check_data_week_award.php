<?php
/**
 * 智能业务线
 * 数据监测： 检查智能业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据
 * 脚本只计算新的周周领奖奖励规则产生的数据
 * 检测范围： 10天内产生奖励数据的活动
 * author: qingfeng.lian
 * date: 2021/12/2
 */

namespace Check;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\WeekActivityModel;
use App\Services\CheckDataBaseService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class DssCheckDataWeekAward extends CheckDataBaseService
{
    const LOG_TITLE = 'dss_check_data_week_award';
    const APP_ID    = Constants::SMART_APP_ID;
    const DB_TYPE   = MysqlDB::CONFIG_SLAVE;

    /**
     * 获取学生应得的奖励数量
     * @param $studentId
     * @param $activityId
     * @param $createAwardTime
     * @return int|mixed
     */
    public static function getStudentDeservedRewards($studentId, $activityId, $createAwardTime)
    {
        $sharePosterCount = SharePosterModel::getCount([
            'student_id'      => $studentId,
            'activity_id'     => $activityId,
            'verify_status'   => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time[<=]' => $createAwardTime,
        ]);
        // var_dump($studentId, $activityId,$sharePosterCount, $createAwardTime);
        $activityPassAwardRule = self::getSharePosterPassAwardRule($activityId);
        $deservedRewards = $activityPassAwardRule[$sharePosterCount] ?? [];
        return $deservedRewards['award_amount'] ?? 0;
    }

    /**
     * 获取学生某个活动中指定的分享任务审核通过的上传截图信息
     * @param $sendUserAwardInfo
     * @return array
     */
    public static function getStudentActivitySharePosterInfo($sendUserAwardInfo, $studentInfo)
    {
        $studentUUID = $sendUserAwardInfo['uuid'] ?? '';
        $activityId = $sendUserAwardInfo['activity_id'] ?? 0;
        if (empty($studentUUID) || empty($activityId)) {
            return [];
        }
        $sharePosterList = SharePosterModel::getRecords([
            'student_id'    => $studentInfo['id'],
            'activity_id'   => $activityId,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
        ], ['task_num']);
        return is_array($sharePosterList) ? $sharePosterList : [];
    }

    /**
     * 获取分享截图审核通过奖励规则
     * @param $activityId
     * @return array|mixed
     */
    public static function getSharePosterPassAwardRule($activityId)
    {
        if (!empty(self::$sharePosterPassAwardRule[$activityId])) {
            return self::$sharePosterPassAwardRule[$activityId];
        }
        $ruleList = SharePosterPassAwardRuleModel::getRecords(['activity_id' => $activityId]);
        if (empty($ruleList)) {
            return [];
        }
        self::$sharePosterPassAwardRule[$activityId] = array_column($ruleList, null, 'success_pass_num');
        return self::$sharePosterPassAwardRule[$activityId];
    }

    /**
     * 获取指定时间段内的产生的所有奖励
     * @return array|null
     */
    public static function getCheckData()
    {
        $awardTable = ErpUserEventTaskAwardGoldLeafModel::getTableNameWithDb();
        $sql = 'select award.activity_id,award.uuid,award.create_time,award.status as award_status,award.award_num as award_amount,count(*) as total,' .
            " group_concat(concat_ws('-',passes_num,award_num)) as passes_award" .
            ' from ' . $awardTable . ' as award' .
            ' where award.activity_id>' . self::$oldRuleLastActivityId .
            " and award_node='" . Constants::WEEK_SHARE_POSTER_AWARD_NODE . "'" .
            ' and create_time>=' . self::$checkStartTime . ' and create_time<=' . self::$checkEntTime .
            ' group by award.activity_id, award.uuid';
        return self::$db->queryAll($sql);
    }

    /**
     * 获取学生列表
     * @param $uuids
     * @return array
     */
    public static function getStudentList($uuids)
    {
        return array_column(DssStudentModel::getRecords(['uuid' => array_unique($uuids)], ['id', 'uuid']), null, 'uuid');
    }

    /**
     * 获取活动列表
     * @param $activityIds
     * @return array
     */
    public static function getActivityList($activityIds)
    {
        return array_column(WeekActivityModel::getRecords(['activity_id' => array_unique($activityIds)], ['activity_id', 'send_award_time', 'award_prize_type']), null, 'activity_id');
    }

    public static function formatSendMail($abnormalData)
    {
        $subject = $_ENV['ENV_NAME'] . ' - 数据监测： 智能业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据';
        $content = '';
        foreach ($abnormalData as &$item) {
            $item['err_msg'] = self::ERR_CODE_MSG[$item['err_code']] ?? '';
            $content .= '活动id：' . $item['activity_id'] . '; 用户uuid：' . $item['uuid'] . '; 错误原因：' . $item['err_msg'] . '</br>';
        }
        unset($item);
        return [$subject, $content];
    }
}

(new DssCheckDataWeekAward())->run();

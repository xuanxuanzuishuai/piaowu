<?php
/**
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

use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\WeekActivityModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class DssCheckDataWeekAward
{
    const LOG_TITLE             = 'dss_check_data_week_award';
    const START_DAY             = 10;
    const SEND_AWARD_TIME_DELAY = 12 * 86400;

    const ERR_CODE_ACTIVITY_STUDENT_REPEAT = 1001;
    const ERR_CODE_STUDENT_AWARD_UNEQUAL   = 1002;
    const ERR_CODE_STUDENT_NOT_FOUND       = 1003;
    const ERR_CODE_ACTIVITY_NOT_FOUND      = 1004;
    const ERR_CODE_ACTIVITY_NOT_START      = 1005;
    const ERR_CODE_MSG                     = [
        self::ERR_CODE_ACTIVITY_STUDENT_REPEAT => '同一个用户同一个活动奖励出现重复',
        self::ERR_CODE_STUDENT_AWARD_UNEQUAL   => '用户同一个活动奖励和用户实际应得奖励不符',
        self::ERR_CODE_STUDENT_NOT_FOUND       => '用户不存在',
        self::ERR_CODE_ACTIVITY_NOT_FOUND      => '活动不存在',
        self::ERR_CODE_ACTIVITY_NOT_START      => '活动未到发放奖励时间但是奖励已发放',
    ];
    protected static $checkStartTime           = 0;
    protected static $checkEntTime             = 0;
    protected static $oldRuleLastActivityId    = 0;
    protected static $db                       = null;
    protected static $sharePosterPassAwardRule = [];


    public function __construct()
    {
        self::$checkStartTime        = strtotime(date("Y-m-d H:i:s", strtotime("-" . self::START_DAY . " day")));
        self::$checkEntTime          = time();
        self::$oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        self::$db                    = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    }

    public function run()
    {
        $abnormalData = [];
        // 获取指定时间段内的产生的所有奖励
        $checkData = self::getCheckData();
        if (empty($checkData)) {
            SimpleLogger::info(self::LOG_TITLE, ['msg' => 'data_empty', 'check_data' => $checkData]);
            return false;
        }
        // 获取学生uuid和id
        $studentList = array_column(DssStudentModel::getRecords(['uuid' => array_column($checkData, 'uuid')], ['id', 'uuid']), null, 'uuid');
        // 获取活动信息
        $activityList = array_column(WeekActivityModel::getRecords(['activity_id' => array_column($checkData, 'activity_id')], ['activity_id', 'send_award_time']), null, 'activity_id');

        foreach ($checkData as $item) {
            // if ($item['activity_id'] != 1272) {
            //     continue;
            // }
            $tmp = [
                'err_code'                 => 0,
                'err_msg'                  => '',
                'activity_id'              => $item['activity_id'],
                'create_award_time'        => date("Y-m-d H:i:s", $item['create_time']),
                'uuid'                     => $item['uuid'],
                'student_id'               => 0,
                'send_award_status'        => -1,
                'award_num'                => 0,
                'student_deserved_rewards' => 0,
                'activity_send_award_time' => '',
            ];
            if (empty($studentList[$item['uuid']])) {
                $tmp['err_code'] = self::ERR_CODE_STUDENT_NOT_FOUND;
            } elseif (empty($activityList[$item['activity_id']])) {
                $tmp['err_code'] = self::ERR_CODE_ACTIVITY_NOT_FOUND;
            } elseif ($item['total'] > 1) {
                $tmp['err_code'] = self::ERR_CODE_ACTIVITY_STUDENT_REPEAT;
            } elseif ($item['create_time'] - $activityList[$item['activity_id']]['send_award_time'] > self::SEND_AWARD_TIME_DELAY) {
                // 由于活动奖励发放时间都是系统自动生成发放当天的0点，但是发放脚本时间一般是当天12点开始跑，所以发放时间计算到当天晚上
                $tmp['err_code']                 = self::ERR_CODE_ACTIVITY_NOT_START;
                $tmp['activity_send_award_time'] = date("Y-m-d H:i:s", $activityList[$item['activity_id']]['send_award_time']);
            } else {
                $studentDeservedRewards = self::getStudentDeservedRewards($studentList[$item['uuid']]['id'], $item['activity_id'], $item['create_time']);
                if ($item['award_num'] != $studentDeservedRewards) {
                    $tmp['err_code']                 = self::ERR_CODE_STUDENT_AWARD_UNEQUAL;
                    $tmp['send_award_status']        = $item['status'];
                    $tmp['award_num']                = $item['award_num'];
                    $tmp['student_deserved_rewards'] = $studentDeservedRewards;
                    $tmp['student_id']               = $studentList[$item['uuid']]['id'];
                }
            }
            if (!empty($tmp['err_code'])) {
                $abnormalData[] = $tmp;
            }
        }
        unset($item);

        // 异常数据需要发到邮箱提醒（邮箱组）
        if (!empty($abnormalData)) {
            self::sendMail($abnormalData);
        }
        SimpleLogger::info(self::LOG_TITLE, ['msg' => 'SUCCESS']);
        return true;
    }

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
            'verify_time[<=]' => $createAwardTime
        ]);
        // var_dump($studentId, $activityId,$sharePosterCount, $createAwardTime);
        $activityPassAwardRule = self::getSharePosterPassAwardRule($activityId);
        $deservedRewards       = $activityPassAwardRule[$sharePosterCount] ?? [];
        return $deservedRewards['award_amount'] ?? 0;
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
        $sql        = 'select award.activity_id,award.uuid,award.uuid,award.create_time,award.status,award.award_num,count(*) as total from ' . $awardTable . ' as award' .
            ' where award.activity_id>' . self::$oldRuleLastActivityId .
            ' and create_time>=' . self::$checkStartTime . ' and create_time<=' . self::$checkEntTime .
            ' group by award.activity_id, award.uuid';
        return self::$db->queryAll($sql);
    }

    public static function sendMail($abnormalData)
    {
        $mail = DictConstants::get(DictConstants::CHECK_DATA_ABNORMAL_MAIL, 'dss_check_data_week_award_mail');
        if (empty($mail)) {
            SimpleLogger::info(self::LOG_TITLE, ['msg' => 'mail_empty']);
            return false;
        }
        $subject = $_ENV['ENV_NAME'] . ' - 数据监测： 智能业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据';
        $content = '';
        foreach ($abnormalData as &$item) {
            $item['err_msg'] = self::ERR_CODE_MSG[$item['err_code']] ?? '';
            switch ($item['err_code']) {
                case self::ERR_CODE_ACTIVITY_STUDENT_REPEAT:
                case self::ERR_CODE_STUDENT_AWARD_UNEQUAL:
                case self::ERR_CODE_STUDENT_NOT_FOUND:
                case self::ERR_CODE_ACTIVITY_NOT_FOUND:
                case self::ERR_CODE_ACTIVITY_NOT_START:
                    $content .= '活动id：' . $item['activity_id'] . '; 用户uuid：' . $item['uuid'] . '; 错误原因：' . $item['err_msg'] . '</br>';
                    break;
                default:
                    $content .= '未知的err_code' . $item['err_code'] . '错误数据：' . json_encode($item) . '</br>';
                    break;
            }
        }
        unset($item);
        $dataFilePath = '/tmp/' . self::LOG_TITLE . time() . '.txt';
        file_put_contents($dataFilePath, json_encode($abnormalData));
        PhpMail::sendEmail(explode(',', $mail), $subject, $content, $dataFilePath);
        unlink($dataFilePath);
        return true;
    }
}

(new DssCheckDataWeekAward())->run();

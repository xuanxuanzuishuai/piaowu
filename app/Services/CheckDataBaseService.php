<?php
/**
 * 智能业务线
 * 数据监测： 检查智能业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据
 * 脚本只计算新的周周领奖奖励规则产生的数据
 * 检测范围： 10天内产生奖励数据的活动
 * author: qingfeng.lian
 * date: 2021/12/2
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;

abstract class CheckDataBaseService
{
    const START_DAY             = 10;       // 往前推多少天
    const APP_ID                = 0;        // 业务线ID
    const SEND_AWARD_TIME_DELAY = 12 * 86400;   // 发奖时间延后的秒数
    const DB_TYPE               = 'default';    // 连接数据库的名字
    const LOG_TITLE             = 'check_data'; // 日志输出标记

    // 错误码
    const ERR_CODE_ACTIVITY_STUDENT_REPEAT = 1001;
    const ERR_CODE_STUDENT_AWARD_UNEQUAL   = 1002;
    const ERR_CODE_STUDENT_NOT_FOUND       = 1003;
    const ERR_CODE_ACTIVITY_NOT_FOUND      = 1004;
    const ERR_CODE_ACTIVITY_NOT_START      = 1005;
    // 错误码对应的文字
    const ERR_CODE_MSG = [
        self::ERR_CODE_ACTIVITY_STUDENT_REPEAT => '同一个用户同一个活动奖励出现重复',
        self::ERR_CODE_STUDENT_AWARD_UNEQUAL   => '用户同一个活动奖励和用户实际应得奖励不符',
        self::ERR_CODE_STUDENT_NOT_FOUND       => '用户不存在',
        self::ERR_CODE_ACTIVITY_NOT_FOUND      => '活动不存在',
        self::ERR_CODE_ACTIVITY_NOT_START      => '活动未到发放奖励时间但是奖励已发放',
    ];
    protected static $checkStartTime           = 0;     // 获取活动的时间范围 - 开始时间
    protected static $checkEntTime             = 0;     // 获取活动的时间范围 - 截止时间
    protected static $oldRuleLastActivityId    = 0;     // 老规则最后的活动的id
    protected static $db                       = null;  // 数据库连接方式
    protected static $sharePosterPassAwardRule = [];    // 上传分享截图审核通过次数对应的奖励规则 （二维数据）


    /**
     * @throws RunTimeException
     */
    public function __construct()
    {
        if (empty(static::APP_ID)) {
            SimpleLogger::info('app_id_is_error', [static::APP_ID]);
            throw new RunTimeException(['app_id_is_error'], [static::APP_ID]);
        }
        if (empty(static::DB_TYPE)) {
            SimpleLogger::info('app_id_is_error', [static::DB_TYPE]);
            throw new RunTimeException(['app_id_is_error'], [static::DB_TYPE]);
        }
        self::$checkStartTime = strtotime(date("Y-m-d H:i:s", strtotime("-" . static::START_DAY . " day")));
        self::$checkEntTime   = time();
        self::$db             = MysqlDB::getDB(static::DB_TYPE);
        self::setOldRuleLastActivityId();
    }

    public function run()
    {
        SimpleLogger::info(static::LOG_TITLE, ['msg' => 'START']);
        $abnormalData = [];
        // 获取指定时间段内的产生的所有奖励
        $checkData = static::getCheckData();
        if (empty($checkData)) {
            SimpleLogger::info(self::LOG_TITLE, ['msg' => 'data_empty', 'check_data' => $checkData]);
            return false;
        }
        // 获取学生uuid和id
        $studentList = static::getStudentList(array_column($checkData, 'uuid'));
        // 获取活动信息
        $activityList = static::getActivityList(array_column($checkData, 'activity_id'));

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
                $studentDeservedRewards = static::getStudentDeservedRewards($studentList[$item['uuid']]['id'], $item['activity_id'], $item['create_time']);
                if ($item['award_amount'] != $studentDeservedRewards) {
                    $tmp['err_code']                 = self::ERR_CODE_STUDENT_AWARD_UNEQUAL;
                    $tmp['send_award_status']        = $item['award_status'];
                    $tmp['award_num']                = $item['award_amount'];
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


    public static function sendMail($abnormalData)
    {
        list($subject, $content) = static::formatSendMail($abnormalData);
        $mail = self::getCheckDataAbnormalMail(static::APP_ID, Constants::WEEK_SHARE_POSTER_AWARD_NODE);
        if (empty($mail)) {
            SimpleLogger::info(self::LOG_TITLE, ['msg' => 'mail_empty']);
            return false;
        }
        $dataFilePath = '/tmp/' . static::LOG_TITLE . '_' . time() . '.txt';
        file_put_contents($dataFilePath, json_encode($abnormalData));
        PhpMail::sendEmail($mail, $subject, $content, $dataFilePath);
        unlink($dataFilePath);
        return true;
    }

    /**
     * 获取老规则最后的一个活动的id
     */
    public static function setOldRuleLastActivityId()
    {
        switch (static::APP_ID) {
            case Constants::REAL_APP_ID:
                self::$oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
                break;
            case Constants::SMART_APP_ID:
                self::$oldRuleLastActivityId = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
                break;
            default:
                break;
        }
    }

    /**
     * 数据异常时发送的邮件列表
     * @param $appId
     * @param $awardNode
     * @return array|false|string[]
     */
    public static function getCheckDataAbnormalMail($appId, $awardNode)
    {
        $mail = [];
        if ($appId == Constants::REAL_APP_ID && $awardNode == Constants::WEEK_SHARE_POSTER_AWARD_NODE) {
            $mail = DictConstants::get(DictConstants::CHECK_DATA_ABNORMAL_MAIL, 'dss_check_data_week_award_mail');
            $mail = !empty($mail) ? explode(',', $mail) : [];
        } elseif ($appId == Constants::SMART_APP_ID && $awardNode == Constants::WEEK_SHARE_POSTER_AWARD_NODE) {
            $mail = DictConstants::get(DictConstants::CHECK_DATA_ABNORMAL_MAIL, 'dss_check_data_week_award_mail');
            $mail = !empty($mail) ? explode(',', $mail) : [];
        }
        return $mail;
    }

    // 获取检查数据
    abstract public static function getCheckData();

    // 获取学生应得的奖励
    abstract public static function getStudentDeservedRewards($studentId, $activityId, $createAwardTime);

    // 发送邮件
    abstract public static function formatSendMail($abnormalData);

    // 获取学生列表
    abstract public static function getStudentList($uuids);

    // 获取活动列表
    abstract public static function getActivityList($activityIds);
}

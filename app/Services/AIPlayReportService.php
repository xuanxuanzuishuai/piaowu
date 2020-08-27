<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:28 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AIPlayRecordModel;
use App\Models\DayReportFabulousModel;
use App\Models\ReviewCourseModel;
use App\Models\ReviewCourseTaskModel;
use App\Services\Queue\PushMessageTopic;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;
use App\Libs\DictConstants;
use Lcobucci\JWT\Parser;

class AIPlayReportService
{
    const signKey = "wblMloJrdkUwIxVLchlXB9Unvr68dJo";

    public static function getShareReportToken($studentId, $date)
    {
        $builder = new Builder();
        $signer = new Sha256();
        $builder->set("student_id", $studentId);
        $builder->set("date", $date);
        $builder->sign($signer, self::signKey);
        $token = $builder->getToken();
        return (string)$token;
    }

    /**
     * 解析jwt获取信息
     * @param $token
     * @return array
     * @throws RunTimeException
     */
    public static function parseShareReportToken($token)
    {
        $parse = (new Parser())->parse((string)$token);
        $signer = new Sha256();
        if (!$parse->verify($signer, self::signKey)) {
            throw new RunTimeException(['error_share_token']);
        };
        $studentId = $parse->getClaim("student_id");
        $date = $parse->getClaim("date");
        return ["student_id" => $studentId, "date" => $date];
    }

    /**
     * 学生练琴日报
     * @param $studentId
     * @param null $date
     * @return array
     * @throws RunTimeException
     */
    public static function getDayReport($studentId, $date = null)
    {
        if (empty($date)) {
            $date = date('Ymd');
        }

        $report = AIPlayRecordService::getDayReportData($studentId, $date);
        $report["day_report_fabulous"] = self::getDayReportFabulous($studentId, $date);
        $report["share_token"] = self::getShareReportToken($studentId, $date);
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        return $report;
    }

    /**
     * 学生练琴日报(分享)
     * @param $shareToken
     * @return array
     * @throws RunTimeException
     */
    public static function getSharedDayReport($shareToken)
    {
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $report = AIPlayRecordService::getDayReportData($shareTokenInfo["student_id"], $shareTokenInfo["date"]);

        $channel_id = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, 'shared_day_report_channel_id');
        $TicketData = UserService::getUserQRAliOss($shareTokenInfo["student_id"], 1, $channel_id);
        $playShareAssessUrl = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'play_share_assess_url');
        $data = array(
            'ad' => 0,
            'channel_id' => $channel_id,
            'referee_id' => $TicketData['qr_ticket']
        );

        $report['share_url'] = $playShareAssessUrl . '?' . http_build_query($data);
        $report['qr_ticket_image'] = empty($TicketData['qr_url']) ? '' : AliOSS::signUrls($TicketData['qr_url']);
        $report["share_token"] = $shareToken;
        $report['replay_token'] = AIBackendService::genStudentToken($shareTokenInfo["student_id"]);
        $report["day_report_fabulous"] = self::getDayReportFabulous($shareTokenInfo["student_id"], $shareTokenInfo["date"]);
        return $report;
    }

    /**
     * 日报点赞
     * @param $shareToken
     * @param $openId
     * @return array|string[]
     * @throws RunTimeException
     */
    public static function dayReportFabulous($shareToken, $openId)
    {
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $fabulousRew = DayReportFabulousModel::getRecord(['student_id' => $shareTokenInfo['student_id'], 'open_id' => $openId, 'day_report_date' => $shareTokenInfo["date"]], ['id']);

        if (!empty($fabulousRew)) {
            throw new RunTimeException(['student_is_fabulous']);
        }

        $id = DayReportFabulousModel::insertRecord(['student_id' => $shareTokenInfo['student_id'], 'open_id' => $openId, 'create_time' => time(), 'day_report_date' => $shareTokenInfo["date"]], false);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * 日报自己给自己点赞
     * @param $openId
     * @param $studentId
     * @param $date
     * @return array|string[]
     * @throws RunTimeException
     */
    public static function dayReportOneSelfFabulous($openId, $studentId, $date)
    {
        $startTime = strtotime($date);
        $endTime = $startTime + 86399;
        $dateFormat = preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $date);
        $aiPlayRecord = AIPlayRecordModel::getRecord(['student_id' => $studentId, 'end_time[<>]' => [$startTime, $endTime]]);

        if (strtotime($date) > time() || !$dateFormat || empty($aiPlayRecord)) {
            throw new RunTimeException(['Please fill in the correct date']);
        }

        $fabulousRew = DayReportFabulousModel::getRecord(['student_id' => $studentId, 'open_id' => $openId, 'day_report_date' => $date], ['id']);

        if (!empty($fabulousRew)) {
            throw new RunTimeException(['student_is_fabulous']);
        }

        $id = DayReportFabulousModel::insertRecord(['student_id' => $studentId, 'open_id' => $openId, 'create_time' => time(), 'day_report_date' =>$date], false);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * 统计日报点赞数
     * @param $studentId
     * @param $date
     * @return int|number
     */
    public static function getDayReportFabulous($studentId, $date)
    {
        $dayReportFabulous = DayReportFabulousModel::getTotalCount($studentId, $date);
        return $dayReportFabulous ?? 0;
    }

    /**
     * 获取学生练琴日历
     * @param $studentId
     * @param $year
     * @param $month
     * @return array
     */
    public static function getPlayCalendar($studentId, $year, $month)
    {
        $startTime = strtotime($year . "-" . $month);
        $endTime = strtotime('+1 month', $startTime) - 1;
        $monthSum = AIPlayRecordModel::getStudentSumByDate($studentId, $startTime, $endTime);

        $where = [
            'play_date[>=]' => date('Ymd', $startTime),
            'play_date[<=]' => date('Ymd', $endTime),
            's.id' => $studentId,
            's.has_review_course' => [ReviewCourseModel::REVIEW_COURSE_49, ReviewCourseModel::REVIEW_COURSE_1980],
            'rct.status' => [ReviewCourseTaskModel::STATUS_SEND_SUCCESS, ReviewCourseTaskModel::STATUS_SEND_FAILURE],
        ];
        list($total, $tasks) = ReviewCourseTaskModel::getTasks($where);
        if ($total > 0) {
            $tasks = array_column($tasks, 'id', 'play_date');
        }

        foreach ($monthSum as $i => $daySum) {
            $monthSum[$i]['review_task_id'] = $tasks[$daySum['play_date']] ?? 0;
        }

        return $monthSum;
    }

    /**
     * 单课成绩单
     * @param $studentId
     * @param $lessonId
     * @param null $date
     * @return array
     * @throws RunTimeException
     */
    public static function getLessonTestReport($studentId, $lessonId, $date = null)
    {
        if (empty($date)) {
            $date = date('Ymd');
        }

        $report = AIPlayRecordService::getLessonTestReportData($studentId, $lessonId, $date);
        $report["share_token"] = self::getShareReportToken($studentId, $date);
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        return $report;
    }

    /**
     * 单课成绩单(分享)
     * @param $shareToken
     * @param $lessonId
     * @return array
     * @throws RunTimeException
     */
    public static function getSharedLessonTestReport($shareToken, $lessonId)
    {
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $report = AIPlayRecordService::getLessonTestReportData($shareTokenInfo["student_id"], $lessonId, $shareTokenInfo["date"]);
        $report["share_token"] = $shareToken;
        $report['replay_token'] = AIBackendService::genStudentToken($shareTokenInfo["student_id"]);
        return $report;
    }

    /**
     * 获取学生评测报告（分享
     * @param $recordId
     * @return array|mixed
     */
    public static function getAssessResult($recordId)
    {
        $report = AIPlayRecordService::getStudentAssessData($recordId);
        return $report;
    }

    /**
     * 发送日报
     * @param $dateTime
     */
    public static function sendDailyReport($dateTime)
    {
        $startTime = $dateTime;
        $endTime = $startTime + 86400;
        $date = date("Y-m-d", $startTime);

        $userInfo = AIPlayRecordModel::getPlayedStudentInfo($startTime, $endTime);

        $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/dailyNew?date=" . $date;
        $templateId = $_ENV["WECHAT_DAY_PLAY_REPORT"];
        $data = [
            'first' => [
                'value' => "宝贝今天的练琴日报已生成，宝贝很棒哦！继续加油！",
                'color' => "#323d83"
            ],
            'keyword1' => [
                'value' => $date,
                'color' => "#323d83"
            ],
            'keyword2' => [
                'value' => "请查看详情",
                'color' => "#323d83"
            ],
            'keyword3' => [
                'value' => "请查看详情",
                'color' => "#323d83"
            ],
        ];
        $msgBody = [
            'wx_push_type' => 'template',
            'template_id' => $templateId,
            'data' => $data,
            'url' => $url,
            'open_id' => '',
        ];

        try {
            $topic = new PushMessageTopic();

        } catch (\Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', [
                '$dateTime' => $dateTime,
            ]);
            return ;
        }

        foreach ($userInfo as $info) {
            $msgBody['open_id'] = $info['open_id'];

            try {
                $topic->wxPushCommon($msgBody)->publish(rand(0, 1200));

            } catch (\Exception $e) {
                SimpleLogger::error("sendDailyReport send failure", ['info' => $info]);
                continue;
            }
        }
    }
}
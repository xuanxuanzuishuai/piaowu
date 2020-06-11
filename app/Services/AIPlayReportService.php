<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:28 PM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\AIPlayRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\ReviewCourseTaskModel;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;
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
        $report["share_token"] = $shareToken;
        $report['replay_token'] = AIBackendService::genStudentToken($shareTokenInfo["student_id"]);
        return $report;
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
}
<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:28 PM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\StudentModel;
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
        if (!$parse->verify($signer,self::signKey)) {
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
}
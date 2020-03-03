<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/14
 * Time: 3:47 PM
 */

namespace App\Libs;


class AIPLClass
{
    const API_REPORT = '/report';
    const API_CLASS_REPORT = '/class_report';

    /**
     * 获取点评话术
     * @param $uuid
     * @param $dateTime
     * @return string
     */
    public static function getReviewText($uuid, $dateTime)
    {
        $host = DictConstants::get(DictConstants::SERVICE, 'ai_class_host');

        $result = HttpHelper::requestJson($host . self::API_REPORT, [
            'date' => $dateTime,
            'uuid' => $uuid
        ]);

        if (empty($result) || $result['code'] != 0) {
            SimpleLogger::error("[AIPLClass getReviewText] error", ['errors' => $result['errs'] ?? null]);
            return false;
        }

        return $result['data']['report'] ?? '';
    }

    /**
     * 获取课堂报告详情
     * @param $uuid
     * @param $dateTime
     * @return array|bool
     */
    public static function getClassReport($uuid, $dateTime)
    {
        $host = DictConstants::get(DictConstants::SERVICE, 'ai_class_host');

        $result = HttpHelper::requestJson($host . self::API_CLASS_REPORT, [
            'date' => $dateTime,
            'uuid' => $uuid
        ]);

        if (empty($result) || $result['code'] != 0) {
            SimpleLogger::error("[AIPLClass getClassReport] error", ['errors' => $result['errs'] ?? null]);
            return [];
        }

        return $result['data'] ?? [];
    }
}
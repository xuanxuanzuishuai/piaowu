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
}
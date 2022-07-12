<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Util;
use App\Services\SendSmsService;

class CollectionService
{
    /**
     * 发送开班短信息
     * @param $sourceAppId
     * @param $collectionData
     * @param $studentData
     */
    public static function sendDivideIntoClassesMessage($sourceAppId, $collectionData, $studentData)
    {
        if ($sourceAppId == Constants::QC_APP_ID) {
            self::qcDivideIntoClassesMessage($collectionData['wx_number'],
                $studentData);
        }
    }

    /**
     * 清晨业务线体验课班级分配成功后发送短信息
     * @param $wxNumber
     * @param $studentData
     */
    private static function qcDivideIntoClassesMessage($wxNumber, $studentData)
    {
        $content = DictConstants::get(DictConstants::MESSAGES_TEMPLATE, "qc_divide_classes");
        if (empty($content)) {
            Util::errorCapture("qing chen divide into classes message params error",
                ['wx_number' => $wxNumber, 'content' => $content]);
        }
        $shortUrl = ((new Dss())->getShortUrl($_ENV['REFERRAL_FRONT_DOMAIN'] . '/morningMarket/pay_succeed?uuid=' . $studentData['uuid']))['data']['short_url'];
        $res = SendSmsService::sendQcDistributeClass($studentData['mobile'], $shortUrl);
        if (empty($res)) {
            Util::errorCapture("qing chen divide into classes message send fail",
                ['mobile' => $studentData['mobile'], 'content' => $content]);
        }
    }
}
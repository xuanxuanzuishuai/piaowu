<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\NewSMS;
use App\Libs\Util;
use App\Services\CommonServiceForApp;

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
                $studentData['mobile']);
        }
    }

    /**
     * 清晨业务线体验课班级分配成功后发送短信息
     * @param $wxNumber
     * @param $mobile
     */
    private static function qcDivideIntoClassesMessage($wxNumber, $mobile)
    {
        $content = DictConstants::get(DictConstants::MESSAGES_TEMPLATE, "qc_divide_classes");
        if (empty($content)) {
            Util::errorCapture("qing chen divide into classes message params error",
                ['wx_number' => $wxNumber, 'content' => $content]);
        }
        $smsObj = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $shortUrl = ((new Dss())->getShortUrl('http://www.xiaoyezi.com/html/study_piano_download.html'))['data']['short_url'];
        $res = $smsObj->sendCommonSms(Util::pregReplaceTargetStr($content, ['content1' => $shortUrl]), $mobile,
            CommonServiceForApp::SIGN_STUDENT_QC_APP);
        if (empty($res)) {
            Util::errorCapture("qing chen divide into classes message send fail",
                ['mobile' => $mobile, 'content' => $content]);
        }
    }
}
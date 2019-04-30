<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/29
 * Time: 2:40 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Models\QRCodeModel;

class QRCodeService
{
    public static function getQR($params)
    {
        $qrData = QRCodeModel::get($params);

        if (empty($qrData)) {
            QRCodeModel::add($params, true);
        }

        $qrData = QRCodeModel::get($params);
        if (!empty($qrData['qr_image'])) {
            $qrData['qr_image'] = AliOSS::signUrls($qrData['qr_image']);
        }

        return $qrData;
    }

    /**
     * 获取老师绑定机构二维码
     * @param $orgId
     * @return mixed
     */
    public static function getOrgTeacherBindQR($orgId)
    {
        $qrParams = [
            'type' => QRCodeModel::TYPE_ORG_BIND_TEACHER,
            'landing_type' => QRCodeModel::LANDING_TYPE_WX,
            'org_id' => $orgId
        ];
        return self::getQR($qrParams);
    }

    /**
     * 获取学生绑定机构二维码
     * @param $orgId
     * @return mixed
     */
    public static function getOrgStudentBindQR($orgId)
    {
        $qrParams = [
            'type' => QRCodeModel::TYPE_ORG_BIND_STUDENT,
            'landing_type' => QRCodeModel::LANDING_TYPE_WX,
            'org_id' => $orgId
        ];
        return self::getQR($qrParams);
    }

    /**
     * 获取学生绑定机构转介绍二维码
     * @param $orgId
     * @param $refereeType
     * @param $refereeId
     * @return mixed
     */
    public static function getOrgStudentBindRefereeQR($orgId, $refereeType, $refereeId)
    {
        $qrParams = [
            'type' => QRCodeModel::TYPE_ORG_BIND_STUDENT,
            'landing_type' => QRCodeModel::LANDING_TYPE_WX,
            'org_id' => $orgId,
            'referee_type' => $refereeType,
            '$refereeId' => $refereeId
        ];
        return self::getQR($qrParams);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 6:51 PM
 */

namespace App\Services\RealStudentOverseas;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Services\CommonServiceForApp;
use App\Services\RealStudentService;

class DeliveryService
{
    /**
     * 投放第一版2022-02-21
     * @param $params
     * @return array|bool|mixed
     * @throws RunTimeException
     */
    public static function deliveryV1($params)
    {
        //注册
        $studentData = self::do($params);
        //获取客服信息
        $dictConfig = DictConstants::getTypesMap([DictConstants::REAL_STUDENT_OVERSEAS_DELIVERY['type']]);
        $studentData['default_cc_mobile'] = $dictConfig[DictConstants::REAL_STUDENT_OVERSEAS_DELIVERY['type']]['default_cc_mobile']['value'];
        $studentData['default_cc_qr_code'] = $dictConfig[DictConstants::REAL_STUDENT_OVERSEAS_DELIVERY['type']]['default_cc_qr_code']['value'];
        return $studentData;
    }


    /**
     * 执行
     * @param mixed ...$args
     * @return array|bool|mixed
     * @throws RunTimeException
     */
    public static function do(...$args)
    {
        if (empty($args) || empty($args[0]['params'])) {
            throw new RunTimeException(['params_empty']);
        }
        //参数
        $params = $args[0]['params'];
        $phoneNumberValid = Util::validPhoneNumber($params['mobile'], $params['country_code']);
        if (empty($phoneNumberValid)) {
            throw new RunTimeException(['invalid_mobile']);
        }

        //校验社交联系账号参数
        $extData = [];
        if (!empty($params['social_account'])) {
            self::formatSocialAccount($params['social_account'], $params['social_account_type'], $extData);
        } else {
            !empty($params['user_name']) && $extData['name'] = $params['user_name'];
            !empty($params['wechat']) && $extData['wechat'] = trim($params['wechat']);
            if (!empty($params['email'])) {
                if (filter_var($params['email'], FILTER_VALIDATE_EMAIL) != true) {
                    throw new RunTimeException(['email_address_error']);
                }
                $extData['email'] = trim($params['email']);
            }
        }
        $studentData = RealStudentService::register($params['mobile'], $params['country_code'], $params['channel_id'], [], [], $params['login_type'], $extData);
        unset($studentData['student_id']);
        return $studentData;
    }

    /**
     * 格式化处理社交账号数据
     * @param $params
     * @throws RunTimeException
     */
    private static function formatSocialAccount($socialAccount, $socialAccountType, &$extData)
    {
        if (($socialAccountType == ErpStudentModel::SOCIAL_ACCOUNT_TYPE_EMAIL)) {
            if (filter_var($socialAccount, FILTER_VALIDATE_EMAIL) != true) {
                throw new RunTimeException(['email_address_error']);
            }
            $extData['email'] = trim($socialAccount);
        } elseif ($socialAccountType == ErpStudentModel::SOCIAL_ACCOUNT_TYPE_WX) {
            $extData['wechat'] = trim($socialAccount);
        } else {
            $extData[] = '';
        }
    }
}
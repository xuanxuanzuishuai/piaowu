<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2022/4/12
 * Time: 3:10 下午
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\Erp;
use App\Libs\NewSMS;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;

class StudentWebCommonService
{
    /**
     * 用户登录
     * @param $params
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function login($params)
    {
        if ($params['app_id'] == Constants::REAL_APP_ID) {
            //注册真人用户信息
            $studentInfo = ErpStudentModel::getRealUserInfoByMobile($params['mobile']);
        } elseif ($params['app_id'] == Constants::SMART_APP_ID) {
            //注册智能用户信息
            $studentInfo = DssStudentModel::getRecord(['mobile' => $params['mobile']],['id(student_id)','uuid']);
        }

        if (empty($studentInfo)) {
            $studentInfo = self::register($params);
        }

        $token = CommonWebTokenService::generateToken(
            $studentInfo['student_id'],
            Constants::USER_TYPE_STUDENT,
            $params['app_id']);
        return [
            'app_id'     => $params['app_id'],
            'student_id' => $studentInfo['student_id'],
            'uuid'       => $studentInfo['uuid'],
            'mobile'     => $params['mobile'],
            'token'      => $token,
        ];
    }

    /**
     * 用户注册
     * @param $params
     * @return array|bool|mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function register($params)
    {
        if ($params['app_id'] == Constants::REAL_APP_ID) {
            //注册真人用户信息
            $studentInfo = (new Erp())->refereeStudentRegister([
                'app_id'       => $params['app_id'],
                'mobile'       => $params['mobile'],
                'country_code' => NewSMS::DEFAULT_COUNTRY_CODE,
                'channel_id'   => $params['channel_id'],
            ]);
        } elseif ($params['app_id'] == Constants::SMART_APP_ID) {
            //注册智能用户信息
            $studentInfo = (new Dss())->studentRegisterBound([
                'mobile'     => (string)$params['mobile'],
                'channel_id' => $params['channel_id']
            ]);
        }
        return $studentInfo ?? [];
    }
}
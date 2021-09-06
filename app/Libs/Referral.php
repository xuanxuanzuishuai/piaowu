<?php


namespace App\Libs;

class Referral
{
    //真人陪练-自动审核接口
    const REAL_CHECK_POSTER = '/boss/admin/poster/auto_check';

    //获取用户二维码
    const GET_USER_QR = '/api/student_weixin/get_user_qr';

    //建立转介绍关系
    const SET_REFERRAL_USER_REFEREE = '/minipro/user/set_referral_user_referee';

    private $host;

    public function __construct()
    {
        $this->host = $_ENV['REFERRAL_HOST'];
    }

    public function realCheckPoster($params)
    {
        return HttpHelper::requestJson($this->host . self::REAL_CHECK_POSTER, $params, 'POST');
    }



    public function getUserOrImg($params)
    {
        return HttpHelper::requestJson($this->host . self::GET_USER_QR, $params);
    }

    /**
     * 建立真人转介绍关系
     * @param $params
     * @return array|bool
     */
    public function setReferralUserReferee($params)
    {
        return HttpHelper::requestJson($this->host . self::SET_REFERRAL_USER_REFEREE, $params, 'POST');
    }

}
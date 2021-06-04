<?php


namespace App\Libs;

class Referral
{
    //真人陪练-自动审核接口
    const REAL_CHECK_POSTER = '/boss/admin/poster/auto_check';

    private $host;

    public function __construct()
    {
        $this->host = $_ENV['REFERRAL_HOST'];
    }

    public function realCheckPoster($params)
    {
        return HttpHelper::requestJson($this->host . self::REAL_CHECK_POSTER, $params, 'POST');
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/7/30
 * Time: 4:19 PM
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;
use App\Services\DictService;
use Classroom\Libs\Request;
use Classroom\Services\ScheduleService;

class PandaService
{
    const STUDENT_SERVICE = 'PANDA_STUDENT'; //学生服务号
    const REFRESH_ACCESS_TOKEN = '/internal/auth/refresh_token'; //刷新
    const GET_ACCESS_TOKEN = '/internal/auth/access_token'; //获取



    private $host;

    public function __construct()
    {
        $this->host = $_ENV['PANDA_SERVICE_HOST'];
    }

    private function commonAPI($api, $data = [], $method = 'GET')
    {
        try {
            $fullUrl = $this->host . $api;
            $response = HttpHelper::requestJson($fullUrl, $data, $method);

            return $response;
        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [$e->getMessage()]);
        }
        return false;
    }

    /**
     * 要一个新的access_token
     * @param $params
     * @return mixed
     * @throws RunTimeException
     */
    public function updateAccessToken()
    {
        $params = [
            'service_name'=>self::STUDENT_SERVICE,
            'token'=>$this->getJwtToken(null),
        ];

        $data = self::commonAPI(self::REFRESH_ACCESS_TOKEN, $params);

        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_fail']);
        }
        return !empty($data['data']) ? $data['data'] : NULL;
    }

    /**
     *
     * @param array $params
     * @return mixed|null
     * @throws RunTimeException
     */
    public function getAccessToken(){

        $params = [
            'service_name'=>self::STUDENT_SERVICE,
            'token'=>$this->getJwtToken(null),
        ];

        $data = self::commonAPI(self::GET_ACCESS_TOKEN, $params);

        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_fail']);
        }
        return !empty($data['data']) ? $data['data'] : NULL;
    }


    public function getJwtToken($scheduleId){
        list($issuer, $audience, $expire, $signerKey, $tokenTypeUser) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV,
            [
                Constants::DICT_KEY_JWT_ISSUER,
                Constants::DICT_KEY_JWT_AUDIENCE,
                Constants::DICT_KEY_JWT_EXPIRE,
                Constants::DICT_KEY_JWT_SIGNER_KEY,
                Constants::DICT_KEY_TOKEN_TYPE_USER
            ]);
        $jwtUtils = new JWTUtils($issuer, $audience, $expire, $signerKey);
        $token = $jwtUtils->getToken(0, $scheduleId,null);
        return $token;
    }





}
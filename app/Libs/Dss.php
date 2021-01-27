<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/7/30
 * Time: 4:19 PM
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;

class Dss
{
    const REFRESH_ACCESS_TOKEN = '/api/wechat/refresh_token'; //刷新
    const ADD_STUDENT = '/op/user/register_bound'; //添加学生
    const ADD_USER_TICKET = '/op/user/save_ticket'; // 保存ticket
    const GET_TOKEN = '/op/user/get_token'; //获取token
    const GET_TOKEN_UUID_INFO = '/api/operation/get_uuid';//获取token对应的uuid信息

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "dss_host");
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
    public function updateAccessToken($params)
    {
        $data = self::commonAPI(self::REFRESH_ACCESS_TOKEN, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_fail']);
        }
        return !empty($data['data']) ? $data['data'] : NULL;
    }

    /**
     * 学生注册
     * @param $params
     * @throws RunTimeException
     */
    public function studentRegisterBound($params)
    {
        $data = self::commonAPI(self::ADD_STUDENT, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_fail']);
        }
    }


    public function saveTicket($data)
    {
        $res = self::commonAPI(self::ADD_USER_TICKET, $data, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('Save ticket error', [$res, $data]);
        }
    }

    /**
     * 获取可用token
     * @param $data
     * @return mixed|null
     */
    public function getToken($data)
    {
        $res = self::commonAPI(self::GET_TOKEN, $data, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('Save ticket error', [$res, $data]);
        }
        return !empty($res['data']) ? $res['data'] : NULL;
    }

    /**
     * token对应的信息
     * @param $data
     * @return mixed|null
     */
    public function getTokenRelateUuid($data)
    {
        $res = self::commonAPI(self::GET_TOKEN_UUID_INFO, $data);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('Save ticket error', [$res, $data]);
        }
        return !empty($res['data']) ? $res['data'] : NULL;
    }
}
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
    const CREATE_BILL = '/op/user/create_bill';//小程序创建订单
    const GET_TRAIL_INFO = '/op/user/get_trail_log';//得到用户的购买体验课信息

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
     * @return mixed|null
     * @throws RunTimeException
     */
    public function studentRegisterBound($params)
    {
        $data = self::commonAPI(self::ADD_STUDENT, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_fail']);
        }
        return !empty($data['data']) ? $data['data'] : NULL;
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
            SimpleLogger::error('get token error', [$res, $data]);
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
            SimpleLogger::error('get relate uuid error', [$res, $data]);
        }
        return !empty($res['data']) ? $res['data'] : NULL;
    }

    /**
     * 创建订单
     * @param $data
     * @return mixed|null
     */
    public function createBill($data)
    {
        $res = self::commonAPI(self::CREATE_BILL, $data);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('create bill error', [$res, $data]);
        }
        return !empty($res['data']) ? $res['data'] : NULL;
    }

    /**
     * 得到用户的体验课信息
     * @param $studentId
     * @return mixed|null
     */
    public function getTrailInfo($studentId)
    {
        $res = self::commonAPI(self::GET_TRAIL_INFO, ['student_id' => $studentId]);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('create bill error', [$res, $studentId]);
        }
        return !empty($res['data']) ? $res['data'] : NULL;
    }
}
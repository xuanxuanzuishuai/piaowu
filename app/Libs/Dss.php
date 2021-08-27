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
    const GET_ACTIVITY_INFO = '/op/referral/activity_info';//周周有礼活动页
    const UPLOAD_SHARE_POSTER = '/op/referral/upload_share_poster';//周周有礼上传图片信息
    const ASSISTANT_LIST = '/op/employee/assistant_list';//获取助教列表
    const WEIXIN_INFO = '/op/user/get_weixin_info'; //同步获取微信信息
    const GET_SHORT_URL = '/op/sms/get_short_url'; //获取短链
    const GET_STUDENT_IDENTITY = '/op/user/get_student_identity'; //获取用户的状态

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
            throw new RunTimeException(['user_register_fail']);
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

    /**
     * 获取周周活动信息
     * @param int $studentId
     * @return mixed|null
     */
    public function getActivityInfo($studentId)
    {
        $res = self::commonAPI(self::GET_ACTIVITY_INFO, ['student_id' => $studentId]);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('create bill error', [$res, $studentId]);
        }
        return $res;
    }

    /**
     * 周周有礼上传图片信息
     * @param $params
     *
     * @return mixed|null
     * @throws RunTimeException
     */
    public function uploadSharePoster($params)
    {
        $res = self::commonAPI(self::UPLOAD_SHARE_POSTER, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

    /**
     * 获取助教列表
     * @param $params
     * @return array|bool
     */
    public function getAssistantList($params)
    {
        $res = self::commonAPI(self::ASSISTANT_LIST, $params, 'GET');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('role employee data', [$res, $params]);
            return [];
        }
        return !empty($res['data']) ? $res['data'] : [];
    }

    public function getWeixinInfo($params)
    {
        $res = self::commonAPI(self::WEIXIN_INFO, $params, 'GET');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('role employee data', [$res, $params]);
            return [];
        }
        return !empty($res['data']['weixin_info']) ? $res['data']['weixin_info'] : [];
    }
    
    /**
     * 从DSS获取短链
     * @param $url
     * @return array|bool
     * @throws RunTimeException
     */
    public function getShortUrl($url)
    {
        $params = [
            'url' => $url,
        ];
        $res = self::commonAPI(self::GET_SHORT_URL, $params, 'POST');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

    /**
     * 获取用户的状态
     * @param $params
     * @return array|mixed
     */
    public function getStudentIdentity($params)
    {
        $res = self::commonAPI(self::GET_STUDENT_IDENTITY, $params, 'GET');
        if ($res['code'] != Valid::CODE_SUCCESS) {
            SimpleLogger::error('get student identity error', [$res, $params]);
            return [];
        }
        return !empty($res['data']) ? $res['data'] : [];
    }
}
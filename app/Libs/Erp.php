<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:19 PM
 */

namespace App\Libs;

use App\Services\ErpReferralService;
use App\Services\EventService;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class Erp
{
    const SELF_APP_ID = 8;

    const RSP_CODE_SUCCESS = 0;

    //转介绍奖励和返现奖励的关联表不同
    const AWARD_RELATE_REFERRAL = 1;
    const AWARD_RELATE_COMMUNITY = 2;

    const API_CREATE_BILL = '/ai_dss/bill/create_bill';
    const API_BILL_DETAIL = '/ai_dss/bill/detail';
    const API_PACKAGE_LIST = '/ai_dss/package/package_list';
    const API_PACKAGE_DETAIL = '/ai_dss/package/package_detail';
    const API_STUDENT_REGISTER = '/api/dss/student_register';
    const API_REFERRED_LIST = '/api/dss/referred_list';
    const API_AWARD_LIST = '/api/dss/awards';
    const API_UPDATE_TASK = '/api/dss/add_user_event_task';
    const API_UPDATE_AWARD = '/api/dss/award';
    const API_BATCH_UPDATE_AWARD = '/api/dss/batch_award';
    const API_USER_REFERRAL_INFO = '/api/dss/get_user_referral_info';
    const API_EVENT_LIST = '/api/dss/events';
    const API_COPY_TASK = '/api/dss/copy_task';
    const API_MODIFY_TASK = '/api/dss/modify_task';
    const API_AWARD_BASE_INFO = '/api/dss/task_award_info';

    const API_STUDENT_ADDRESS_LIST = '/ai_dss/student/address_list';
    const API_STUDENT_MODIFY_ADDRESS = '/ai_dss/student/modify_address';

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "erp_host");
    }

    private function commonAPI($api,  $data = [], $method = 'GET')
    {
        try {
            $client = new Client([
                'debug' => false
            ]);

            $fullUrl = $this->host . $api;

            if ($method == 'GET') {
                $data = ['query' => $data];
            } elseif ($method == 'POST') {
                $data = ['json' => $data];
            }
            $data['headers'] = ['Content-Type' => 'application/json'];
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $fullUrl, 'data' => $data]);
            $response = $client->request($method, $fullUrl, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'body' => $body, 'status' => $status]);


            $res = json_decode($body, true);
            SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);

            if (($status != StatusCode::HTTP_OK) || !isset($res['code']) || $res['code'] != Valid::CODE_SUCCESS) {
                return false;
            }
            return $res;

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        return false;
    }

    /**
     * 创建订单
     * @param $uuid
     * @param $packageId
     * @param $payChannel
     * @param $clientIp
     * @param $amount
     * @param $oprice
     * @param $callbacks
     * @param $params
     * @return array
     */
    public function createBill($uuid, $packageId, $payChannel, $clientIp, $amount, $oprice, $callbacks, $params = [])
    {
        $data = [
            'type' => 3, // 购买课程
            'num' => 1, // 数量
            'app_id' => 8, // 熊猫陪练
            'user_id' => $uuid, // uuid 到erp会转为对应
            'user_type' => 1, // 订单拥有者类型  1 学员 2 老师
            'object_id' => $packageId, // 对应物品id 如商品包id，商品id，课程单元id
            'object_type' => 1, // 物品类型 1 商品包id 2 商品id  3 课程id
            'fee_type' => 'cny', // 账户类型  usd: 美元, cny: 人民币
            'amount' => $amount, // 实际支付金额，单位是分
            'oprice' => $oprice, // 应付金额，单位是分
            'pay_type' => 1, // 1ping++ 2原生微信支付 3 原生支付宝支付 现阶段都是1
            'pay_channel' => $payChannel, // 1支付宝手机网页支付 2微信H5支付；
            'msg' => "购买产品包({$packageId})", // 描述
            'student_address_id' => $params['student_address_id'] ?? 0, // 地址

            'success_url' => $callbacks['success_url'] ?? null, // 支付宝web 支付成功跳转链接
            'cancel_url' => $callbacks['cancel_url'] ?? null, // 支付宝web 支付失败跳转链接
            'result_url' => $callbacks['result_url'] ?? null, // 微信H5 支付结果跳转链接

            'ip' => $clientIp, // 微信H5支付需要客户端ip
        ];

        //把扩展参数合并进data，注意这里不会覆盖原有的key
        foreach($params as $key => $value) {
            if(!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        $result = self::commonAPI(self::API_CREATE_BILL, $data, 'POST');
        return $result;
    }

    /**
     * 查询商品列表
     *
     * {
     * "code": 0,
     * "data": {
     * "packages": {
     * "total": "1",
     * "data": [{
     * "package_id": "10162",
     * "package_name": "AI陪练APP内购买（7天）",
     * "start_time": "1564329600",
     * "end_time": "1596105351",
     * "oprice": 2500, // 原价
     * "sprice": 2400, // 现价
     * "dprice": 100, // 减价
     * "num": "1464",
     * "duration": "0min"
     * }]
     * }
     * }
     * }
     * @param string uuid
     * @param int $channel
     * @return array
     */
    public function getPackages($uuid, $channel = 1)
    {
        $result = self::commonAPI(self::API_PACKAGE_LIST, ['uuid' => $uuid, 'channel' => $channel], 'GET');

        return $result['data']['packages'] ?? null;
    }

    /**
     * 获取商品包详情
     * @param $uuid
     * @param int $channel
     * @return null
     */
    public function getPackageDetail($packageId, $uuid, $channel = 1)
    {
        $result = self::commonAPI(self::API_PACKAGE_DETAIL, ['package_id' => $packageId, 'uuid' => $uuid, 'channel' => $channel], 'GET');

        return $result['data'] ?? [];
    }

    /**
     * 获取订单详情
     * @param $billId
     * @return array
     */
    public function getBill($billId)
    {
        $result = self::commonAPI(self::API_BILL_DETAIL, ['bill_id' => $billId], 'GET');

        return $result['data']['bill'] ?? null;
    }

    /**
     * 学生注册
     * @param $channelId
     * @param $mobile
     * @param $name
     * @param null $refType
     * @param null $refUuid
     * @return array|bool
     */
    public function studentRegister($channelId, $mobile, $name, $refType = null, $refUuid = null)
    {
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_REGISTER, [
            'app_id' => self::SELF_APP_ID,
            'mobile' => $mobile,
            'name' => $name,
            'channel_id' => $channelId,
            'referrer_type' => $refType,
            'referrer_uuid' => $refUuid,
        ], 'POST');
        return $response;
    }

    /**
     * 转介绍列表
     * @param $params
     * @return array|bool
     */
    public function referredList($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_REFERRED_LIST, $params);
        return $response;
    }

    /**
     * 获取事件任务
     * @param int $eventId
     * @param int $eventType
     * @return array|bool
     */
    public function eventTaskList($eventId = 0, $eventType = ErpReferralService::EVENT_TYPE_UPLOAD_POSTER)
    {
        $params['app_id'] = self::SELF_APP_ID;
        if (!empty($eventId)) {
            $params['event_id'] = $eventId;
        }
        if (!empty($eventType)) {
            $params['type'] = $eventType;
        }
        return ['code' => Valid::CODE_SUCCESS, 'data' => EventService::wholeEvents($params)];
    }

    /**
     * 复制任务
     * @param $taskId
     * @param $startTime
     * @param $endTime
     * @return array|bool
     */
    public function copyTask($taskId, $startTime, $endTime, $name)
    {
        $response = HttpHelper::requestJson($this->host . self::API_COPY_TASK, [
            'app_id' => self::SELF_APP_ID,
            'task_id' => $taskId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'task_name' => $name
        ]);
        return $response;
    }

    /**
     * 修改任务
     * @param $taskId
     * @param $startTime
     * @param $endTime
     * @param $name
     * @param null $status
     * @return array|bool
     */
    public function modifyTask($taskId, $startTime, $endTime, $name, $status = null)
    {
        $data['app_id'] = self::SELF_APP_ID;
        $data['task_id'] = $taskId;

        $data['start_time'] = !empty($startTime) ? $startTime : 0;
        $data['end_time'] = !empty($endTime) ? $endTime : 0;
        $data['name'] = !empty($name) ? $name : '';
        if (isset($status) && is_numeric($status)) {
            $data['status'] = $status;
        }

        $response = HttpHelper::requestJson($this->host . self::API_MODIFY_TASK, $data);
        return $response;
    }

    /**
     * 转介绍奖励列表
     * @param $params
     * @return array|bool
     */
    public function awardList($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_AWARD_LIST, $params);
        return $response;
    }

    /**
     * 活动任务
     * @param $uuid
     * @param $eventTaskId
     * @param $status
     * @return array|bool
     */
    public function updateTask($uuid, $eventTaskId, $status)
    {
        $params = [
            'app_id' => self::SELF_APP_ID,
            'user_type' => 1,
            'uuid' => $uuid,
            'event_task_id' => $eventTaskId,
            'status' => $status
        ];
        $response = HttpHelper::requestJson($this->host . self::API_UPDATE_TASK, $params, 'POST');
        return $response;
    }

    /**
     * 更新奖励状态
     * @param $awardId
     * @param $status
     * @param $reviewerId
     * @param string $reason
     * @return array|bool
     */
    public function updateAward($awardId, $status, $reviewerId, $reason = '')
    {
        $params['app_id'] = self::SELF_APP_ID;
        $params = [
            'app_id' => self::SELF_APP_ID,
            'user_event_task_award_id' => $awardId,
            'status' => $status,
            'reviewer_id' => $reviewerId,
            'review_time' => time(),
            'reason' => $reason,
        ];
        $response = HttpHelper::requestJson($this->host . self::API_UPDATE_AWARD, $params, 'POST');
        return $response;
    }

    /**
     * @param $awardInfo
     * @return array|bool
     * 批量更新状态
     */
    public function batchUpdateAward($awardInfo)
    {
        array_walk($awardInfo, function (&$value) {
            $value['app_id'] = self::SELF_APP_ID;
        });
        $response = HttpHelper::requestJson($this->host . self::API_BATCH_UPDATE_AWARD, ['award_info' => $awardInfo], 'POST');
        return $response;
    }

    /**
     * @param $awardIdStr
     * @return array|bool
     * 当前奖励的基础信息
     */
    public function getUserAwardInfo($awardIdStr)
    {
        $params = [
            'app_id' => self::SELF_APP_ID,
            'award_info_id_str' => $awardIdStr
        ];
        return HttpHelper::requestJson($this->host . self::API_AWARD_BASE_INFO, $params, 'GET');
    }


    /**
     * 转介绍列表
     * @param $params
     * @return array|bool
     */
    public function userReferralInfo($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_USER_REFERRAL_INFO, $params);
        return $response;
    }

    /**
     * 学生地址列表
     * @param $params
     * @return array|bool
     */
    public function getStudentAddressList($uuid)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $params['uuid'] = $uuid;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_ADDRESS_LIST, $params);
        return $response;
    }

    /**
     * 修改学生地址
     * @param $params
     * @return array|bool
     */
    public function modifyStudentAddress($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_MODIFY_ADDRESS, $params, 'POST');
        return $response;
    }
}
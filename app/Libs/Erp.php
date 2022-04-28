<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:19 PM
 */

namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;
use App\Models\CountingActivityAwardModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Erp\ErpDictModel;
use App\Models\Erp\ErpEventModel;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class Erp
{
    const RSP_CODE_SUCCESS = 0;

    //转介绍奖励和返现奖励的关联表不同
    const AWARD_RELATE_REFERRAL = 1;
    const AWARD_RELATE_COMMUNITY = 2;
    const AWARD_RELATE_REFEREE = 3;

    const REQUEST_ERP_OP_SOURCE = 19;       // 请求erp 时的source  19代表op
    const USER_IS_PAY_YES = 1;              // 请求erp获取用户付费属性时， 1是已付费

    const SOURCE_TYPE_OP_TO_MONEY = 7001; //OP系统扣减兑换现金
    const API_CREATE_BILL = '/ai_dss/bill/create_bill';
    const API_BILL_DETAIL = '/ai_dss/bill/detail';
    const API_PACKAGE_LIST = '/ai_dss/package/package_list';
    const API_PACKAGE_DETAIL = '/ai_dss/package/package_detail';
    const API_STUDENT_REGISTER = '/api/dss/student_register';
    const API_REFERRED_LIST = '/api/dss/referred_list';
    const API_AWARD_LIST = '/api/dss/awards';
    const API_UPDATE_TASK = '/api/dss/add_user_event_task';
    const API_ADD_EVENT_TASK_AWARD = '/api/operation/add_event_task_award'; // 添加（完成）任务 （A推荐B，B是作任务的人，返回任务id）
    const API_UPDATE_AWARD = '/api/dss/award';
    const API_BATCH_UPDATE_AWARD = '/api/dss/batch_award';
    const API_USER_REFERRAL_INFO = '/api/dss/get_user_referral_info';
    const API_EVENT_LIST = '/api/dss/events';
    const API_COPY_TASK = '/api/dss/copy_task';
    const API_MODIFY_TASK = '/api/dss/modify_task';
    const API_AWARD_BASE_INFO = '/api/dss/task_award_info';
    const API_USER_GET_RED_PACKET_CALL = '/api/app/user_get_red_packet_call_back';  //积分兑换红包 - 红包被用户成功领取通知erp接口地址
    const API_REFUND_FINISH_TIME = '/ai_dss/billV1/refund_finish_time';  //获取订单退单时间erp接口地址
    const API_LOGISTICS_GOODS_LIST = '/api/logistics/goods_list';  //获取实物商品列表

    // 地址管理
    const API_STUDENT_ADDRESS_LIST = '/ai_dss/student/address_list';
    const API_STUDENT_MODIFY_ADDRESS = '/ai_dss/student/modify_address';
    const API_STUDENT_DELETE_ADDRESS = '/ai_dss/student/delete_address';
    // 新产品包
    const API_PACKAGE_V1_LIST = '/ai_dss/package/package_v1_list';
    const API_PACKAGE_V1_DETAIL = '/ai_dss/package/package_v1_detail';
    // 新订单中心
    const API_CREATE_BILL_V1 = '/ai_dss/billV1/create_bill';
    const API_BILL_LIST_V1 = '/ai_dss/billV1/bill_list';
    const API_BILL_DETAIL_V1 = '/ai_dss/billV1/bill_detail';
    const API_LOGISTICS_V1 = '/ai_dss/billV1/logistics';
    const API_BILL_STATUS_V1 = '/ai_dss/billV1/bill_status';
    const API_CREATE_ZERO_BILL = '/ai_dss/billV1/create_bill_zero';
    // 创建订单（可以创建后立即发货）
    const API_MAN_CREATE_BILL_V1 = '/ai_dss/billV1/man_create';
    const API_REFUND_FREE_BILL = '/ai_dss/billV1/abandon_free';
    const API_CHECK_PACKAGE_HAVE_KIND_V1 = '/ai_dss/billV1/check_package_have_kind';
    const API_UPDATE_ORDER_ADDRESS_V1 = '/ai_dss/billV1/update_order_address';

    // 账户
    const API_STUDENT_ACCOUNTS = '/ai_dss/account/detail';
    const API_STUDENT_ACCOUNT_DETAIL = '/ai_dss/account/logs';

    //用户累计获取的音符
    const API_ADD_UP_CREDIT = '/api/dss/get_user_credit';

    //用户对于特定任务的完成状态
    const API_USER_RELATE_TASK = '/api/dss/get_user_relate_task';
    //特定任务的完成用户情况
    const API_TASK_COMPLETE_INFO = '/api/dss/get_task_complete';
    // 批量发放用户奖励积分
    const API_BATCH_AWARD_POINTS = '/api/operation/award_points';
    // 优惠券发放明细列表
    const API_STUDENT_COUPON_PAGE = '/api/student_coupon/page';

    //获取快递详情
    const API_EXPRESS_DETAILS = '/api/logistics/detail';
    //发货
    const API_DELIVER_GOODS = '/api/logistics/create';
    const API_EVENT_EDIT = '/api/dss/event/edit'; //创建event
    //通过唯一标示批量查询物流单号以及物流状态
    const API_LOGISTICS_STATUS_DATA = '/api/logistics/list';
    //发放优惠券
    const API_GRANT_COUPON = '/api/student_coupon/distribution';
    //发放金叶子
    const API_GRANT_GOLD_LEAF = '/api/consumer/%sstudent_account_callback_referral';
    //用户余额扣减
    const API_REDUCE_ACCOUNT = '/api/student_account/deduct';
    //查询账户余额
    const API_STUDENT_ACCOUNT_BALANXE = '/api/student_account/balance';
    //真人转介绍学生注册
    const REFEREE_STUDENT_REGISTER = '/op/user/register_bound';
    //神策埋点学生基础数据
    const SENSORS_STUDENT_BASE_DATA = '/api/app/sensors/profile/student';
    //获取学生身份属性
    const STUDENT_IDENTIFY_ATTRIBUTE = '/api/student/attribute';
    //抖店实物商品消费发货/退费
    const DOU_STORE_MSG = '/api/consumer/dou_store_msg';

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "erp_host");
    }

    private function commonAPI($api, $data = [], $method = 'GET', &$exportBody = '')
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

            $exportBody = $body;

            $res = json_decode($body, true);
            SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);

            if (($status != StatusCode::HTTP_OK) || !isset($res['code']) || $res['code'] != Valid::CODE_SUCCESS) {
                return false;
            }
            return $res;

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
            SentryClient::captureException($e, ['error' => $e->getMessage()]);
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
            'type'               => 3, // 购买课程
            'num'                => 1, // 数量
            'app_id'             => 8, // 熊猫陪练
            'user_id'            => $uuid, // uuid 到erp会转为对应
            'user_type'          => 1, // 订单拥有者类型  1 学员 2 老师
            'object_id'          => $packageId, // 对应物品id 如商品包id，商品id，课程单元id
            'object_type'        => 1, // 物品类型 1 商品包id 2 商品id  3 课程id
            'fee_type'           => 'cny', // 账户类型  usd: 美元, cny: 人民币
            'amount'             => $amount, // 实际支付金额，单位是分
            'oprice'             => $oprice, // 应付金额，单位是分
            'pay_type'           => 1, // 1ping++ 2原生微信支付 3 原生支付宝支付 现阶段都是1
            'pay_channel'        => $payChannel, // 1支付宝手机网页支付 2微信H5支付；
            'msg'                => "购买产品包({$packageId})", // 描述
            'student_address_id' => $params['student_address_id'] ?? 0, // 地址

            'success_url' => $callbacks['success_url'] ?? null, // 支付宝web 支付成功跳转链接
            'cancel_url'  => $callbacks['cancel_url'] ?? null, // 支付宝web 支付失败跳转链接
            'result_url'  => $callbacks['result_url'] ?? null, // 微信H5 支付结果跳转链接

            'ip'            => $clientIp, // 微信H5支付需要客户端ip
            'employee_uuid' => $params['employee_uuid'] ?? null //成单人
        ];

        //把扩展参数合并进data，注意这里不会覆盖原有的key
        foreach ($params as $key => $value) {
            if (!isset($data[$key])) {
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
     * @param $packageId
     * @param $uuid
     * @param int $channel
     * @return null
     */
    public function getPackageDetail($packageId, $uuid, $channel = 1)
    {
        $result = self::commonAPI(self::API_PACKAGE_DETAIL,
            ['package_id' => $packageId, 'uuid' => $uuid, 'channel' => $channel], 'GET');

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
     * @param null $countryCode
     * @return array|bool
     */
    public function studentRegister($channelId, $mobile, $name, $refType = null, $refUuid = null, $countryCode = null)
    {
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_REGISTER, [
            'app_id'        => Constants::SELF_APP_ID,
            'mobile'        => $mobile,
            'country_code'  => $countryCode,
            'name'          => $name,
            'channel_id'    => $channelId,
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
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_REFERRED_LIST, $params);
        return $response;
    }


    /**
     * 复制任务
     * @param $taskId
     * @param $startTime
     * @param $endTime
     * @param $name
     * @return array|bool
     */
    public function copyTask($taskId, $startTime, $endTime, $name)
    {
        $response = HttpHelper::requestJson($this->host . self::API_COPY_TASK, [
            'app_id'     => Constants::SELF_APP_ID,
            'task_id'    => $taskId,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'task_name'  => $name
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
        $data['app_id'] = Constants::SELF_APP_ID;
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
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_AWARD_LIST, $params);
        return $response;
    }

    /**
     * @param $uuid
     * @param $eventTaskId
     * @param $status
     * @return mixed
     * @throws RunTimeException
     * 活动任务
     */
    public function updateTask($uuid, $eventTaskId, $status)
    {
        $params = [
            'app_id'        => Constants::SELF_APP_ID,
            'user_type'     => 1,
            'uuid'          => $uuid,
            'event_task_id' => $eventTaskId,
            'status'        => $status
        ];
        $response = HttpHelper::requestJson($this->host . self::API_UPDATE_TASK, $params, 'POST');
        if ($response['code'] == Valid::CODE_PARAMS_ERROR) {
            SimpleLogger::info('erp-> update task error', ['uuid' => $uuid, 'event_task_id' => $eventTaskId]);
            throw new RunTimeException(['request_error']);
        }
        return $response['data'];
    }

    /**
     * @param $awardId
     * @param $status
     * @param $reviewerId
     * @param string $reason
     * @return mixed
     * @throws RunTimeException
     * 更新奖励状态
     */
    public function updateAward($awardId, $status, $reviewerId, $reason = '')
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $params = [
            'app_id'                   => Constants::SELF_APP_ID,
            'user_event_task_award_id' => $awardId,
            'status'                   => $status,
            'reviewer_id'              => $reviewerId,
            'review_time'              => time(),
            'reason'                   => $reason,
        ];
        $response = HttpHelper::requestJson($this->host . self::API_UPDATE_AWARD, $params, 'POST');
        if ($response['code'] == Valid::CODE_PARAMS_ERROR) {
            SimpleLogger::info('erp-> update award error', ['award_if' => $awardId, 'status' => $status]);
            throw new RunTimeException(['request_error']);
        }
        return $response['data'];
    }

    /**
     * @param $awardInfo
     * @return array|bool
     * 批量更新状态
     */
    public function batchUpdateAward($awardInfo)
    {
        array_walk($awardInfo, function (&$value) {
            $value['app_id'] = Constants::SELF_APP_ID;
        });
        $response = HttpHelper::requestJson($this->host . self::API_BATCH_UPDATE_AWARD, ['award_info' => $awardInfo],
            'POST');
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
            'app_id'            => Constants::SELF_APP_ID,
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
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_USER_REFERRAL_INFO, $params);
        return $response;
    }

    /**
     * 学生地址列表
     * @param $uuid
     * @return array|bool
     */
    public function getStudentAddressList($uuid)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
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
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_MODIFY_ADDRESS, $params, 'POST');
        return $response;
    }

    /**
     * 删除地址
     * @param $params
     * @return array|bool
     */
    public function deleteStudentAddress($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_DELETE_ADDRESS, $params, 'POST');
        return $response;
    }

    /**
     * 学生账户余额
     * @param $studentUuid
     * @return array|bool
     */
    public function studentAccount($studentUuid)
    {
        $params['student_uuid'] = $studentUuid;
        $params['app_id'] = Constants::SMART_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_ACCOUNTS, $params);
        return $response;
    }

    /**
     * 学生账户明细
     * @param $studentUuid
     * @param $subType
     * @param $page
     * @param $count
     * @param $createTime
     * @return array|bool
     */
    public function studentAccountDetail($studentUuid, $subType, $page, $count, $createTime = 0)
    {
        $params['student_uuid'] = $studentUuid;
        $params['sub_type'] = $subType;
        $params['app_id'] = Constants::SELF_APP_ID;
        $params['page'] = $page;
        $params['count'] = $count;
        $params['create_time'] = $createTime;
        $response = HttpHelper::requestJson($this->host . self::API_STUDENT_ACCOUNT_DETAIL, $params);
        return $response;
    }

    /**
     * 新产品包列表
     * @param $params
     * @return array|bool
     */
    public function packageV1List($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_PACKAGE_V1_LIST, $params);
        return $response;
    }

    /**
     * 新产品包详情
     * @param $params
     * @return array|bool
     */
    public function packageV1Detail($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;

        $response = HttpHelper::requestJson($this->host . self::API_PACKAGE_V1_DETAIL, $params);
        return $response;
    }


    /**
     * 创建发货订单
     * @param $params
     * @return array
     */
    public function manCreateDeliverBillV1($params)
    {
        $body = '';
        $result = self::commonAPI(self::API_MAN_CREATE_BILL_V1, $params, 'POST', $body);
        return [$result, $body];
    }

    /**
     * 订单列表
     * @param $params
     * @return array|bool
     */
    public function billListV1($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;

        $response = HttpHelper::requestJson($this->host . self::API_BILL_LIST_V1, $params);
        return $response;
    }

    /**
     * 订单详情
     * @param $params
     * @return array|bool
     */
    public function billDetailV1($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;

        $response = HttpHelper::requestJson($this->host . self::API_BILL_DETAIL_V1, $params);
        return $response;
    }

    /**
     * 新订单状态
     * @param $params
     * @return array|bool
     */
    public function billStatusV1($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;

        $response = HttpHelper::requestJson($this->host . self::API_BILL_STATUS_V1, $params);
        return $response;
    }

    /**
     * 物流信息
     * @param $params
     * @return array|bool
     */
    public function logisticsV1($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;

        $response = HttpHelper::requestJson($this->host . self::API_LOGISTICS_V1, $params);
        return $response;
    }

    /**
     * @param $params
     * @return array|bool
     * 累计音符
     */
    public function getUserAddUpCredit($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_ADD_UP_CREDIT, $params);
        return $response;
    }

    /**
     * @param $params
     * @return array|bool
     * 用户对于特定的任务的完成情况
     */
    public function getUserTaskRelateInfo($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_USER_RELATE_TASK, $params);
        return $response;
    }

    /**
     * @param $params
     * @return array|bool
     * 特定任务的用户完成情况
     */
    public function getTaskCompleteUser($params)
    {
        $params['app_id'] = Constants::SELF_APP_ID;
        $response = HttpHelper::requestJson($this->host . self::API_TASK_COMPLETE_INFO, $params);
        return $response;
    }

    /**
     * 退赠单
     * @param $params
     * @return bool|mixed
     */
    public function abandonFreeBill($params)
    {
        $result = self::commonAPI(self::API_REFUND_FREE_BILL, $params, 'POST');
        return $result;
    }

    /**
     * 批量发放用户奖励积分
     * @param $params
     * @return array
     */
    public function batchAwardPoints($params)
    {
        $result = self::commonAPI(self::API_BATCH_AWARD_POINTS, $params, 'POST');
        return $result ?? null;
    }

    /**
     * 创建订单
     * @param $params
     * @return array|bool
     */
    public function createBillV1($params)
    {
        $params['sale_shop'] = $params['sale_shop'] ?? DssErpPackageV1Model::SALE_SHOP_NOTE;
        return HttpHelper::requestJson($this->host . self::API_CREATE_BILL_V1, $params, 'POST');
    }

    /**
     * 创建0元体验订单
     * @param $params
     * @return array|bool
     */
    public function createZeroBillV1($params)
    {
        return HttpHelper::requestJson($this->host . self::API_CREATE_ZERO_BILL, $params, 'POST');
    }

    /**
     * 订单中的产品或赠品是否包含实物
     * @param $params
     * @return array|bool
     */
    public function checkPackageHaveKind($params)
    {
        $params['app_id'] = $params['app_id'] ?? Constants::SMART_APP_ID;
        return HttpHelper::requestJson($this->host . self::API_CHECK_PACKAGE_HAVE_KIND_V1, $params);
    }

    /**
     * 更新订单发货地址
     * @param $params
     * @return array|bool
     */
    public function updateOrderAddress($params)
    {
        $params['app_id'] = $params['app_id'] ?? Constants::SMART_APP_ID;
        return HttpHelper::requestJson($this->host . self::API_UPDATE_ORDER_ADDRESS_V1, $params);
    }

    /**
     * 添加任务奖励
     * @param $uuid
     * @param $eventTaskId
     * @param $status
     * @param $refereeUid
     * @param $awardId
     * @param $extParams
     * @return array|bool
     */
    public function addEventTaskAward($uuid, $eventTaskId, $status, $awardId = 0, $refereeUid = '', $extParams = [])
    {
        $params = [
            'app_id'                    => Constants::SMART_APP_ID,
            'user_type'                 => 1,
            'uuid'                      => $uuid,
            'referee_uuid'              => $refereeUid,
            'event_task_id'             => $eventTaskId,
            'status'                    => $status,
            'reason'                    => $extParams['reason'] ?? '',
            'bill_id'                   => $extParams['bill_id'] ?? '',
            'package_type'              => $extParams['package_type'] ?? 0,
            'task_award_id'             => $awardId,
            'invite_detail_id'          => $extParams['invite_detail_id'] ?? 0,
            'activity_id'               => $extParams['activity_id'] ?? 0,
            'delay'                     => $extParams['delay'] ?? 0,
            'amount'                    => $extParams['amount'] ?? 0,
            'award_to'                  => $extParams['award_to'] ?? 0,
            'passes_num'                => $extParams['passes_num'] ?? 0,
            'old_rule_last_activity_id' => $extParams['old_rule_last_activity_id'] ?? 0,
            'remark'                    => $extParams['remark'] ?? '',
        ];
        return HttpHelper::requestJson($this->host . self::API_ADD_EVENT_TASK_AWARD, $params, 'POST');
    }

    /**
     * 通知erp用户成功领取红包成功
     * @param string $uuid
     * @param int $orderId 订单Id
     * @param int $recordSn 订单中的红包对应的唯一标识 (一个订单可能有多个红包)
     * @param int $status 领取状态
     * @return array|bool
     */
    public function noticeErpUserGetRedPackStatus($uuid, $orderId, $recordSn = 0, $status = 0)
    {
        $params = [
            'app_id'    => Constants::SMART_APP_ID,
            'user_type' => 1,
            'uuid'      => $uuid,
            'award_id'  => $recordSn,
            'order_id'  => $orderId,
            'status'    => $status,
        ];
        $response = HttpHelper::requestJson($this->host . self::API_USER_GET_RED_PACKET_CALL, $params, 'POST');
        return $response;
    }

    /**
     * 获取事件任务
     * @param int $eventId
     * @param int $eventType
     * @return array|bool
     */
    public function eventTaskList($eventId = 0, $eventType = ErpEventModel::DAILY_UPLOAD_POSTER)
    {
        $params['app_id'] = Constants::SMART_APP_ID;
        if (!empty($eventId)) {
            $params['event_id'] = $eventId;
        }
        if (!empty($eventType)) {
            $params['type'] = $eventType;
        }
        return ['code' => Valid::CODE_SUCCESS, 'data' => ErpEventModel::wholeEvents($params)];
    }

    /**
     * 获取订单退单时间
     * @param array $arrBillId
     * @return array|bool
     */
    public function getRefundTime($arrBillId = [])
    {
        if (empty($arrBillId)) {
            return [];
        }
        $strBillId = implode(',', $arrBillId);
        $params = [
            'order_id' => $strBillId,
        ];
        return HttpHelper::requestJson($this->host . self::API_REFUND_FINISH_TIME, $params, 'POST');
    }

    /**
     * 获取学生优惠券信息
     * @param array $id
     * @return array|bool
     */
    public function getStudentCouponPageById($id = [])
    {
        if (empty($id)) {
            return [];
        }
        $strId = implode(',', $id);
        $params = [
            'student_coupon_id' => $strId,
        ];
        return HttpHelper::requestJson($this->host . self::API_STUDENT_COUPON_PAGE, $params, 'GET');
    }

    /**
     * 获取商品列表
     * @param $params
     * @return array|bool
     */
    public function getLogisticsGoodsList($params)
    {
        return HttpHelper::requestJson($this->host . self::API_LOGISTICS_GOODS_LIST, $params);
    }

    /**
     * 获取快递详情
     * @param string $uniqueId
     * @param bool $readCache
     * @return array
     */
    public function getExpressDetails(string $uniqueId, bool $readCache = true): array
    {
        $redis = RedisDB::getConn();

        $cache = [];
        $cacheKey = sprintf(CountingActivityAwardModel::REDIS_EXPRESS_KEY, $uniqueId);
        if ($readCache) {
            $cache = $redis->get($cacheKey);
        }
        if (!empty($cache)) {
            return json_decode($cache, true);
        }

        $result = HttpHelper::requestJson($this->host . self::API_EXPRESS_DETAILS, ['order_id' => $uniqueId]);

        $data = [];
        if ($result['code'] == 0 && !empty($result['data'])) {
            $data = $result['data'];
            $redis->set($cacheKey, json_encode($data), 'EX', Util::TIMESTAMP_1H);
        }
        return $data;
    }

    /**
     * 发货
     * @param array $params
     * @return array|bool
     */
    public function deliverGoods(array $params)
    {
        return HttpHelper::requestJson($this->host . self::API_DELIVER_GOODS, $params, 'POST');
    }


    /**
     * 领取优惠券
     * @param $params
     * @return array|bool
     */
    public function grantCoupon($params)
    {
        $result = HttpHelper::requestJson($this->host . self::API_GRANT_COUPON, $params, 'POST');
        return $result['data'] ?? [];
    }

    /**
     * 运营活动添加事件和奖励任务
     * @param $params
     * @return array|bool
     */
    public function addEventAndEventTask($params)
    {
        $params['app_id'] = Constants::SMART_APP_ID;
        return HttpHelper::requestJson($this->host . self::API_EVENT_EDIT, $params, 'POST');
    }

    /**
     * 通过唯一标示批量查询物流单号以及物流状态
     * @param $params
     * @param $timeOut
     * @return array
     */
    public function getLogisticsStatusData($params, $timeOut)
    {
        $data = HttpHelper::requestJson($this->host . self::API_LOGISTICS_STATUS_DATA, $params, 'GET',
            ['timeout' => $timeOut]);
        if ($data === false) {
            return [];
        }
        return empty($data['data']) ? [] : $data['data'];
    }

    /**
     * 发放金叶子
     * @param $params
     * @return bool
     */
    public function grantGoldLeaf(array $params): bool
    {
        $url = sprintf(self::API_GRANT_GOLD_LEAF,
            ErpDictModel::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DICT_KEY_NSQ_TOPIC_PREFIX));
        $result = HttpHelper::requestJson($this->host . $url, $params, 'POST');
        if ($result['code'] == 0) {
            return true;
        }
        return false;
    }


    /**
     * 扣减金叶子
     * @param $params
     * @return bool
     */
    public function reduce_account($params)
    {
        $res = self::commonAPI(self::API_REDUCE_ACCOUNT, $params, 'POST');
        if ($res && $res['code'] == Valid::CODE_SUCCESS) {
            return true;
        }
        return false;
    }

    /**
     * 获取用户账户信息
     * @param $params
     * @return array|mixed
     */
    public function getStudentAccount($params)
    {
        $data = self::commonAPI(self::API_STUDENT_ACCOUNT_BALANXE, $params);
        if ($data === false) {
            return [];
        }
        return empty($data['data']) ? [] : $data['data'];
    }

    /**
     * 真人转介绍学生注册
     * @param $params
     * @return array|bool
     * @throws RunTimeException
     */
    public function refereeStudentRegister($params)
    {
        $response = HttpHelper::requestJson($this->host . self::REFEREE_STUDENT_REGISTER, $params, 'POST');
        if ($response['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException(['user_register_fail']);
        }
        return $response['data'];
    }

    /**
     * 获取学生状态
     * @param $params
     * @return array|mixed
     */
    public function getStudentStatus($params)
    {
        $response = HttpHelper::requestJson($this->host . self::SENSORS_STUDENT_BASE_DATA, $params, 'POST');
        return $response['data'] ?? [];
    }

    /**
     * 获取学生身份属性
     * @param $studentUuid
     * @return array|mixed
     */
    public function getStudentIdentityAttribute($studentUuid)
    {
        if (empty($studentUuid)) {
            return [];
        }
        $response = HttpHelper::requestJson($this->host . self::STUDENT_IDENTIFY_ATTRIBUTE, [
            'student_uuid' => $studentUuid,
            'source'       => self::REQUEST_ERP_OP_SOURCE
        ], 'GET');
        return $response['data'] ?? [];
    }

    /**
     * 消费发货/退费
     * @param $params
     * @return array|mixed
     */
    public function douStoreMsg($params)
    {
        $response = HttpHelper::requestJson($this->host . self::DOU_STORE_MSG, $params, 'POST');
        return $response['data'] ?? [];
    }
}

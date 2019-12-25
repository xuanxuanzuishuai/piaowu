<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:19 PM
 */

namespace App\Libs;

use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class Erp
{
    const RSP_CODE_SUCCESS = 0;

    const API_CREATE_BILL = '/ai_dss/bill/create_bill';
    const API_BILL_DETAIL = '/ai_dss/bill/detail';
    const API_PACKAGE_LIST = '/ai_dss/package/package_list';

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
            'student_address_id' => null, // 地址

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
    {
        "code": 0,
        "data": {
            "packages": {
                "total": "1",
                "data": [{
                    "package_id": "10162",
                    "package_name": "AI陪练APP内购买（7天）",
                    "start_time": "1564329600",
                    "end_time": "1596105351",
                    "oprice": 2500, // 原价
                    "sprice": 2400, // 现价
                    "dprice": 100, // 减价
                    "num": "1464",
                    "duration": "0min"
                }]
            }
        }
    }
     * @param string uuid
     * @return array
     */
    public function getPackages($uuid)
    {
        $result = self::commonAPI(self::API_PACKAGE_LIST, ['uuid' => $uuid], 'GET');

        return $result['data']['packages'] ?? null;
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
}
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
     * @param $data
     * @return array
     */
    public function createBill($data)
    {
        $result = self::commonAPI(self::API_CREATE_BILL, $data, 'POST');
        return $result;
    }

    /**
     * 查询商品列表
     * @return array
     */
    public function packageList()
    {
        $result = self::commonAPI(self::API_PACKAGE_LIST, [], 'GET');
        return $result;
    }
}
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

class QingChen
{
    //根据手机号（批量）检查是否购买过体验课
    const API_qc_ddd = '/api/logistics/list';
    //注册用户并购买体验课
    const API_QC_REGISTER_ORDER = '/api/student/create_order';

    private $host;

    public function __construct()
    {
        $this->host = $_ENV['QC_HOST'];
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
     * 创建学生&订单
     * @param $params
     * @return array|mixed
     */
    public function registerAndOrder($params)
    {
        $requestParams = [
            'country_code' => $params['country_code'],
            'mobile'       => $params['mobile'],
            'channel_id'   => $params['channel_id'],
            'package_id'   => $params['package_id'],
            'trade_no'     => $params['trade_no'],
            'amount'       => $params['dss_amount'],
        ];
        return self::commonAPI(self::API_QC_REGISTER_ORDER, $requestParams, 'POST');
    }
}

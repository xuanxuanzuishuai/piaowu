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

class AdTrack
{
    //记录清晨抖店付费渠道
    const API_AD_QC_DOU_PAY_CHANNEL = '/interior/qingchen/dou_pay_channel';

    private $host;

    public function __construct()
    {
        $this->host = $_ENV['AD_PRI_HOST'];
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

            if (($status != StatusCode::HTTP_OK) || !isset($res['code'])) {
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
     * 记录清晨抖店付费渠道
     * @param $params
     * @return false|mixed
     */
    public function recordQcPayChannelForDou($params)
    {
        $res = self::commonAPI(self::API_AD_QC_DOU_PAY_CHANNEL, $params, 'POST');
        return $res['data'] ?? [];
    }
}

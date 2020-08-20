<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: yangyijie
 * Date: 2019/07/12
 * Time: 下午8:02
 */

namespace App\Libs;

use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class PandaCRM
{
    const RSP_CODE_SUCCESS = 0;

    const API_CHECK_LEADS = '/api/dss/lead_check';
    const API_SYNC_STUDENT_PLAY_DAYS = '/api/dss/sync_student_play_days';

    private $host;

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::SERVICE, "panda_crm_host");
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
     * 检查手机号是否进入熊猫leads库
     * @param $mobile
     * @return int
     */
    public function leadsCheck($mobile)
    {
        $result = self::commonAPI(self::API_CHECK_LEADS, [
            'mobile' => $mobile,
        ], 'POST');

        return empty($result) ? 0 : $result['data'];
    }

    /**
     * 同步学生体验期练琴天数数据
     * @param $data
     * @return mixed
     */
    public function syncStudentsPlayData($data)
    {
        $result = self::commonAPI(self::API_SYNC_STUDENT_PLAY_DAYS, [
            'data' => $data,
        ], 'POST');

        return $result;
    }
}
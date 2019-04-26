<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/26
 * Time: 17:49
 */

namespace App\Libs;


use App\Models\AppConfigModel;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class AIPLCenter
{
    const USER_AUDIO_URL = "/api/1.0/eval/user_audio/";

    public static function commonAPI($api,  $data = [], $method = 'GET')
    {
        try {
            $client = new Client([
                'debug' => false
            ]);

            $erpUrl = AppConfigModel::get(AppConfigModel::AIPL_URL_KEY);
            $fullUrl = $erpUrl . $api;

            if ($method == 'GET') {
                $data = ['query' => $data];
            } elseif ($method == 'POST') {
                $data = ['json' => $data];
            }
            $data['headers'] = [
                'Content-Type' => 'application/json',
                'SERVICEID' => 3,
                'TOKEN' => "MAGISTER_USER_1"
            ];
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $fullUrl, 'data' => $data]);
            $response = $client->request($method, $fullUrl, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'body' => $body, 'status' => $status]);

            if (StatusCode::HTTP_OK == $status) {
                $res = json_decode($body, true);
                if (!empty($res["meta"]["code"]) && $res["meta"]["code"] !== Valid::CODE_SUCCESS) {
                    SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);
                    return $res;
                }
                return $res;
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
                $res = json_decode($body, true);
                return $res;
            }

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        return false;
    }

    /**
     * @param $ai_record_id
     * @return array|bool|mixed
     */
    public static function userAudio($ai_record_id)
    {
        $result = self::commonAPI(self::USER_AUDIO_URL . $ai_record_id);

        return empty($result) ? [] : $result;
    }

}
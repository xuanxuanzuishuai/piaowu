<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/20
 * Time: 4:34 PM
 */

namespace App\Libs;

use GuzzleHttp\Client;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class HttpHelper
{
    const STATUS_SUCCESS = 0;
    const STATUS_ERROR = 1;

    public static function buildResponse(Response $response, $data)
    {
        $result = [
            'code' => self::STATUS_SUCCESS,
            'data' => $data ?? [],
        ];

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    public static function buildErrorResponse(Response $response, $errors)
    {
        $result = [
            'code' => self::STATUS_ERROR,
            'data' => [],
            'errors' => $errors,
        ];
        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * @param $api
     * @param array $params
     * @param string $method
     * @return bool|array
     */
    public static function requestJson($api,  $params = [], $method = 'GET')
    {
        try {
            $client = new Client(['debug' => false]);

            if ($method == 'GET') {
                $data = empty($params) ? [] : ['query' => $params];
            } elseif ($method == 'POST') {
                $data = ['json' => $params];
                $data['headers'] = ['Content-Type' => 'application/json'];
            } else {
                return false;
            }

            SimpleLogger::info("[HttpHelper] send request", ['api' => $api, 'data' => $data]);
            $response = $client->request($method, $api, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info("[HttpHelper] send request ok", ['body' => $body, 'status' => $status]);

            if (($status != StatusCode::HTTP_OK)) {
                return false;
            }

            $res = json_decode($body, true);

        } catch (\Exception $e) {
            SimpleLogger::error("[HttpHelper] send request error", [
                'error_message' => $e->getMessage()
            ]);
            return false;
        }

        return $res;
    }
}
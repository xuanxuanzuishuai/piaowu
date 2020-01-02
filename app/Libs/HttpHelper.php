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

    /**
     * 正常请求结果
     * @param Response $response
     * @param $data
     * @return Response
     */
    public static function buildResponse(Response $response, $data)
    {
        $result = [
            'code' => self::STATUS_SUCCESS,
            'data' => $data ?? [],
        ];

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 错误请求结果
     * @param Response $response
     * @param $errors
     * @return Response
     */
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
     * 错误请求结果(后台)
     * @param Response $response
     * @param $errors
     * @return Response
     */
    public static function buildOrgWebErrorResponse(Response $response, $errors)
    {
        $result = [
            'code' => self::STATUS_ERROR,
            'data' => ['errors' => $errors],
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

    /**
     * @param Response $response
     * @param $data
     * @return Response
     */
    public static function buildClassroomResponse(Response $response, $data)
    {
        $result = [
            'request_result' => [
                'code' => self::STATUS_SUCCESS,
                'message' => 'OK',
                'display_message' => '',
                'request_time' => time(),
            ],
            'data' => $data ?? [],
        ];

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * @param Response $response
     * @param $errors
     * @return Response
     */
    public static function buildClassroomErrorResponse(Response $response, $errors)
    {
        $error = $errors[0] ?? ['err_no' => 'sys_unknown_errors', 'err_msg' => '未知错误'];

        $result = [
            'request_result' => [
                'code' => self::STATUS_ERROR,
                'message' => $error['err_no'],
                'display_message' => $error['err_msg'],
                'request_time' => time(),
            ],
            'data' => [],
        ];
        return $response->withJson($result, StatusCode::HTTP_OK);
    }
}
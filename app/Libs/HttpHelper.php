<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/20
 * Time: 4:34 PM
 */

namespace App\Libs;

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
}
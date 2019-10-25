<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/25
 * Time: 下午8:12
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\BAIDUService;
use Slim\Http\Request;
use Slim\Http\Response;

class BAIDU extends ControllerBase
{
    public function audioToken(Request $request, Response $response)
    {
        try {
            $token = BAIDUService::obtainBAIDUToken();
        }catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['token' => $token]);
    }
}
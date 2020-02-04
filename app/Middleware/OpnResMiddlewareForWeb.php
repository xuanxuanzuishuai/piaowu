<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\AIBackendService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OpnResMiddlewareForWeb extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token = $this->container['token'] ?? NULL;
        if (empty($token)) {
            SimpleLogger::error(__FILE__ . __LINE__, ['empty token']);
            $result = Valid::addAppErrors([], 'empty_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        } else {
            $studentId = AIBackendService::validateStudentToken($token);
        }

        if (empty($studentId)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid token']);
            $result = Valid::addAppErrors([], 'invalid_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $this->container['opn_pro_ver'] = $this->container['version'];
        $this->container['opn_auditing'] = 0;
        $this->container['opn_publish'] = 1;

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'OpnResMiddleWareForWeb',
            'opn_pro_ver' => $this->container['opn_pro_ver'],
            'opn_auditing' => $this->container['opn_auditing'],
            'opn_publish' => $this->container['opn_publish'],
        ]);

        $response = $next($request, $response);

        return $response;
    }
}
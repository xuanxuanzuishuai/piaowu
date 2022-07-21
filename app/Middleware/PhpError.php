<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/3/4
 * Time: 4:19 PM
 */

namespace App\Middleware;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;

class PhpError
{
    public function __invoke(Request $request, Response $response, $e)
    {
        if ($e instanceof RunTimeException) {
            SimpleLogger::error($e->getMessage(),[]);
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        } else {
            $errMsg = $e->getMessage();
            $trace = $e->getTrace();
            SimpleLogger::error(__FILE__ . ":" . __LINE__ . " Exception:", [$errMsg, $trace]);
            if ($request->getContentType() == 'application/json'
                || preg_match('/.*application\/json.*/', $request->getHeaderLine("Accept")))

            {
                $result = Valid::addErrors([], Valid::CODE_EXCEPTION, 'sys_unknown_errors',[]);
                return $response->withJson($result, 500);
            } else {
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'text/html')
                    ->write('Unkonwn Errors');
            }
        }

    }
}
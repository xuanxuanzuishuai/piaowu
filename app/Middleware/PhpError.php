<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/3/4
 * Time: 4:19 PM
 */

namespace App\Middleware;


use App\Libs\MyErrorException;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;

class PhpError
{
    public function __invoke(Request $request, Response $response, $e)
    {
        if ($e instanceof MyErrorException) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__ . " Exception:", $e->get());
            return $response->withJson($e->get(), 200);
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
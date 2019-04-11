<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/3/4
 * Time: 4:19 PM
 */

namespace App\Middleware;


use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;

class NotFound
{
    public function __invoke(Request $request, Response $response, $argv = array())
    {
        if ($request->getContentType() == 'application/json'
            || preg_match('/.*application\/json.*/', $request->getHeaderLine("Accept")))
        {
            $result = Valid::addErrors([], Valid::CODE_NOT_FOUNT, 'sys_not_found',[]);
            return $response->withJson($result, 404);
        } else {
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('Page not found!');
        }

    }
}
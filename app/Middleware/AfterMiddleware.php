<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/27
 * Time: 10:05 AM
 */

namespace App\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class AfterMiddleware extends MiddlewareBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $next
     * @return Response|static
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        $next($request, $response);
        if ($request->getContentType() == 'application/json'
            || preg_match('/.*application\/json.*/', $request->getHeaderLine("Accept"))) {
            if(!empty($this->container['result'])) {
                $result = $this->container['result'];
            }
            return $response;
        } else {
            return $response;
        }
    }
}
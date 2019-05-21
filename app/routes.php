<?php

namespace App;


use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array();

/** @var App $app */
$app->add(function (Request $request, Response $response, $next) use ($app, $arr) {
    $startTime = Util::microtime_float();
    $uri = $request->getUri()->getPath();
    $method = $request->getMethod();
    $params = $request->getParams();
    $headers = $request->getHeaders();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__ . " == REQUEST [$method] path: $uri START ==",
        ['headers' => $headers, 'params' => $params]);

    if (!empty($arr[$uri])) {
        $r = $app->map($arr[$uri]['method'], $uri, $arr[$uri]['call']);
        if (!empty($arr[$uri]['middles']) && is_array($arr[$uri]['middles'])) {
            foreach ($arr[$uri]['middles'] as $middle)
                $r->add(new $middle($app->getContainer()));
        }
        //$r->add(new AfterMiddleware($app->getContainer()));
    }

    /** @var Response $response */
    $response = $next($request, $response);

    $endTime = Util::microtime_float();
    $body = (string)$response->getBody();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__ . " == RESPONSE path: $uri END ==",
        ['duration' => $endTime - $startTime, 'body' => $body]);

    return $response;
});



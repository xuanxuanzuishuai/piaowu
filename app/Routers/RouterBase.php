<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:46 PM
 */

namespace App\Routers;


use App\Libs\SimpleLogger;
use App\Libs\Util;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

class RouterBase
{
    protected $logFilename;

    protected $uriConfig = [];

    public function load($uri, $app)
    {
        if (!empty($this->logFilename)) {
            // 将log文件重命名
            // 文件目录不变，只改变最后的*.log的名字
            $logFilePath = $_ENV['LOG_FILE_PATH'];
            $_ENV['LOG_FILE_PATH'] = preg_replace('/[^\/]*\.log$/i',
                $this->logFilename,
                $logFilePath);
        }

        $config = $this->uriConfig[$uri] ?? [];

        /** @var App $app */
        $app->add(function (Request $request, Response $response, $next) use ($app, $uri, $config) {
            $startTime = Util::microtime_float();
            $method = $request->getMethod();
            $params = $request->getParams();
            $headers = $request->getHeaders();
            SimpleLogger::debug(__FILE__ . ":" . __LINE__ . " == REQUEST [$method] path: $uri START ==",
                ['headers' => $headers, 'params' => $params]);

            if (!empty($config)) {
                $r = $app->map($config['method'], $uri, $config['call']);
                if (!empty($config['middles']) && is_array($config['middles'])) {
                    foreach ($config['middles'] as $middle)
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
    }
}
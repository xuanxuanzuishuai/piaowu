<?php
/**
 * 参数签名验证,区分大小写
 * 生成规则
 * 将"sign"除外的所有参数按照key进行字典升序排列，将server_key放在最后md5(生成字符串)
 * example:  md5(a=qingfeng.lian&m=100&service_key=123456789)
 */

namespace App\Middleware;


use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\IPService;
use Slim\Http\Request;
use Slim\Http\Response;

class SignMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $params = $request->getParams();
        $sign = $params['sign'] ?? '';
        $serviceName = $params['service_name'] ?? '';
        if (empty($sign)) {
            $sign = $request->getHeaderLine('sign');
            $serviceName = $request->getHeaderLine('service');
        }
        $serviceKey = DictConstants::get(DictConstants::SERVICE_SIGN_KEY, $serviceName);
        if (is_null($serviceKey)) {
            $errs = Valid::addErrors([], 'service_name', 'service_name_not_found');
            return $response->withJson($errs, 200);
        }
        $paramsCreateSign = self::createSign($params, $serviceKey);
        if ($sign != $paramsCreateSign) {
            $errs = Valid::addErrors([], 'sign', 'sign_error');
            SimpleLogger::info("SignMiddleware sign error", ['sign' => $paramsCreateSign, 'params' => $params]);
            return $response->withJson($errs, 200);
        }

        return $next($request, $response);
    }

    protected function createSign(array $params, string $service_key) :string
    {
        unset($params['sign']);
        ksort($params);
        $paramsStr = http_build_query($params);
        $paramsStr .='&service_key=' . $service_key;
        return md5($paramsStr);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/14
 * Time: 下午2:04
 */

namespace App\Middleware;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Models\ClassroomAppModel;
use Slim\Http\Request;
use Slim\Http\Response;

class ClassroomAppMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $headers = $request->getHeader('classroom_token');
        if(empty($headers)) {
            return HttpHelper::buildClassroomErrorResponse($response, RunTimeException::makeAppErrorData('less_classroom_token'));
        }

        $value = ClassroomAppModel::getClassroomToken($headers[0]);
        if(empty($value)) {
            return HttpHelper::buildClassroomErrorResponse($response, RunTimeException::makeAppErrorData('expire_classroom_token'));
        }

        $v = json_decode($value, 1);
        $this->container['org_id'] = $v['org_id'];
        $this->container['account'] = $v['account'];

        return $next($request,$response);
    }
}
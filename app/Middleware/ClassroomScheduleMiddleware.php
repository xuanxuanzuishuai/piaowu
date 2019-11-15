<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/24
 * Time: 6:03 PM
 */

namespace app\Middleware;

use App\Models\ClassroomAppModel;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;

class ClassroomScheduleMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $headers = $request->getHeader('schedule_token');
        if(empty($headers)) {
            return HttpHelper::buildClassroomErrorResponse($response, RunTimeException::makeAppErrorData('less_schedule_token'));
        }

        $value = ClassroomAppModel::getSchedule($headers[0]);
        if(empty($value)) {
            return HttpHelper::buildClassroomErrorResponse($response, RunTimeException::makeAppErrorData('expire_schedule_token'));
        }

        $this->container['schedule'] = json_decode($value, 1);

        return $next($request,$response);
    }
}
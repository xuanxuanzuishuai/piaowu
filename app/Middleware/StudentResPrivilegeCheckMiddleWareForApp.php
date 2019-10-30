<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/6
 * Time: 11:09 AM
 */

namespace App\Middleware;


use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\StudentModelForApp;
use App\Services\StudentServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentResPrivilegeCheckMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);

        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        $reviewFlag = $this->container['flags'][$reviewFlagId];
        if (empty($this->container['need_res_privilege']) || $reviewFlag) {
            return $response;
        }

        // TheONE设备免费
        $deviceCheck = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'device_check');
        if ($deviceCheck) {
            $isXyzDevice = md5('THEONE' . $this->container['token'] . date('Ymd') . '_1');
            if ($this->container['device_hash'] == $isXyzDevice) {
                return $response;
            }
        }

        $student = $this->container['student'];

        if (StudentModelForApp::isAnonymousStudentId($student['id'])) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['anonymous_no_res_privilege' => $student]);
            $errorCode = 'anonymous_no_res_privilege';
        }

        if (!StudentServiceForApp::getSubStatus($student['id'])) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['no_res_privilege' => $student]);
            $errorCode = 'no_res_privilege';
        }

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'StudentResPrivilegeCheckMiddleWareForApp',
            'student' => $student
        ]);

        return $response;
    }
}
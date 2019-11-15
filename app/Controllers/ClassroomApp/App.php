<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/14
 * Time: 下午1:59
 */

namespace App\Controllers\ClassroomApp;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\ClassroomAppService;
use App\Services\ClassroomDeviceService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class App extends ControllerBase
{
    public function init(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'teacher_mac',
                'type'       => 'required',
                'error_code' => 'teacher_mac_is_required'
            ],
            [
                'key'        => 'devices',
                'type'       => 'required',
                'error_code' => 'devices_is_required'
            ],
            [
                'key'        => 'devices',
                'type'       => 'array',
                'error_code' => 'devices_is_array'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            ClassroomDeviceService::updateDevices($this->ci['org_id'], $params['teacher_mac'], $params['devices']);
        } catch(RunTimeException $e) {
            return HttpHelper::buildClassroomErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildClassroomResponse($response, []);
    }

    public function versionCheck(Request $request, Response $response)
    {
        $data = ClassroomAppService::checkVersion();
        return HttpHelper::buildClassroomResponse($response, $data);
    }
}
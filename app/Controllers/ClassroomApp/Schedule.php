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
use App\Services\ClassroomScheduleService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class Schedule extends ControllerBase
{
    //开始上课
    public function start(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'class_id',
                'type'       => 'required',
                'error_code' => 'class_id_is_required'
            ],
            [
                'key'        => 'app_version',
                'type'       => 'required',
                'error_code' => 'app_version_is_required'
            ],
            [
                'key'        => 'students',
                'type'       => 'required',
                'error_code' => 'students_is_required'
            ],
            [
                'key'        => 'students',
                'type'       => 'array',
                'error_code' => 'students_is_array'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $data = ClassroomScheduleService::start(
                $this->ci['org_id'], $this->ci['account'], $params['class_id'], $params['students']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildClassroomErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildClassroomResponse($response, $data);
    }

    //下课
    public function end(Request $request, Response $response)
    {
        try {
            ClassroomScheduleService::end($this->ci['org_id'], $this->ci['schedule']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildClassroomErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildClassroomResponse($response, []);
    }
}
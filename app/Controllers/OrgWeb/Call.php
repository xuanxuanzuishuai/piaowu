<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 7:42 PM
 *
 * 客户数据相关功能
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\CallCenterService;
use App\Services\EmployeeSeatService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 外呼相关
 */
class Call extends ControllerBase
{

    /**
     * 外呼接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dialOut(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //获取学员手机号
        $studentMobile = StudentService::getStudentMobile($params['student_id']);
        if(empty($studentMobile)){
            return $response->withJson(Valid::addErrors([], 'student_mobile', 'student_mobile_not_exists'));
        }

        //获取雇员坐席ID
        $employeeId = $this->getEmployeeId();
        $userSeat = EmployeeSeatService::getEmployeeSeatInfo($employeeId);
        if (empty($userSeat)) {
            return $response->withJson(Valid::addErrors([], 'user_seat_id', 'user_seat_not_exists'));
        }

        $svcCall = new CallCenterService();
        $result = $svcCall->dialout($userSeat['seat_type'], $userSeat['seat_id'], $studentMobile, $userSeat['extend_type']);
        if ($result['res'] != 0) {
            return $response->withJson([
                'code' => Valid::CODE_PARAMS_ERROR,
                'data' => [
                    'errors' => [
                        'tel_no' => array([
                            'err_no' => 'dial_error',
                            'err_msg' => $result['errMsg']
                        ])
                    ]
                ]
            ], 200);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ], StatusCode::HTTP_OK);
    }

}
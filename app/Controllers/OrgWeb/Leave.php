<?php


namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\StudentLeaveLogModel;
use App\Services\GiftCodeDetailedService;
use App\Services\StudentLeaveLogService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Leave extends ControllerBase
{
    /**
     * 获取请假列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentLeaveList(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentLeaveList = StudentLeaveLogService::getStudentLeaveLogList($params['student_id']);

        return HttpHelper::buildResponse($response, $studentLeaveList);
    }

    /**
     * 学生请假
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentLeave(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'start_leave_date',
                'type'       => 'required',
                'error_code' => 'start_leave_date_is_required'
            ],
            [
                'key'        => 'end_leave_date',
                'type'       => 'required',
                'error_code' => 'end_leave_date_is_required',
            ],
            [
                'key'        => 'leave_days',
                'type'       => 'required',
                'error_code' => 'leave_days_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $leaveOperator = $this->ci['employee']['id'];

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = StudentLeaveLogService::studentLeave($params['student_id'], $leaveOperator, $params['start_leave_date'], $params['end_leave_date'], $params['leave_days']);
        if (!empty($errorCode)) {
            $db->rollBack();
            $result = Valid::addErrors([], 'student_leave', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    /**
     * 学生取消请假
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function cancelLeave(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $cancelOperator = $this->ci['employee']['id'];

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = StudentLeaveLogService::cancelLeave($params['id'], $cancelOperator, StudentLeaveLogModel::CANCEL_OPERATOR_COURSE);
        if (!empty($errorCode)) {
            $db->rollBack();
            $result = Valid::addErrors([], 'student_leave', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    /**
     * 获取学生请假时间段
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function leavePeriod(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentLeavePeriodList = GiftCodeDetailedService::getStudentLeavePeriod($params['student_id']);

        return HttpHelper::buildResponse($response, $studentLeavePeriodList);
    }

    /**
     * 学生是否可以请假
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function leaveStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentLeaveStatus = StudentLeaveLogService::studentLeaveStatus($params['student_id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['leave_status' => $studentLeaveStatus],
        ]);


    }
}
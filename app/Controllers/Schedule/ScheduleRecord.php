<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/25
 * Time: 下午4:27
 */

namespace App\Controllers\Schedule;

use App\Controllers\ControllerBase;
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\Valid;
use App\Services\ScheduleService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 学员上课记录
 * Class ScheduleRecord
 * @package App\Controllers\Schedule
 */
class ScheduleRecord extends ControllerBase
{
    /**
     * 学员上课记录 1对1
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function AIAttendRecord(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer',
            ],
            [
                'key'        => 'classroom_id',
                'type'       => 'integer',
                'error_code' => 'classroom_id_is_integer',
            ],
            [
                'key'        => 'course_id',
                'type'       => 'integer',
                'error_code' => 'course_id_is_integer',
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'status_is_integer',
            ],
            [
                'key'        => 'start_time',
                'type'       => 'integer',
                'error_code' => 'start_time_is_integer',
            ],
            [
                'key'        => 'end_time',
                'type'       => 'integer',
                'error_code' => 'end_time_is_integer',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $roleId = Dict::getOrgCCRoleId();
        if(empty($roleId)) {
            return $response->withJson([],'play_record', 'org_cc_role_is_empty_in_session');
        }
        if($this->isOrgCC($roleId)) {
            $params['cc_id'] = $this->ci['employee']['id'];
        }

        global $orgId;

        $courseId = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'course_id');
        $params['course_id'] = $courseId;

        list($records, $total) = ScheduleService::AIAttendRecord($orgId, $params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }

    //非1对1上课记录
    public function attendRecord(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer',
            ],
            [
                'key'        => 'classroom_id',
                'type'       => 'integer',
                'error_code' => 'classroom_id_is_integer',
            ],
            [
                'key'        => 'course_id',
                'type'       => 'integer',
                'error_code' => 'course_id_is_integer',
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'status_is_integer',
            ],
            [
                'key'        => 'start_time',
                'type'       => 'integer',
                'error_code' => 'start_time_is_integer',
            ],
            [
                'key'        => 'end_time',
                'type'       => 'integer',
                'error_code' => 'end_time_is_integer',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $roleId = Dict::getOrgCCRoleId();
        if(empty($roleId)) {
            return $response->withJson([],'play_record', 'org_cc_role_is_empty_in_session');
        }
        if($this->isOrgCC($roleId)) {
            $params['cc_id'] = $this->ci['employee']['id'];
        }

        global $orgId;

        $courseId = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'course_id');
        $params['course_id'] = $courseId;

        list($records, $total) = ScheduleService::attendRecord($orgId, $params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }
}
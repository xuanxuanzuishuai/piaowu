<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/28
 * Time: 下午6:07
 */

namespace App\Controllers\Student;


use App\Controllers\ControllerBase;
use App\Libs\Dict;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\PlayRecordService;

class PlayRecord extends ControllerBase
{
    /**
     * 查询学生练习日报，机构后台用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function reportForOrg(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'student_id',
                'type'       => 'min',
                'value'      => 0,
                'error_code' => 'student_id_must_egt_0'
            ],
            [
                'key'        => 'lesson_type',
                'type'       => 'in',
                'value'      => [0, 1],
                'error_code' => 'lesson_type_must_be_in_0_1'
            ],
        ];

        //可能包含的筛选条件,teacher_name(fuzzy), start_time(timestamp), end_time(timestamp)
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $startTime = $params['start_time'];
        $endTime = $params['end_time'];
        if(empty($startTime)) {
            $startTime = strtotime('today');
        }
        if(empty($endTime)) {
            $endTime = $startTime + 86400 - 1; //默认当天起止时间
        }
        if($startTime > $endTime) {
            list($startTime, $endTime) = [$endTime, $startTime];
        }

        //机构下的cc只能看见自己名下的学生,如果当前登录的角色是cc，要把role_id作为查询条件
        $roleId = Dict::getOrgCCRoleId();
        if(empty($roleId)) {
            return $response->withJson(Valid::addErrors([],'play_record', 'org_cc_role_is_empty_in_session'));
        }
        if($this->isOrgCC($roleId)) {
            $params['cc_id'] = $this->ci['employee']['id'];
        }

        global $orgId;

        list($records, $total) = PlayRecordService::selectReport(
            $orgId, $params['student_id'], $startTime,
            $endTime, $params['page'], $params['count'], $params
        );

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }
}
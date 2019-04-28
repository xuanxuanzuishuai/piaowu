<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 11:09
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\AIPLCenter;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\PlayRecordService;


class PlayRecord extends ControllerBase
{

    /** 获取练琴日报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function recordReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'date',
                'type' => 'required',
                'error_code' => 'date_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
//        $user_id = 22;
        $result = PlayRecordService::getDayRecordReport($user_id, $params["date"]);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /** 分享报告页面
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'jwt',
                'type' => 'required',
                'error_code' => 'jwt_token_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = PlayRecordService::parseShareReportToken($params["jwt"]);
        if ($data["code" != 0]) {
            $response->withJson(Valid::addAppErrors([], 'jwt_invalid'), StatusCode::HTTP_OK);
        }
        $result = PlayRecordService::getDayRecordReport($data["student_id"], $data["date"]);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /** 精彩回放
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWonderfulMomentUrl(Request $request, Response $response){
        $rules = [
            [
                'key' => 'ai_record_id',
                'type' => 'required',
                'error_code' => 'ai_record_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AIPLCenter::userAudio($params["ai_record_id"]);
        if (empty($data) or $data["meta"]["code"] != 0){
            $ret = [];
        } else {
            $ret = ["url" => $data["data"]["audio_url"]];
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }

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

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $startTime = $params['start_time'];
        if(empty($startTime)) {
            $startTime = strtotime('today');
        }
        $endTime = $startTime + 86400 - 1; //当天起止时间

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

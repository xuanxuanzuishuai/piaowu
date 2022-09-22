<?php
/**
 * 3s系统真人业务线 (3s系统不同业务线是不同的项目)
 * User: qingfeng.lian
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealWeekActivityModel;
use App\Services\Activity\RealWeekActivity\RealWeekActivityClientService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class GeneralCrm extends ControllerBase
{
    /**
     * 获取当前正在运行的活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getActivityList(Request $request, Response $response)
    {
        $list = RealWeekActivityModel::getStudentCanSignWeekActivity(null, time(), [
            'activity_id',
            'name'
        ]);
        return HttpHelper::buildResponse($response, [
            'list' => $list
        ]);
    }

    /**
     * 每一个活动的奖励规则列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getActivityAwardRule(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $awardRuleList = RealSharePosterPassAwardRuleModel::getRecords(['activity_id' => $params['activity_id']]);
        return HttpHelper::buildResponse($response, [
            'list' => $awardRuleList
        ]);
    }

    /**
     * 学生可参与活动历史记录列表（带筛选条件）
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentJoinActivityHistory(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_uuid',
                'type'       => 'required',
                'error_code' => 'student_uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $data = RealWeekActivityClientService::getStudentJoinActivityHistory($params['student_uuid'], $params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取学生某个互动参与的详情记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentActivityJoinRecords(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_uuid',
                'type'       => 'required',
                'error_code' => 'student_uuid_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $count) = Util::formatPageCount($params);
        $data = RealWeekActivityClientService::getStudentActivityJoinRecords($params['student_uuid'], $params['activity_id'], $page, $count);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取学生可参与活动历史记录列表（活动名称+活动id）
     * 下拉选项列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentJoinActivityHistoryList(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'student_uuid',
                'type'       => 'required',
                'error_code' => 'student_uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = RealWeekActivityClientService::getStudentJoinActivityHistoryList($params['student_uuid']);
        return HttpHelper::buildResponse($response, $data);
    }
}


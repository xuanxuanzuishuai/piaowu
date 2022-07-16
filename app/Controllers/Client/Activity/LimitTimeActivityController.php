<?php

namespace App\Controllers\Client\Activity;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityClientService;
use App\Services\Activity\LimitTimeActivity\TraitService\DssService;
use App\Services\Activity\LimitTimeActivity\TraitService\LimitTimeActivityBaseAbstract;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class LimitTimeActivityController extends ControllerBase
{
    /**
     * @param array $studentInfo
     * @return DssService
     * @throws RunTimeException
     */
    public function initServiceObj(array $studentInfo): DssService
    {
		return LimitTimeActivityBaseAbstract::getAppObj(
			$this->ci['app_id'],
			[
				'from_type'    => $this->ci['from_type'],
				'student_info' => $studentInfo
			]
		);
    }

    /**
     * 获取限时分享活动基础数据
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function baseData(Request $request, Response $response): Response
    {
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::baseData($obj);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取参与记录
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function joinRecords(Request $request, Response $response): Response
    {
        $params = $request->getParams();
        list($page, $limit) = Util::formatPageCount($params);
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::joinRecords($obj, $page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取可参与活动的任务列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function activityTaskList(Request $request, Response $response): Response
    {
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::activityTaskList($obj);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取已参与活动的任务审核列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function activityTaskVerifyList(Request $request, Response $response): Response
    {
        $rules  = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
                'error_code' => 'activity_id_is_integer'
            ],
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $limit) = Util::formatPageCount($params);
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::activityTaskVerifyList($obj, $params, $page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取任务审核详情
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function activityTaskVerifyDetail(Request $request, Response $response): Response
    {
        $rules  = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'activity_id_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::activityTaskVerifyDetail($obj, $params['id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取活动奖励规则
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function awardRule(Request $request, Response $response): Response
    {
        $rules  = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
                'error_code' => 'activity_id_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data['award_rule'] = LimitTimeActivityClientService::awardRule($params['activity_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 上传海报截图
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function posterUpload(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
                'error_code' => 'activity_id_is_integer'
            ],
            [
                'key'        => 'image_path',
                'type'       => 'required',
                'error_code' => 'image_path_is_required'
            ],
            [
                'key'        => 'task_num',
                'type'       => 'required',
                'error_code' => 'task_num_is_required'
            ],
            [
                'key'        => 'task_num',
                'type'       => 'integer',
                'error_code' => 'task_num_is_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $obj  = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::uploadSharePoster($obj, $params['activity_id'], $params['task_num'],
            $params['image_path']);
        return HttpHelper::buildResponse($response, $data);
    }
}
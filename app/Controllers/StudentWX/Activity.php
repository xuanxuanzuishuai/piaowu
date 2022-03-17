<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 10:41 AM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ActivityService;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\SharePosterService;
use App\Services\SourceMaterialService;
use App\Services\WeekActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Activity extends ControllerBase
{

    /**
     * 节点打卡截图上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function signInUpload(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'node_id',
                'type' => 'required',
                'error_code' => 'node_id_is_required'
            ],
            [
                'key' => 'image_path',
                'type' => 'required',
                'error_code' => 'image_path_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            ActivityService::signInUpload(
                $this->ci['user_info']['user_id'],
                $params['node_id'],
                $params['image_path']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取打卡数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function signInData(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        $data = ActivityService::getSignInData($this->ci['user_info']['user_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取打卡文案和海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function signInCopyWriting(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'node_id',
                'type' => 'required',
                'error_code' => 'node_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params = $request->getParams();
        $params['from_type'] = ActivityService::FROM_TYPE_WX;
        $data = ActivityService::signInCopyWriting($this->ci['user_info']['user_id'],$params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 周周有奖活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function weekActivityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['user_info'] = $this->ci['user_info'];
        $data = WeekActivityService::getCanPartakeWeekActivity(['id' => $params['user_info']['user_id']]);
        return HttpHelper::buildResponse($response, ['list' => $data]);
    }

    /**
     * 跑马灯数据-获取用户金叶子相关信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userRewardDetails(Request $request, Response $response)
    {
        try {
            $data = SourceMaterialService::userRewardDetails();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 拼团首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function collageIndex(Request $request, Response $response)
    {
        try {
            $data = ActivityService::collageIndex();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 拼团详情页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function collageDetail(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['from_type'] = ActivityService::FROM_TYPE_WX;
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $data = ActivityService::collageDetail($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取助教信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function assistantInfo(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $data = ActivityService::assistantInfo($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
    
    /**
     * 邀请活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getInviteActivity(Request $request, Response $response)
    {
        try {
            $fromType = ActivityService::FROM_TYPE_WX;
            $data = ActivityService::inviteActivityData($this->ci['user_info']['user_id'], $fromType);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 智能 - 周周领奖发奖记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharePosterSendAwardHistoryList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ErpUserEventTaskAwardGoldLeafService::getDssStudentWeekActivitySendAwardList($this->ci['user_info']['user_id'], $params);
        } catch (RunTimeException $e) {
            SimpleLogger::info("realSharePosterAwardList_error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 周周领奖活动分享任务审核记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekActivityVerifyList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'activity_id_is_integer'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $limit) = Util::formatPageCount($params);
        $data = SharePosterService::getWeekActivityVerifyList( $this->ci['user_info']['user_id'], (int)$params['activity_id'], $page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Controllers\StudentApp;

use App\Libs\DictConstants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\HalloweenService;
use App\Services\MedalService;
use App\Services\PointActivityService;
use App\Services\TermSprintService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class PointActivity extends ControllerBase
{
    /**
     * 获取用户可参与积分活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            if (empty($this->ci['version'])) {
                throw new RunTimeException(['app_version_is_required']);
            }
            $records = PointActivityService::getPointActivityListInfo($params['activity_type'], $this->ci['student']['id'], $this->ci['version']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $records);
    }

    /**
     * 任务完成上报数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function report(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_type',
                'type' => 'required',
                'error_code' => 'activity_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            if (empty($this->ci['version'])) {
                throw new RunTimeException(['app_version_is_required']);
            }
            $records = PointActivityService::reportRecord($params['activity_type'], $this->ci['student']['id'], ['app_version' => $this->ci['version'], 'play_grade_id' => $params['play_grade_id'] ?? NULL]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $records);
    }

    /**
     * 获取学生积分明细列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pointsDetail(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $data = PointActivityService::pointsDetail($this->ci['student']['id'], $page, $count);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 异步获取尚未弹出的奖章
     */
    public function needAlert(Request $request, Response $response)
    {
        $data = MedalService::getNeedAlertMedal($this->ci['student']['id'], $this->ci['version']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 学期冲刺主页信息
     */
    public function termSprint(Request $request, Response $response)
    {
        $data = TermSprintService::getTermSprintRelateTask($this->ci['student']['id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 发送新学期冲刺奖励
     */
    public function drawAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = TermSprintService::drawAward($this->ci['student']['id'], $params['task_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, [$data]);
    }

    /**
     * 获取学生总积分
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentTotalPoints(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        $data = PointActivityService::totalPoints($this->ci['student']['id'], PointActivityService::ACCOUNT_SUB_TYPE_STUDENT_POINTS);
        return HttpHelper::buildResponse($response, ['total_points' => $data['total_num']]);
    }

    /**
     * 万圣节报名
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function halloweenSignUp(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        try {
            //获取万圣节配置的活动ID
            $eventId = DictConstants::get(DictConstants::HALLOWEEN_CONFIG, ['halloween_event']);
            PointActivityService::activitySignUp($this->ci['student']['id'], $eventId[0]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, ['review_course_type' => $this->ci['student']['has_review_course']]);
    }

    /**
     * 万圣节活动用户参与数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function halloweenUserRecord(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        try {
            $data = HalloweenService::halloweenUserRecord($this->ci['student']['id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 万圣节排行榜数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function halloweenRank(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rank_limit',
                'type' => 'required',
                'error_code' => 'rank_limit_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $rank = HalloweenService::halloweenRank($this->ci['student']['id'], $params['rank_limit']);
        return HttpHelper::buildResponse($response, $rank);
    }

    /**
     * 万圣节领取奖励
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function halloweenTakeAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'task_ids',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            HalloweenService::halloweenTakeAward($this->ci['student']['id'], explode(',', $params['task_ids']));
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}
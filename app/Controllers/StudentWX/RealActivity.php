<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\SharePosterModel;
use App\Services\ActivityService;
use App\Services\PosterService;
use App\Services\RealActivityService;
use App\Services\SharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 真人业务线学生微信端接口控制器文件
 * Class StudentWXRouter
 * @package App\Routers
 */
class RealActivity extends ControllerBase
{
    /**
     * 获取周周领奖活动信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekActivity(/** @noinspection PhpUnusedParameterInspection */
        Request $request, Response $response)
    {
        try {
            $data = RealActivityService::weekActivityData($this->ci['user_info']['user_id'], ActivityService::FROM_TYPE_REAL_STUDENT_WX);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 获取月月有奖活动信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getMonthActivity(/** @noinspection PhpUnusedParameterInspection */
        Request $request, Response $response)
    {
        try {
            $data = RealActivityService::monthActivityData($this->ci['user_info']['user_id'], ActivityService::FROM_TYPE_REAL_STUDENT_WX);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取可参与周周领奖活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCanParticipateWeekActivityList(/** @noinspection PhpUnusedParameterInspection */
        Request $request, Response $response)
    {
        $data = RealActivityService::getCanParticipateWeekActivityIds(['id' => $this->ci['user_info']['user_id'], 'first_pay_time' => $this->ci['user_info']['first_pay_time'],], 2);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 周周有奖活动海报截图上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function weekActivityPosterScreenShotUpload(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
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
            //上传并发处理
            $lockKey = 'real_week_lock_' . $this->ci['user_info']['user_id'] . '_' . $params['activity_id'];
            $lock = Util::setLock($lockKey, 15);
            if (!$lock) {
                throw new RunTimeException(['']);
            }
            RealActivityService::weekActivityPosterScreenShotUpload($this->ci['user_info']['user_id'], $params['activity_id'], $params['image_path']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        } finally {
            Util::unLock($lockKey);
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 文案列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function wordList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = SharePosterService::getShareWordList($params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 截图上传历史记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $userInfo = $this->ci['user_info'];
            $student = DssStudentModel::getById($userInfo['user_id']);
            if (empty($student)) {
                throw new RunTimeException(['record_not_found']);
            }
            $params['type'] = SharePosterModel::TYPE_WEEK_UPLOAD;
            $params['user_id'] = $student['id'];
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            list($posters, $total) = SharePosterService::sharePosterList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, ['list' => $posters, 'total' => $total]);
    }

    /**
     * 截图审核详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareDetail(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'id',
                    'type' => 'required',
                    'error_code' => 'id_is_required'
                ]
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $userInfo = $this->ci['user_info'];
            $poster = SharePosterService::sharePosterDetail($params['id'], $userInfo);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $poster);
    }

    /**
     * 获取小程序码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getQrPath(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required'
            ],
            [
                'key' => 'poster_id',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $userInfo = $this->ci['user_info'];
            $params['student_id'] = $userInfo['user_id'];
            $data = PosterService::getQrPath($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

}

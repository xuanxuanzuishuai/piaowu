<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Services\ActivityService;
use App\Services\PosterService;
use App\Services\RealActivityService;
use App\Services\RealSharePosterService;
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
            //上传并发处理:一个账户针对同一个活动15秒内上传截图只允许进行一次有效动作
            $lockKey = RealWeekActivityModel::REAL_WEEK_LOCK_KEY . $this->ci['user_info']['user_id'] . '_' . $params['activity_id'];
            $lock = Util::setLock($lockKey, 5);
            if ($lock) {
                RealActivityService::weekActivityPosterScreenShotUpload($this->ci['user_info']['user_id'], $params['activity_id'], $params['image_path']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        } finally {
            Util::unLock($lockKey);
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 真人 - 周周领奖分享海报文案列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function realSharePosterWordList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['app_id'] = Constants::REAL_APP_ID;
            $data = SharePosterService::sharePosterWordList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 真人 - 截图上传历史记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharePosterHistory(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $userInfo = $this->ci['user_info'];
            $student = ErpStudentModel::getById($userInfo['user_id']);
            if (empty($student)) {
                throw new RunTimeException(['record_not_found']);
            }
            $params['type'] = RealSharePosterModel::TYPE_WEEK_UPLOAD;
            $params['student_id'] = $student['id'];
            list($page, $count) = Util::formatPageCount($params);
            $res = RealSharePosterService::sharePosterHistory($params, $page, $count);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 真人 - 截图审核详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharePosterDetail(Request $request, Response $response)
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
            $poster = RealSharePosterService::realSharePosterDetail($params['id']);
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

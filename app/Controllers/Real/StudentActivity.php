<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Controllers\Real;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ActivityExtModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Services\Activity\RealWeekActivity\RealWeekActivityClientService;
use App\Services\RealActivityService;
use App\Services\RealSharePosterService;
use App\Services\RealUserAwardMagicStoneService;
use App\Services\SharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 真人业务线学生端活动接口控制器文件
 * Class StudentActivity
 * @package App\Routers
 */
class StudentActivity extends ControllerBase
{
    /**
     * 获取周周领奖活动信息：当前时间有效活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekActivity(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response): Response
    {
        try {
            $data = RealActivityService::weekActivityData((int)$this->ci['user_info']['user_id'], $this->ci['from_type']);
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
            $data = RealActivityService::monthActivityData($this->ci['user_info']['user_id'], $this->ci['from_type']);
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
    public function getCanParticipateWeekActivityList(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $data = RealActivityService::getCanPartakeWeekActivity(['id' => $this->ci['user_info']['user_id'], 'first_pay_time' => $this->ci['user_info']['first_pay_time']]);
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
            // 检查用户是否能够参与该活动
            if (RealWeekActivityClientService::checkStudentIsUploadPoster($this->ci['user_info']['user_Id'], $params['activity_id'])) {
                throw new RunTimeException(['week_activity_user_not_upload']);
            }
            $uploadId = 0;
            //上传并发处理:一个账户针对同一个活动5秒内上传截图只允许进行一次有效动作
            $lockKey = RealWeekActivityModel::REAL_WEEK_LOCK_KEY . $this->ci['user_info']['user_id'] . '_' . $params['activity_id'];
            $lock = Util::setLock($lockKey, 5);
            if ($lock) {
                $uploadId = RealActivityService::weekActivityPosterScreenShotUpload(
                    [
                        'id' => $this->ci['user_info']['user_id'],
                        'first_pay_time' => $this->ci['user_info']['first_pay_time'],
                    ],
                    $params['activity_id'],
                    $params['image_path'],
                    $params['task_num'] ?? 0
                );
            } else {
                throw new RunTimeException(['service_busy_try_later']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        } finally {
            Util::unLock($lockKey);
        }
        return HttpHelper::buildResponse($response, [$uploadId]);
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
        return HttpHelper::buildResponse($response, $poster);
    }

    /**
     * 真人 - 获取小程序码
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
            $data = RealSharePosterService::getQrPath($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 真人 - 获取月月有奖二次跑马灯数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function realUserRewardTopList(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        try {
            $data = RealActivityService::realUserRewardTopList();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 周周领奖tab是否可以展示
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function monthAndWeekActivityShowTab(/** @noinspection PhpUnusedParameterInspection */
        Request $request, Response $response)
    {
        $data = RealActivityService::monthAndWeekActivityTabShowList($this->ci['user_info']);
        return HttpHelper::buildResponse($response, array_values($data));
    }

    /**
     * 真人 - 周周领奖发奖记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function realSharePosterAwardList(Request $request, Response $response)
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
            list($page, $limit) = Util::formatPageCount($params);
            $data = RealUserAwardMagicStoneService::getUserWeekActivityAwardList($this->ci['user_info']['user_id'], Constants::USER_TYPE_STUDENT, $page, $limit);
        } catch (RunTimeException $e) {
            SimpleLogger::info("realSharePosterAwardList_error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 真人 - 周周领奖活动奖励细则
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekActivityAwardRule(Request $request, Response $response)
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
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = ActivityExtModel::getRecord(['activity_id'=>(int)$params['activity_id']],['award_rule']);
        $data['award_rule'] = Util::textDecode($data['award_rule']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 真人 - 周周领奖活动分享任务审核记录
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
        $data = RealSharePosterService::getWeekActivityVerifyList( $this->ci['user_info']['user_id'], (int)$params['activity_id'], $page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }

}

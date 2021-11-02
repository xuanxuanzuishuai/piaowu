<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/5/23
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\KeyErrorRC4Exception;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\SharePosterModel;
use App\Services\ActivityService;
use App\Services\PosterService;
use App\Services\PosterTemplateService;
use App\Services\SharePosterService;
use App\Services\UserService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Poster extends ControllerBase
{
    /**
     * 是否可以参与
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function canJoin(Request $request, Response $response)
    {
        $canNext = UserService::judgeUserValidPay($this->ci['user_info']['user_id']);
        return HttpHelper::buildResponse($response, ['can_next' => $canNext]);
    }

    /**
     * 海报列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws KeyErrorRC4Exception
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $userInfo = $this->ci['user_info'];
            $params['from_type'] = ActivityService::FROM_TYPE_WX;
            $data = PosterTemplateService::getPosterList($userInfo['user_id'], $params['type'], $params['activity_id'] ?? 0, $params);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 截图上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function upload(Request $request, Response $response)
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
            $userInfo = $this->ci['user_info'];
            $params['student_id'] = $userInfo['user_id'];
            $data = SharePosterService::uploadSharePoster($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 文案列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function wordList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['app_id'] = Constants::SMART_APP_ID;
            $data = SharePosterService::getShareWordList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
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
                'key'        => 'channel_id',
                'type'       => 'required',
                'error_code' => 'channel_id_is_required'
            ],
            [
                'key'        => 'poster_id',
                'type'       => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $userInfo             = $this->ci['user_info'];
            $params['student_id'] = $userInfo['user_id'];
            $data                 = PosterService::getQrPath($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/04/21
 * Time: 11:00 PM
 */

namespace App\Controllers\StudentWX;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Libs\HttpHelper;
use App\Services\ReferralActivityService;
use App\Services\SharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;

class ReferralActivity extends ControllerBase
{

    /**
     * 活动信息
     * @param Response $response
     * @return Response
     */
    public function activityInfo(Request $request, Response $response)
    {
        //获取数据
        try {
            $studentId = $this->ci['user_info']['user_id'];
            $activityData = ReferralActivityService::getReferralActivityTipInfo($studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, $activityData);
    }

    /**
     * 分享图片上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function uploadSharePoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'activity_id_is_invalid'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $studentId = $this->ci['user_info']['user_id'];
            SharePosterService::uploadSharePoster($params['activity_id'], $params['poster_url'], $studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取学生参加活动的记录列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function joinRecordList(Request $request, Response $response)
    {
        //学生ID
        $studentId = $this->ci['user_info']['user_id'];
        list($page, $count) = Util::formatPageCount($request->getParams());
        $data = SharePosterService::joinRecordList($studentId, $page, $count);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 返现活动信息
     * @param Response $response
     * @return Response
     */
    public function returnCashActivityInfo(Request $request, Response $response)
    {
        //获取数据
        try {
            $studentId = $this->ci['user_info']['user_id'];
            $activityData = ReferralActivityService::returnCashActivityTipInfo($studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, $activityData);
    }

    /**
     * 返现活动截图图片上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function uploadReturnCashPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $studentId = $this->ci['user_info']['user_id'];
            SharePosterService::uploadReturnCashPoster($params['poster_url'], $studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}
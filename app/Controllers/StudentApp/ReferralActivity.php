<?php

/**
 * Created by PhpStorm.
 * User: yuxingkui
 * Date: 2021/04/13
 * Time: 03:35 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Dss;
use App\Libs\HttpHelper;
use App\Libs\Valid;
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
	        $result = (new Dss())->getActivityInfo($studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

	    //返回数据
	    return $response->withJson($result, StatusCode::HTTP_OK);
    }


	/**
	 * 周周有礼图片上传
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
			$params['student_id'] = $this->ci['user_info']['user_id'];
			$result = (new Dss())->uploadSharePoster($params);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}

		//返回数据
		return $response->withJson($result, StatusCode::HTTP_OK);
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
		[$page, $count] = Util::formatPageCount($request->getParams());
		try {
			$data = SharePosterService::joinRecordList($studentId, $page, $count);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, $data);
	}



}
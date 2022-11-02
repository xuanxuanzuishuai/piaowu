<?php

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\Sop\SopService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AdTrack extends ControllerBase
{
	/**
	 * 获取微信公众号sop数据
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function sop(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'wx_original_id',
				'type'       => 'required',
				'error_code' => 'wx_original_id_is_required'
			],
			[
				'key'        => 'event',
				'type'       => 'required',
				'error_code' => 'event_is_required'
			],
		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		$data = SopService::thirdServiceGetSops($params["wx_original_id"], $params["event"], $params["extra"] ?? "");
		return HttpHelper::buildResponse($response, $data);
	}

	/**
	 * 获取微信公众号sop数据发送结果统计
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function sopStatics(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'wx_original_id',
				'type'       => 'required',
				'error_code' => 'wx_original_id_is_required'
			],
			[
				'key'        => 'sop_id',
				'type'       => 'required',
				'error_code' => 'sop_id_is_required'
			],
			[
				'key'        => 'sop_details_id',
				'type'       => 'required',
				'error_code' => 'sop_details_id_is_required'
			],
			[
				'key'        => 'open_id',
				'type'       => 'required',
				'error_code' => 'open_id_is_required'
			],
			[
				'key'        => 'union_id',
				'type'       => 'required',
				'error_code' => 'union_id_is_required'
			],
			[
				'key'        => 'error_code',
				'type'       => 'required',
				'error_code' => 'error_code_is_required'
			],

		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		SopService::sopStaticsAdd($params);
		return HttpHelper::buildResponse($response, []);
	}
}


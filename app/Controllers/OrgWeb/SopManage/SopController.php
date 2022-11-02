<?php

namespace App\Controllers\OrgWeb\SopManage;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\Sop\SopService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use function AlibabaCloud\Client\env;

class SopController extends ControllerBase
{
	/**
	 * 下拉框搜索条件列表
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function selects(Request $request, Response $response): Response
	{
		$dictData = SopService::selects();
		return HttpHelper::buildResponse($response, $dictData);
	}

	/**
	 * sop公众号与小程序绑定关系
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function wxBindMiniApp(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'wx_original_id',
				'type'       => 'required',
				'error_code' => 'wx_original_id_is_required'
			]
		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		$formatData = json_decode(env("WX_BIN_MINI_APP_MAP"), true);
		return HttpHelper::buildResponse($response, $formatData[$params["wx_original_id"]] ?? []);
	}


	/**
	 * 创建
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function add(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'wx_original_id',
				'type'       => 'required',
				'error_code' => 'wx_original_id_is_required'
			],
			[
				'key'        => 'name',
				'type'       => 'required',
				'error_code' => 'sop_name_is_required'
			],
			[
				'key'        => 'name',
				'type'       => 'lengthMax',
				'value'      => 30,
				'error_code' => 'sop_name_length_invalid'
			],
			[
				'key'        => 'exec_type',
				'type'       => 'required',
				'error_code' => 'exec_type_is_required'
			],
			[
				'key'        => 'exec_type',
				'type'       => 'in',
				'value'      => [1, 2],
				'error_code' => 'exec_type_is_error'
			],
			[
				'key'        => 'details',
				'type'       => 'required',
				'error_code' => 'sop_details_is_required'
			],
			[
				'key'        => 'details',
				'type'       => 'array',
				'error_code' => 'sop_details_is_error'
			],
		];

		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		try {
			SopService::add($params, $this->ci['employee']['uuid']);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, []);
	}

	/**
	 * 修改
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function update(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'sop_id',
				'type'       => 'required',
				'error_code' => 'sop_id_is_required'
			],
			[
				'key'        => 'wx_original_id',
				'type'       => 'required',
				'error_code' => 'wx_original_id_is_required'
			],
			[
				'key'        => 'name',
				'type'       => 'required',
				'error_code' => 'sop_name_is_required'
			],
			[
				'key'        => 'name',
				'type'       => 'lengthMax',
				'value'      => 30,
				'error_code' => 'sop_name_length_invalid'
			],
			[
				'key'        => 'exec_type',
				'type'       => 'required',
				'error_code' => 'exec_type_is_required'
			],
			[
				'key'        => 'exec_type',
				'type'       => 'in',
				'value'      => [1, 2],
				'error_code' => 'exec_type_is_error'
			],
			[
				'key'        => 'details',
				'type'       => 'required',
				'error_code' => 'sop_details_is_required'
			],
			[
				'key'        => 'details',
				'type'       => 'array',
				'error_code' => 'sop_details_is_error'
			],
		];

		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		try {
			SopService::update($params, $this->ci['employee']['uuid']);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, []);
	}

	/**
	 * 获取规则详情
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function detail(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'sop_id',
				'type'       => 'required',
				'error_code' => 'sop_id_is_required'
			],
			[
				'key'        => 'sop_id',
				'type'       => 'min',
				'value'      => 1,
				'error_code' => 'sop_id_is_integer'
			]
		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		try {
			$data = SopService::detail($params["sop_id"]);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, $data);
	}

	/**
	 * 列表
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function list(Request $request, Response $response): Response
	{
		$params = $request->getParams();
		list($params['page'], $params['count']) = Util::formatPageCount($params);
		$data = SopService::list($params);
		return HttpHelper::buildResponse($response, $data);
	}

	/**
	 * 启用/禁用规则
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function enableOrDisable(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'sop_id',
				'type'       => 'required',
				'error_code' => 'sop_id_is_required'
			],
			[
				'key'        => 'sop_id',
				'type'       => 'min',
				'value'      => 1,
				'error_code' => 'sop_id_is_integer'
			],
			[
				'key'        => 'status',
				'type'       => 'required',
				'error_code' => 'sop_status_is_required'
			],
			[
				'key'        => 'status',
				'type'       => 'in',
				'value'      => [1, 2],
				'error_code' => 'sop_status_invalid'
			]
		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		try {
			SopService::enableOrDisable($params["sop_id"], $params["status"], $this->ci['employee']['uuid']);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, []);
	}

	/**
	 * 删除规则
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function delete(Request $request, Response $response): Response
	{
		$rules = [
			[
				'key'        => 'sop_id',
				'type'       => 'required',
				'error_code' => 'sop_id_is_required'
			],
			[
				'key'        => 'sop_id',
				'type'       => 'min',
				'value'      => 1,
				'error_code' => 'sop_id_is_integer'
			]
		];
		$params = $request->getParams();
		$result = Valid::appValidate($params, $rules);
		if ($result['code'] != Valid::CODE_SUCCESS) {
			return $response->withJson($result, StatusCode::HTTP_OK);
		}
		try {
			SopService::delete($params["sop_id"], $this->ci['employee']['uuid']);
		} catch (RunTimeException $e) {
			return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
		}
		return HttpHelper::buildResponse($response, []);
	}

}
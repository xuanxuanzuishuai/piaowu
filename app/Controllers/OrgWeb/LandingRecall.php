<?php
/**
 * landing页召回短信 - 活动管理
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\LandingRecallService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class LandingRecall extends ControllerBase
{
    /**
     * 获取下拉框键值对
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function extInfo(Request $request, Response $response)
    {
        $res = LandingRecallService::getExtInfo();
        return HttpHelper::buildResponse($response, $res);
    }
    
    /**
     * 添加活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function save(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 60,
                'error_code' => 'name_length_invalid'
            ],
            [
                'key' => 'target_population',
                'type' => 'required',
                'error_code' => 'target_population_is_required'
            ],
            [
                'key' => 'send_time',
                'type' => 'required',
                'error_code' => 'send_time_is_required'
            ],
            [
                'key' => 'sms_content',
                'type' => 'required',
                'error_code' => 'sms_content_is_required'
            ],
            [
                'key' => 'sms_content',
                'type' => 'lengthMax',
                'value' => 255,
                'error_code' => 'sms_content_length_invalid'
            ],
            [
                'key' => 'voice_call_type',
                'type' => 'integer',
                'error_code' => 'voice_call_type_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            $params['channel'] = implode(',', $params['channel']??[]);
            if (!empty($params['id'])) {
                LandingRecallService::edit($params, $employeeId);
            } else {
                LandingRecallService::add($params, $employeeId);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }
    
    /**
     * 获取规则列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $data = LandingRecallService::searchList($params, $page, $limit);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
    
    /**
     * 获取规则详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'integer',
                'error_code' => 'id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = LandingRecallService::getDetailById($params['id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
    
    /**
     * 规则 启用和禁用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editEnableStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'integer',
                'error_code' => 'id_is_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'required',
                'error_code' => 'enable_status_is_required'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'enable_status_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            LandingRecallService::editEnableStatus($params['id'], $params['enable_status'], $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }
    
    /**
     * 发送短信提醒
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendMsg(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];
        
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        
        try {
            $result = LandingRecallService::sendMsg($params['id'], $params['mobile']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        
        return HttpHelper::buildResponse($response, $result);
    }
    
    /**
     * 发送短信统计
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendCount(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            $result = LandingRecallService::sendCount($params['date'] ?? '');
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        
        return HttpHelper::buildResponse($response, $result);
    }
}

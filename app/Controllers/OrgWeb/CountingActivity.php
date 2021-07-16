<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentOrgService;
use App\Services\CountingActivityService;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class CountingActivity extends ControllerBase
{

    /**
     * 添加活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function create(Request $request, Response $response)
    {
        $params = $request->getParams();
        $result = $this->_checkParams($params);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operator_id = $this->getEmployeeId();

        $result = CountingActivityService::createCountingActivity($params, $operator_id);

        if($result){
            return HttpHelper::buildResponse($response, []);
        }else{
            return HttpHelper::buildOrgWebErrorResponse($response, 'error');
        }
    }


    /**
     * 活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getActivityList(Request $request, Response $response){

        $params = $request->getParams();
        list($page, $pageSize) = Util::formatPageCount($params);

        $result = CountingActivityService::getCountingList($params, $page, $pageSize);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 更新状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editStatus(Request $request, Response $response){
        $op_activity_id = $request->getParam('op_activity_id');
        $status      = $request->getParam('status');

        $result = CountingActivityService::editStatus($op_activity_id, $status);

        if($result){
            return HttpHelper::buildResponse($response,[]);
        }else{
            return HttpHelper::buildOrgWebErrorResponse($response, 'error');
        }
    }

    public function _checkParams($params){

        $rules = [
            //必填项
            ['key' => 'name',           'type' => 'required', 'error_code' => 'name_is_required'],
            ['key' => 'start_time',     'type' => 'required', 'error_code' => 'start_time_is_required'],
            ['key' => 'end_time',       'type' => 'required', 'error_code' => 'end_time_is_required'],
            ['key' => 'sign_end_time',  'type' => 'required', 'error_code' => 'sign_end_time_is_required'],
            ['key' => 'join_end_time',  'type' => 'required', 'error_code' => 'join_end_time_is_required'],
            ['key' => 'rule_type',      'type' => 'required', 'error_code' => 'rule_type_is_required'],
            ['key' => 'nums',           'type' => 'required', 'error_code' => 'nums_is_required'],
            ['key' => 'title',          'type' => 'required', 'error_code' => 'title_is_required'],
            ['key' => 'instruction',    'type' => 'required', 'error_code' => 'instruction_is_required'],
            ['key' => 'award_thumbnail','type' => 'required', 'error_code' => 'award_thumbnail_is_required'],
            ['key' => 'award_rule',     'type' => 'required', 'error_code' => 'award_rule_is_required'],

            //类型限制
            ['key' => 'nums',           'type' => 'integer', 'error_code' => 'nums_is_integer'],
            //长度限制
            ['key' => 'name',           'type' => 'lengthMax', 'error_code' => 'name_length_error','value'=> 50],
            ['key' => 'remark',         'type' => 'lengthMax', 'error_code' => 'remark_length_error','value'=> 50],
            //范围限制
            ['key' => 'nums',           'type' => 'max', 'value' => 10, 'error_code' => 'nums_between_1_10'],
            ['key' => 'nums',           'type' => 'min', 'value' => 1, 'error_code' => 'nums_between_1_10'],
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $result;
        }

        $ymd = date('Y-m-d H:i:s');

        if($params['start_time'] < $ymd){
            return Valid::addAppErrors([], 'start_time_is_small');
        }

        if($params['sign_end_time'] < $params['start_time']){
            return Valid::addAppErrors([], 'sign_end_time_is_small');
        }

        if($params['join_end_time'] < $params['sign_end_time']){
            return Valid::addAppErrors([], 'join_end_time_is_small');
        }

        if($params['end_time'] < $params['join_end_time']){
            return Valid::addAppErrors([], 'end_time_is_small');
        }

        return ['code'=>0];
    }

    /**
     * 获取奖品列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getAwardList(Request $request, Response $response){
        $params = $request->getParams();

        $result = CountingActivityService::getAwardList($params);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 更新活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editActivity(Request $request, Response $response){
        $params = $request->getParams();
        $result = $this->_checkParams($params);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operator_id = $this->getEmployeeId();

        try{
            CountingActivityService::editActivity($params, $operator_id);
            return HttpHelper::buildResponse($response, $result);
        }catch (RuntimeException $e){
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
    }

    public function getCountingActivityDetail(Request $request, Response $response)
    {
        $op_activity_id = $request->getParam('op_activity_id');
        $detail = CountingActivityService::getCountDetail($op_activity_id);
        return HttpHelper::buildResponse($response, $detail);

    }

}
<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/18
 * Time: 3:24 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\Erp;
use App\Models\WeChatAwardCashDealModel;
use App\Services\ErpReferralService;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Referral extends ControllerBase
{
    /**
     * 转介绍配置
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function config(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {

        $names = ErpReferralService::EVENT_TASKS;

        // 如有来源，根据来源取页面[奖励节点]select数据
        $params = $request->getParams();
        if (isset($params['source']) && $params['source'] == 'referee') {
            $names = ErpReferralService::REFEREE_EVENT_TASKS;
        }

        $config = [
            'event_task_name' => $names,
            'award_status' => ErpReferralService::AWARD_STATUS,
        ];

        return HttpHelper::buildResponse($response, $config);
    }

    /**
     * 转介绍列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function referredList(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $ret = ErpReferralService::getReferredList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * 转介绍奖励列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function awardList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if ($params['award_status'] === ''){
            unset($params['award_status']);
        }
        if(empty($params['award_type'])) {
            $params['award_type'] = ErpReferralService::AWARD_TYPE_CASH;
        }

        try {
            $ret = ErpReferralService::getAwardList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * 转介绍二期红包发放审核列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeAwardList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];

        $params                 = $request->getParams();
        $params['award_relate'] = Erp::AWARD_RELATE_REFEREE;
        $result                 = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (isset($params['award_status']) && $params['award_status'] === '') {
            unset($params['award_status']);
        }
        if (empty($params['award_type'])) {
            $params['award_type'] = ErpReferralService::AWARD_TYPE_CASH;
        }

        try {
            $ret = ErpReferralService::getAwardList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * 转介绍二期新增红包发放审核
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeUpdateAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $ret = ErpReferralService::updateAward(
                $params['award_id'],
                $params['status'],
                $this->getEmployeeId(),
                $params['reason'],
                WeChatAwardCashDealModel::REFERRER_PIC_WORD,
                $params['event_task_id']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }
    /**
     * 更新奖励状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $ret = ErpReferralService::updateAward(
                $params['award_id'],
                $params['status'],
                $this->getEmployeeId(),
                $params['reason'],
                WeChatAwardCashDealModel::NORMAL_PIC_WORD,
                $params['event_task_id']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 微信的用户信息
     */
    public function receiveInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'user_event_task_award_id',
                'type' => 'required',
                'error_code' => 'user_event_task_award_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $openId = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $params['user_event_task_award_id']], 'open_id');
        $wxInfo = $openId ? WeChatService::getUserInfo($openId) : [];
        return HttpHelper::buildResponse($response, $wxInfo);
    }
}
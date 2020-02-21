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
use App\Services\ErpReferralService;
use Slim\Http\Request;
use Slim\Http\Response;

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
        $config = [
            'event_task_name' => ErpReferralService::EVENT_TASKS,
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
        $params = $request->getParams();

        if ($params['award_status'] === ''){
            unset($params['award_status']);
        }

        try {
            $ret = ErpReferralService::getAwardList($params);
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
        $params = $request->getParams();

        try {
            $ret = ErpReferralService::updateAward($params['award_id'],
                $params['status'],
                $this->getEmployeeId(),
                $params['reason']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }
}
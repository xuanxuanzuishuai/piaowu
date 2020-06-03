<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/2/18
 * Time: 3:24 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Models\WeChatAwardCashDealModel;
use App\Services\ErpReferralService;
use Slim\Http\Request;
use Slim\Http\Response;

class CommunityRedPack extends ControllerBase
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
            'award_status' => ErpReferralService::AWARD_STATUS,
        ];

        return HttpHelper::buildResponse($response, $config);
    }

    /**
     * 红包返现列表
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
        if(empty($params['award_type'])) {
            $params['award_type'] = ErpReferralService::AWARD_TYPE_CASH;
        }

        try {
            $ret = ErpReferralService::getCommunityAwardList($params);
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
                $params['reason'],
            WeChatAwardCashDealModel::COMMUNITY_PIC_WORD);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }
}
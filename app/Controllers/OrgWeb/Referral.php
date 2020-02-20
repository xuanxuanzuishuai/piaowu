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
            'event_task_name' => ErpReferralService::REF_EVENT_TASK_INFO
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
}
<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/16
 * Time: 11:08
 */

namespace App\Controllers\Referral;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Services\RefereeAwardService;
use App\Services\ReferralService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Award extends ControllerBase
{
    /**
     * 打卡奖励列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['award_type'] = ErpUserEventTaskAwardModel::AWARD_TYPE_CASH;
            if (!empty($params['event_task_id']) && in_array($params['event_task_id'], ReferralService::getNotDisplayWaitGiveTask())) {
                $params['not_award_status'] = [ErpUserEventTaskAwardModel::STATUS_WAITING];
            }

            list($records, $totalCount) = RefereeAwardService::getAwardList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'list' => $records,
            'total_count' => $totalCount
        ]);
    }

    /**
     * 红包发放
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'award_id',
                'type' => 'required',
                'error_code' => 'award_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $ret = RefereeAwardService::updateAward(
                $params['award_id'],
                $params['status'],
                $this->getEmployeeId(),
                $params['reason']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * 列表选项
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function config(Request $request, Response $response)
    {
        $params = $request->getParams();
        $names = ReferralService::getAwardNode($params['source']);
        $eventTask = [];
        foreach ($names as $key => $value) {
            $eventTask[] = [
                'code' => $key,
                'value' => $value,
            ];
        }

        $config = [
            'event_task_name'   => $eventTask,
            'has_review_course' => DictConstants::getSet(DictConstants::HAS_REVIEW_COURSE),
            'award_status'      => ErpUserEventTaskAwardModel::STATUS_DICT,
        ];

        return HttpHelper::buildResponse($response, $config);
    }

}
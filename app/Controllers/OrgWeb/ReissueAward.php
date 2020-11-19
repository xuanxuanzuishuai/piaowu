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
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ErpReferralService;
use App\Services\ApplyAwardService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ReissueAward extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 提交申请
     */
    public function submitApply(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ],
            [
                'key'        => 'reissue_reason',
                'type'       => 'required',
                'error_code' => 'reissue_reason_is_required',
            ],
            [
                'key'        => 'reissue_reason',
                'type'       => 'lengthMax',
                'value'      => 500,
                'error_code' => 'reissue_reason_length_more_then_500'
            ],
            [
                'key'        => 'image_key_arr',
                'type'       => 'array',
                'error_code' => 'image_key_arr_must_be_arr'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{
            ApplyAwardService::applyAward($this->getEmployeeId(), $params['student_id'], $params['event_task_id'], $params['reissue_reason'], $params['image_key_arr']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 节点对应的奖励信息
     */
    public function getTaskAward(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = ErpReferralService::getExpectTaskIdRelateAward($params['event_task_id']);

        return HttpHelper::buildResponse($response, ['data' => $data]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 申请列表
     */
    public function applyList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        list($data, $totalNum) = ApplyAwardService::getApplyList($params, $page, $count);
        return HttpHelper::buildResponse($response, ['data' => $data, 'total_count' => $totalNum]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     * 申请详情
     */
    public function applyDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $data = ApplyAwardService::getApplyDetail($params['id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['data' => $data]);
    }
}
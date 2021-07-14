<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\Referral;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\ReferralService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Invite extends ControllerBase
{
    /**
     * 列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $list = ReferralService::getReferralList($params);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     *  转介绍学员列表，包括rt活动优惠券信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function listAndCoupon(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            $list = ReferralService::getReferralListAndCoupon($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $list);
    }


    /**
     * 当前这个人的推荐人信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function referralDetail(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'student_id',
                    'type' => 'required',
                    'error_code' => 'student_id_is_required'
                ],
                [
                    'key' => 'app_id',
                    'type' => 'required',
                    'error_code' => 'app_id_is_required'
                ]
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $params = $request->getParams();
            $info = ReferralService::getReferralInfo($params['app_id'], $params['student_id'], $params['parent_bill_id']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'referee_info' => $info
        ]);
    }

    /**
     * 当前这个人的推荐人信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function batchReferralDetail(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'student_id',
                    'type' => 'required',
                    'error_code' => 'student_id_is_required'
                ],
                [
                    'key' => 'app_id',
                    'type' => 'required',
                    'error_code' => 'app_id_is_required'
                ]
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $params = $request->getParams();
            $info = StudentReferralStudentStatisticsModel::getRecords(['student_id' => $params['student_id']], ['student_id', 'referee_id']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, 
             $info
        );
    }

    /**
     * 当前这个人推荐过来的所有用户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeAllUser(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'student_id',
                    'type' => 'required',
                    'error_code' => 'student_id_is_required'
                ],
                [
                    'key' => 'app_id',
                    'type' => 'required',
                    'error_code' => 'app_id_is_required'
                ],
                [
                    'key' => 'referee_type',
                    'type' => 'required',
                    'error_code' => 'referee_type_is_required'
                ]
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $params = $request->getParams();
            $info = ReferralService::getRefereeAllUser($params['app_id'], $params['student_id']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'referee_all_user' => $info
        ]);
    }

}
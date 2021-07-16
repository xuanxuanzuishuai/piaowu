<?php


namespace App\Controllers\StudentWX;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\RtActivityModel;
use App\Services\RtActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class RtActivity extends ControllerBase
{

    /**
     * 推荐人首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function inviteIndex(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $params['user_info'] = $this->ci['user_info'] ?? [];
            $data = RtActivityService::inviteIndex($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 被邀人首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function invitedIndex(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'invite_uid',
                'type' => 'required',
                'error_code' => 'invite_uid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $data = RtActivityService::invitedIndex($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }



    /**
     * 获取海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'employee_uuid',
                'type' => 'required',
                'error_code' => 'employee_uuid_is_required'
            ],
            [
                'key' => 'employee_id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $params['type'] = RtActivityModel::ACTIVITY_RULE_TYPE_STUDENT;
            $data = RtActivityService::getPoster($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 领取优惠券
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function receiveCoupon(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'invite_uid',
                'type'       => 'required',
                'error_code' => 'invite_uid_is_required'
            ],
            [
                'key'        => 'is_new',
                'type'       => 'required',
                'error_code' => 'is_new_is_required'
            ],
            [
                'key'        => 'param_id',
                'type'       => 'required',
                'error_code' => 'param_id_is_required'
            ],
            [
                'key'        => 'employee_id',
                'type'       => 'required',
                'error_code' => 'employee_id_is_required'
            ],
            [
                'key'        => 'employee_uuid',
                'type'       => 'required',
                'error_code' => 'employee_uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $data = RtActivityService::receiveCoupon($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 领取优惠券后-获取页面信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function couponCollecte(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $data = RtActivityService::couponCollecte($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
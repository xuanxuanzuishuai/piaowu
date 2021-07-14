<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/5/11
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\CountingActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ActivitySign extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            list($list, $total) = CountingActivityService::getSignList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 用户参与详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'user_id',
                'type' => 'required',
                'error_code' => 'user_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            list($student, $weekActivityDetail, $signRecordDetails) = CountingActivityService::getUserSignList($params['user_id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'student' => $student,
            'week_detail' => $weekActivityDetail,
            'sign_detail' => $signRecordDetails
        ]);
    }
}

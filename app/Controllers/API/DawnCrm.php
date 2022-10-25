<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\Morning\MorningReferralStatisticsService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class DawnCrm extends ControllerBase
{
    /**
     * 清晨 - 创建转介绍关系
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function morningCreateReferral(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'student_uuid',
                'type'       => 'required',
                'error_code' => 'student_uuid_is_required'
            ],
            [
                'key'        => 'type',
                'type'       => 'required',
                'error_code' => 'order_type_is_required'
            ],
            [
                'key'        => 'order_id',
                'type'       => 'required',
                'error_code' => 'bill_not_exist'
            ],
            [
                'key'        => 'metadata',
                'type'       => 'required',
                'error_code' => 'metadata_uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 处理转介绍关系
        $params['uuid'] = $params['student_uuid'];
        $params['order_type'] = $params['type'];
        $res = MorningReferralStatisticsService::createReferral($params);
        SimpleLogger::info("morning create referral res:", [$res]);
        // 再次查询转介绍关系，确保返回的是一定存在的
        $referralInfo = MorningReferralStatisticsService::getStudentRefereeList([$params['student_uuid']])[0] ?? [];
        return HttpHelper::buildResponse($response, $referralInfo);
    }


    /**
     * 清晨 - 获取用户推荐人
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getMorningStudentReferee(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'student_uuids',
                'type'       => 'required',
                'error_code' => 'student_uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentUuids = array_slice($params['student_uuids'], 0, 500);
        $data = MorningReferralStatisticsService::getStudentRefereeList($studentUuids);
        return HttpHelper::buildResponse($response, $data);
    }
}


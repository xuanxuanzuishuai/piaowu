<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/22
 * Time: 4:27 PM
 */

namespace App\Controllers\Boss;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\StudentModelForApp;
use App\Services\GiftCodeService;
use App\Services\Queue\QueueService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class GiftCode extends ControllerBase
{
    public function add(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'valid_num',
                'type' => 'required',
                'error_code' => 'valid_num_is_required'
            ],
            [
                'key' => 'valid_num',
                'type' => 'integer',
                'error_code' => 'valid_num_must_be_integer'
            ],
            [
                'key' => 'buyer',
                'type' => 'required',
                'error_code' => 'buyer_is_required'
            ],
            [
                'key' => 'remarks',
                'type' => 'required',
                'error_code' => 'remark_is_required'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //当前操作人
        $employeeId = $this->ci['employee']['id'];
        $uuids = [];
        $buyers = explode(',', $params['buyer']);
        foreach ($buyers as $mobile) {
            $student = StudentModelForApp::getStudentInfo(null, $mobile, null);
            if (empty($student)) {
                $result = Valid::addErrors([], 'buyer', 'unknown_student_mobile', $mobile);
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $uuids[] = $student['uuid'];
        }

        // erp赠单逻辑
        $applyType = PackageExtModel::APPLY_TYPE_SMS;
        $generateChannel = GiftCodeModel::BUYER_TYPE_STUDENT;
        QueueService::giftDuration($uuids, $applyType, $params['valid_num'], $generateChannel, $employeeId, $params['remarks']);


        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    //获取激活码列表
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        //获取激活码
        list($totalCount, $codeData) = GiftCodeService::batchGetCode($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'code_data' => $codeData
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 机构激活码列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function listForOrg(Request $request, Response $response)
    {
        $params = $request->getParams();
        global $orgId;
        $params['org_id'] = $orgId;

        list($totalCount, $codeData) = GiftCodeService::batchGetCode($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'code_data' => $codeData
            ]
        ], StatusCode::HTTP_OK);
    }

    //作废激活码
    public function abandon(Request $request, Response $response) {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'ids',
                'type' => 'required',
                'error_code' => 'ids_is_required'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //一次只允许作废一个激活码
        if (strstr($params['ids'], ',')) {
            return $response->withJson(Valid::addAppErrors([], 'abandon_gift_code_ids_error'), StatusCode::HTTP_OK);
        }
        try {
            $updatedCount = GiftCodeService::orgWebAbandonCode($params['ids']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'count' => $updatedCount,
        ], StatusCode::HTTP_OK);
    }
}
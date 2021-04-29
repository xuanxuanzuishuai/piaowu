<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/22
 * Time: 4:49 PM
 */

namespace App\Controllers\ReferralMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Services\BillMapService;
use App\Services\ErpOrderV1Service;
use App\Services\PayServices;
use App\Services\ShowMiniAppService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Pay extends ControllerBase
{
    /**
     * 创建订单
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key'        => 'pkg',
                'type'       => 'required',
                'error_code' => 'pkg_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $student = DssStudentModel::getRecord(['uuid' => $params['uuid']]);
            if (empty($student)) {
                throw new RunTimeException(['record_not_found']);
            }
            $openId = $params['open_id'] ?? '';

            $packageId = PayServices::getPackageIDByParameterPkg($params['pkg']);
            // 微信支付，用code换取支付用公众号的open_id
            if (empty($openId) && !empty($params['wx_code'])) {
                $appId    = Constants::SMART_APP_ID;
                $busiType = Constants::SMART_MINI_BUSI_TYPE;
                $wechat   = WeChatMiniPro::factory($appId, $busiType);
                $data     = $wechat->code2Session($params['wx_code']);
                if (empty($data['openid'])) {
                    SimpleLogger::error('can_not_obtain_open_id', [$data]);
                }
                $openId = $data['openid'];
            }
            $student = [
                'id'         => $student['id'],
                'uuid'       => $student['uuid'],
                'open_id'    => $openId,
                'address_id' => $params['address_id'] ?? true
            ];
            $payChannel   = PayServices::payChannelToV1($params['pay_channel']);
            $payType      = PayServices::PAY_TYPE_DIRECT;
            $employeeUuid = !empty($params['employee_id']) ? RC4::decrypt($_ENV['COOKIE_SECURITY_KEY'], $params['employee_id']) : null;
            $channel      = $params['channel_id'] ?? ErpPackageV1Model::CHANNEL_WX;
            if ($params['pkg'] == PayServices::PACKAGE_0) {
                // 0元体验课订单
                $remark = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'zero_order_remark');
                $res = ErpOrderV1Service::createZeroOrder($packageId, $student, $remark);
            } else {
                $res = ErpOrderV1Service::createOrder($packageId, $student, $payChannel, $payType, $employeeUuid, $channel);
            }
            if (empty($res)) {
                $res = Valid::addAppErrors([], 'create_bill_error');
            }
            $orderId = $res['order_id'] ?? '';
            //转介绍订单关系绑定
            $sceneData = ShowMiniAppService::getSceneData($params['scene'] ?? '');
            if (!empty($orderId) && !empty($sceneData)) {
                BillMapService::mapDataRecord($sceneData, $orderId, $student['id']);
            }
            $res['data']['bill'] = [
                'id' => $orderId
            ];
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 查询订单状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function billStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'bill_id',
                'type'       => 'required',
                'error_code' => 'bill_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $status = PayServices::getBillStatus($params['bill_id']);

        // $status 可能为 '0', 要用全等
        if ($status === null) {
            $result = Valid::addAppErrors([], 'bill_not_exist');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        return HttpHelper::buildResponse($response, ['bill_status' => $status]);
    }
}

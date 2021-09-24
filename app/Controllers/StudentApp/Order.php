<?php


namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\RC4;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Services\BillMapService;
use App\Services\ErpOrderV1Service;
use App\Services\PayServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Order extends ControllerBase
{
    /**
     * 下单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key' => 'package_id',
                'type' => 'integer',
                'error_code' => 'package_id_must_be_integer',
            ],
            [
                'key' => 'pay_channel',
                'type' => 'integer',
                'error_code' => 'pay_channel_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $studentId = $this->ci['user_info']['user_id'] ?? '';
            $studentInfo = DssStudentModel::getById($studentId);
            if (empty($studentInfo)) {
                throw new RunTimeException(['student_not_found']);
            }

            $packageInfo = DssErpPackageV1Model::getPackageById($params['package_id']);
            if (empty($packageInfo) || $packageInfo['package_status'] != DssErpPackageV1Model::STATUS_ON_SALE) {
                throw new RunTimeException(['package_not_available']);
            }
            $sceneData = [];
            if (!empty($params['channel_id'])) {
                $sceneData['c'] = $params['channel_id'];
            }
            $studentInfo['address_id'] = $params['address_id'] ?? true;
            $studentInfo['package_sub_type'] = $packageInfo['sub_type'];
            $employeeUuid = !empty($params['employee_id']) ? RC4::decrypt($_ENV['COOKIE_SECURITY_KEY'], $params['employee_id']) : null;
            $channel = Util::isWx() ? ErpPackageV1Model::CHANNEL_WX : ErpPackageV1Model::CHANNEL_H5;
            $payChannel = PayServices::payChannelToV1($params['pay_channel']);


            $userWeixin = DssUserWeiXinModel::getByUserId($studentId);
            $studentInfo['open_id'] = $userWeixin['open_id'] ?? null;
            $giftGoods = $params['gift_res'] ?? [];
            $payType = $params['pay_type'] ?? 1;
            $ret = ErpOrderV1Service::createOrder($params['package_id'], $studentInfo, $payChannel, $payType, $employeeUuid, $channel, $giftGoods);
            if (!empty($sceneData) && !empty($ret['order_id'])) {
                // 保存agent_bill_map数据
                BillMapService::mapDataRecord($sceneData, $ret['order_id'], $studentInfo['id']);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $ret);
    }

    /**
     * 支付结果查询
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function billStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'order_id',
                'type'       => 'required',
                'error_code' => 'order_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $erp = new Erp();
        $order = $erp->billStatusV1($params);
        $status = 0;
        if (!empty($order['data'])) {
            $status = $order['data']['order_status'];
        }
        return HttpHelper::buildResponse($response, ['order_status' => $status]);
    }
}
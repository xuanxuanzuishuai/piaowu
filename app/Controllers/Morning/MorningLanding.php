<?php

/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/03/12
 * Time: 10:41
 */

namespace App\Controllers\Morning;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\AreaService;
use App\Services\ErpOrderV1Service;
use App\Services\Morning\MorningLandingService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MorningLanding extends ControllerBase
{
    /**
     * 根据父级code获取列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getByParentCode(Request $request, Response $response)
    {

        $params = $request->getParams();
        if (empty($params['parent_code'])) {
            $parentCode = 100000;
        } else {
            $parentCode = $params['parent_code'];
        }

        $result = AreaService::getAreaByParentCode($parentCode);

        return HttpHelper::buildResponse($response, $result);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getByCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'code',
                'type'       => 'required',
                'error_code' => 'area_code_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $result = AreaService::getByCode($params['code']);
        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 保存收货地址并发货
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function saveAddressAndDelivery(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'student_name_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => Constants::MOBILE_REGEX,
                'error_code' => 'student_mobile_format_is_error'
            ],
            [
                'key'        => 'country_code',
                'type'       => 'required',
                'error_code' => 'country_code_is_required',
            ],
            [
                'key'        => 'province_code',
                'type'       => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key'        => 'city_code',
                'type'       => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key'        => 'district_code',
                'type'       => 'required',
                'error_code' => 'district_code_is_required'
            ],
            [
                'key'        => 'address',
                'type'       => 'required',
                'error_code' => 'student_address_is_required',
            ],
            [
                'key'        => 'is_default',
                'type'       => 'required',
                'error_code' => 'address_default_is_required',
            ],
            [
                'key'        => 'order_id',
                'type'       => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key'        => 'temporary_code',
                'type'       => 'required',
                'error_code' => 'temporary_code_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            //校验唯一码是否有效
            $temporaryCode = MorningLandingService::getTemporaryCode($params['uuid']);
            if ($temporaryCode != $params['temporary_code']) {
                throw new RunTimeException(['save_address_fail']);
            }
            //校验订单收货地址是否填写
            $orderRecord = ErpOrderV1Service::getOrderInfo($params['order_id']);
            if (empty($orderRecord['student_addr_id'])) {
                //保存收货地址
                $addressId = MorningLandingService::modifyAddress($params);
                //通知ERP发货
                MorningLandingService::updateOrderAddress($params['order_id'], $addressId);
                //移除临时码
                MorningLandingService::removeTemporaryCode($params['uuid']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 查询体验卡订单信息
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function trialOrderInfo(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required',
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = MorningLandingService::getOrderDetail($params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取临时码接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function temporaryCode(Request $request, Response $response)
    {
        $uuid = $this->ci['student_uuid'];
        $temporaryCode = MorningLandingService::genTemporaryCode($uuid);
        $data = [
            'uuid'           => $uuid,
            'temporary_code' => $temporaryCode,
        ];
        return HttpHelper::buildResponse($response, $data);
    }
}

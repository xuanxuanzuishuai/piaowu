<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/7/31
 * Time: 4:00 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\PayServices;
use App\Services\TrackService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class SaleShop extends ControllerBase
{
    /**
     * 积分商城列表
     */
    public function saleShopPackages(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);

        $platformId = TrackService::getPlatformId($this->ci['platform']);
        $packages = PayServices::getPackageV1List($platformId, $page, $count);

        return $response->withJson($packages, StatusCode::HTTP_OK);
    }

    /**
     * 积分商城详情
     */
    public function saleShopPackageDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'  => 'package_id',
                'type'  => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key' => 'package_id',
                'type' => 'integer',
                'error_code' => 'package_id_must_be_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $uuid = $this->ci['student']['uuid'];
        $platformId = TrackService::getPlatformId($this->ci['platform']);

        $detail = PayServices::getPackageV1Detail($params['package_id'], $platformId, $uuid);
        return $response->withJson($detail, StatusCode::HTTP_OK);
    }

    /**
     * 学生地址列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressList(Request $request, Response $response)
    {
        $uuid = $this->ci['student']['uuid'];

        $erp = new Erp();
        $result = $erp->getStudentAddressList($uuid);
        if (empty($result) || $result['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $result['errors'][0]['err_no'] ?? 'erp_request_error';
            return $response->withJson(Valid::addAppErrors([], $errorCode), StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'address_list' => $result['data']['list']
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * 添加、修改地址
     * @param Request $request
     * @param Response $response
     * @return null|Response
     */
    public function modifyAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'student_name_is_required',
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required',
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => Constants::MOBILE_REGEX,
                'error_code' => 'student_mobile_format_is_error'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required',
            ],
            [
                'key' => 'province_code',
                'type' => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key' => 'city_code',
                'type' => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key' => 'district_code',
                'type' => 'required',
                'error_code' => 'district_code_is_required'
            ],
            [
                'key' => 'address',
                'type' => 'required',
                'error_code' => 'student_address_is_required',
            ],
            [
                'key' => 'default',
                'type' => 'required',
                'error_code' => 'address_default_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['uuid'] = $this->ci['student']['uuid'];

        $erp = new Erp();
        $result = $erp->modifyStudentAddress($params);
        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 删除学生地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function deleteAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'address_id',
                'type' => 'required',
                'error_code' => 'student_address_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['uuid'] = $this->ci['student']['uuid'];

        $erp = new Erp();
        $result = $erp->deleteStudentAddress($params);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 兑换商品
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
                'key' => 'address_id',
                'type' => 'required',
                'error_code' => 'address_id_is_required',
            ],
            [
                'key' => 'address_id',
                'type' => 'integer',
                'error_code' => 'address_id_must_be_integer',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['uuid'] = $this->ci['student']['uuid'];

        $erp = new Erp();
        $result = $erp->createBillV1($params);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 积分订单列表
     */
    public function billList(Request $request, Response $response)
    {
        $params = $request->getParams();

        $params['uuid'] = $this->ci['student']['uuid'];

        $erp = new Erp();
        $result = $erp->billListV1($params);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 积分订单详情
     */
    public function billDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key' => 'order_id',
                'type' => 'integer',
                'error_code' => 'order_id_must_be_integer',
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $erp = new Erp();
        $detail = $erp->billDetailV1($params);
        return $response->withJson($detail, StatusCode::HTTP_OK);
    }

    /**
     * 物流信息
     */
    public function billLogistics(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key' => 'order_id',
                'type' => 'integer',
                'error_code' => 'order_id_must_be_integer',
            ],
            [
                'key' => 'logistics_no',
                'type' => 'required',
                'error_code' => 'logistic_no_is_required',
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $erp = new Erp();
        $result = $erp->logisticsV1($params);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }
}
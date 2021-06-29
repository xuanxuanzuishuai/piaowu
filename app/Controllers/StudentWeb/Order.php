<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021-03-08
 * Time: 14:39
 */

namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\KeyErrorRC4Exception;
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AgentModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpGiftGoodsV1Model;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\ParamMapModel;
use App\Services\BillMapService;
use App\Services\DssDictService;
use App\Services\ErpOrderV1Service;
use App\Services\ErpUserService;
use App\Services\MiniAppQrService;
use App\Services\PackageService;
use App\Services\PayServices;
use App\Services\ReferralActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Order extends ControllerBase
{
    /**
     * 产品包详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPackageDetail(Request $request, Response $response)
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
                'error_code' => 'package_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $student = $this->ci['user_info'] ?? [];
            $studentInfo = DssStudentModel::getById($student['user_id'] ?? 0);
            if (empty($studentInfo)) {
                SimpleLogger::error('STUDENT NOT FOUND IN CONTAINER', [$student]);
                throw new RunTimeException(['student_not_exist']);
            }

            $user['mobile'] = Util::hideUserMobile($studentInfo['mobile']);
            $user['uuid'] = $studentInfo['uuid'];

            $package = PackageService::getPackageV1Detail($params['package_id']);
            //判断产品包是否绑定赠品组
            $giftGroup = ErpOrderV1Service::haveBoundGiftGroup($params['package_id']);
            $package['has_gift_group'] = $giftGroup;
            $package['gift_group'] =  ErpGiftGoodsV1Model::getOnlineGroupGifts($params['package_id'], true);

            // 现金账户余额
            $user['cash'] = ErpUserService::getStudentCash($studentInfo['uuid']);
            $defaultAddress = ErpOrderV1Service::getStudentDefaultAddress($studentInfo['uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'package' => $package,
            'student' => $user,
            'default_address' => $defaultAddress,
            'share_info' => [
                'name' => $package['name'] ?? '',
                'desc' => $package['desc'] ?? '',
                'logo' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::AGENT_CONFIG, 'share_card_logo')),
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws KeyErrorRC4Exception
     */
    public function createOrder(Request $request, Response $response)
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
            $student = $this->ci['user_info'];
            $studentInfo = [];
            $channel = null;
            if (!empty($student['user_id'])) {
                $studentInfo = DssStudentModel::getById($student['user_id']);
                if (empty($studentInfo)) {
                    throw new RunTimeException(['student_not_found']);
                }
            }

            $packageInfo = DssErpPackageV1Model::getPackageById($params['package_id']);
            if (empty($packageInfo)
                || $packageInfo['package_status'] != DssErpPackageV1Model::STATUS_ON_SALE) {
                throw new RunTimeException(['package_not_available']);
            }

            $sceneData = [];
            if (!empty($params['param_id'])) {
                $sceneData = ReferralActivityService::getParamsInfo($params['param_id']);
                $sceneData['param_id'] = $sceneData['id'];
            }elseif(!empty($params['channel_id'])){
                $sceneData['c'] = $params['channel_id'];
            }
            // 检查购买人当前绑定的代理是否一致
            if (!empty($sceneData['user_id']) && $sceneData['type'] == ParamMapModel::TYPE_AGENT) {
                // 年卡不可购买体验包
                if ($studentInfo['has_review_course'] == DssStudentModel::REVIEW_COURSE_1980 && $packageInfo['sub_type'] == DssCategoryV1Model::DURATION_TYPE_TRAIL) {
                    SimpleLogger::error('STUDENT_DOWN_STAGE_NOT_ALLOWED', [$studentInfo, $packageInfo]);
                    throw new RunTimeException(['student_down_stage_not_allowed']);
                }
                $channel = ErpPackageV1Model::CHANNEL_OP_AGENT;
            }

            // check 9折续费 产品包
            // value是逗号分开的，如76,74
            $disPackageId = DssDictService::getKeyValue(DictConstants::DSS_PERSONAL_LINK_PACKAGE_ID, 'discount_package_id');
            $disPackageIds = explode(',', Util::trimAllSpace($disPackageId));
            if (in_array($params['package_id'], $disPackageIds)) {
                $isNormal = DssGiftCodeModel::hadPurchasePackageByType($studentInfo['id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL);
                if (empty($isNormal)) {
                    throw new RunTimeException(['only_renew_student_pay']);
                }
            }

            $studentInfo['open_id'] = $this->ci['open_id'] ?? null;
            $studentInfo['address_id'] = $params['address_id'] ?? true;
            $studentInfo['package_sub_type'] = $packageInfo['sub_type'];
            $employeeUuid = !empty($params['employee_id']) ? RC4::decrypt($_ENV['COOKIE_SECURITY_KEY'], $params['employee_id']) : null;
            if (is_null($channel)) {
                $channel = Util::isWx() ? ErpPackageV1Model::CHANNEL_WX : ErpPackageV1Model::CHANNEL_H5;
            }
            $payChannel = PayServices::payChannelToV1($params['pay_channel']);
            if ($payChannel == PayServices::PAY_CHANNEL_V1_WEIXIN
            && empty($studentInfo['open_id'])) {
                $userWeixin = DssUserWeiXinModel::getByUserId($student['user_id']);
                if (!empty($userWeixin['open_id'])) {
                    $studentInfo['open_id'] = $userWeixin['open_id'];
                }
            }

            //AIPL-10499 专属售卖链接支持选择赠品
            if (isset($params['gift_res'])
                && empty($params['gift_res'])
                && ErpOrderV1Service::haveBoundGiftGroup($params['package_id'])
            ) {
                throw new RunTimeException(['must_bind_gift_group']);
            }

            $ret = ErpOrderV1Service::createOrder($params['package_id'], $studentInfo, $payChannel, $params['pay_type'], $employeeUuid, $channel, $params['gift_res']);
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
     * 获取订单状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orderStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
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

    /**
     * 获取产品包中包含的赠品组商品信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getGiftGroupGoods(Request $request, Response $response)
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
                'error_code' => 'package_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //产品包包含的赠品组
        $groupGifts = ErpGiftGoodsV1Model::getOnlineGroupGifts($params['package_id'], true);

        return HttpHelper::buildResponse($response, ['gift_group_goods' => $groupGifts]);
    }

    /**
     * 根据订单ID获取产品或赠品中的实物商品和邮寄地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkObjectsAndAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            //校验是否包含实物
            $erp = new Erp();
            $res = $erp->checkPackageHaveKind($params);
            if (!empty($res['code'])) {
                SimpleLogger::error('ERP REQUEST ERROR', [$res]);
                throw new RunTimeException(['request_error']);
            }
            $orderInfo = ErpOrderV1Service::getOrderInfo($params['order_id']);
            $res['data']['have_address'] = $orderInfo['student_addr_id'] ? true : false;
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, $res['data']);
    }

    /**
     * 更新订单发货地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateOrderAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key' => 'address_id',
                'type' => 'required',
                'error_code' => 'address_id_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $orderInfo = ErpOrderV1Service::getOrderInfo($params['order_id']);
            if (empty($orderInfo)) {
                throw new RunTimeException(['order_not_exist']);
            }
            if ($orderInfo['student_addr_id']) {
                throw new RunTimeException(['order_address_exist']);
            }

            $erp = new Erp();
            $res = $erp->updateOrderAddress($params);
            if (!empty($res['code'])) {
                SimpleLogger::error('ERP REQUEST ERROR', [$res]);
                throw new RunTimeException(['request_error']);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 支付成功页面
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function paySuccess(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $student = $this->ci['user_info'];
            $paramInfo = [];
            if (!empty($params['param_id'])) {
                $paramInfo = ReferralActivityService::getParamsInfo($params['param_id']);
            }
            $agent = null;
            if (!empty($paramInfo['r'])
            && stripos($paramInfo['r'], MiniAppQrService::AGENT_TICKET_PREFIX) !== false) {
                $agent = AgentModel::getById($paramInfo['user_id']);
                if (!empty($agent['parent_id'])) {
                    $agent = AgentModel::getById($agent['parent_id']);
                }
            }
            $qrCode = DictConstants::get(DictConstants::AGENT_CONFIG, 'ai_wx_official_account_qr_code');
            $qrCodeUrl = AliOSS::replaceCdnDomainForDss($qrCode);
            if (!empty($params['type']) && $params['type'] == DssCategoryV1Model::DURATION_TYPE_NORMAL) {
                $assistantInfo = DssStudentModel::getAssistantInfo($student['user_id'], false);
            } else {
                $assistantInfo = DssStudentModel::getAssistantInfo($student['user_id']);
                if (empty($assistantInfo['wx_qr'])) {
                    $studentInfo = DssStudentModel::getById($student['user_id']);
                    $collectionInfo = DssCollectionModel::getById($studentInfo['collection_id']);
                    if (!empty($collectionInfo['wechat_qr'])) {
                        $assistantInfo['wx_qr'] = AliOSS::signUrls($collectionInfo['wechat_qr']);
                    }
                }
            }
            if (!is_null($agent)) {
                $defaultNickName = '小叶子老师';
                $defaultThumb = AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::AGENT_CONFIG, 'default_thumb'));
                $assistantInfo['wx_nick'] = $assistantInfo['wx_nick'] ?: $defaultNickName;
                $assistantInfo['wx_thumb'] = $assistantInfo['wx_thumb'] ?: $defaultThumb;
            }
            $data = array_merge([
                'model' => $agent['division_model'] ?? 0,
                'ai_qr_url' => $qrCodeUrl,
            ], $assistantInfo);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}

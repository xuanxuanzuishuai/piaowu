<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/3/5
 * Time: 5:59 下午
 */

namespace App\Controllers\ShowMiniApp;


use App\Controllers\ControllerBase;
use App\Libs\Dss;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\CommonServiceForApp;
use App\Services\PayServices;
use App\Services\ShowMiniAppService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Landing extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 练琴测评3.0落地页
     */
    public function playReview(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $sceneData = ShowMiniAppService::getSceneData($params['scene'] ?? '');
            $pageData = ShowMiniAppService::getMiniAppPlayReviewData($sceneData, $this->ci['open_id']);
            $pageData['share_scene'] = urlencode($params['scene']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 国际区号列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCountryCode(Request $request, Response $response)
    {
        $countryCode = CommonServiceForApp::getCountryCode();
        // 热门国际区号 + 全部区号国家名字母序
        $hot = [];
        $list = [];
        array_walk($countryCode, function ($item) use (&$hot, &$list) {
            if ($item['hot'] > 0) {
                $hot['hot'][] = $item;
            }
            $u = strtoupper(substr($item['pinyin'], 0, 1));
            if (!isset($list[$u])) {
                $list[$u] = [];
            }
            $list[$u][] = $item;
        });
        usort($hot['hot'], function ($a, $b) {
            return $a['hot'] > $b['hot'];
        });
        return HttpHelper::buildResponse($response, array_merge($hot, $list));
    }

    /**
     * 发送注册验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendSmsCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'], CommonServiceForApp::SIGN_WX_STUDENT_APP, $params['country_code']);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * 注册
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function register(Request $request, Response $response)
    {
        $params = $request->getParams();
        if (isset($params['encrypted_data'])) {
            $rules = [
                [
                    'key'        => 'iv',
                    'type'       => 'required',
                    'error_code' => 'iv_is_required'
                ],
                [
                    'key'        => 'encrypted_data',
                    'type'       => 'required',
                    'error_code' => 'encrypted_data_is_required'
                ],
            ];
        } else {
            $rules = [
                [
                    'key'        => 'mobile',
                    'type'       => 'required',
                    'error_code' => 'mobile_is_required'
                ],
                [
                    'key'        => 'sms_code',
                    'type'       => 'required',
                    'error_code' => 'validate_code_error'
                ]
            ];
        }
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"], $params["country_code"] ?? '')) {
                return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
            }
            $sceneData = ShowMiniAppService::getSceneData($params['scene'] ?? '');
            $sessionKeyOpenId = $this->ci['open_id'];
            list($openid, $lastId, $mobile, $uuid, $hadPurchased) = ShowMiniAppService::remoteRegister(
                $this->ci['open_id'],
                $params['iv'] ?? '',
                $params['encrypted_data'] ?? '',
                $sessionKeyOpenId,
                $params['mobile'] ?? '',
                $params['country_code'] ?? '',
                $sceneData['r'] ?? '', // referrer ticket
                $sceneData['c'] ?? '', // channel id
                $sceneData
            );
            //获取分享scene
            $shareScene = $sceneData;
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['openid' => $openid, 'last_id' => $lastId, 'mobile' => $mobile, 'uuid' => $uuid, 'had_purchased' => $hadPurchased, 'share_scene' => $shareScene]);
    }

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
            ]
        ];
        $params = $request->getParams();
        $params['ping_app_id'] = $request->getParam('ping_app_id', '4');
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = (new Dss())->createBill($params);
        return HttpHelper::buildResponse($response, $data);
    }

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

        return $response->withJson([
            'code' => 0,
            'data' => [
                'bill_status' => $status
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 转介绍Landing小程序消息
     * @param Request $request
     * @param Response $response
     * @return int
     */
    public function notify(Request $request, Response $response)
    {
        $params = $request->getParams();
        $postData = file_get_contents('php://input');
        return $response->getBody()->write(ShowMiniAppService::miniAppNotify($params, $postData));
    }

}
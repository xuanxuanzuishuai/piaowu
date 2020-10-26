<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/20
 * Time: 10:15
 */

namespace App\Controllers\ReferralMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\UserQrTicketModel;
use App\Services\CommonServiceForApp;
use App\Services\ReferralService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Landing extends ControllerBase
{

    /**
     * 转介绍小程序Landing页
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function index(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $scene = $params['scene'] ?? '';
            if (!empty($scene)) {
                parse_str(urldecode($scene), $sceneData);
            } else {
                $sceneData = [];
            }
            $pageData = ReferralService::getLandingPageData($sceneData['r'] ?? '', $this->ci['referral_landing_openid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
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
        return $response->getBody()->write(ReferralService::miniAppNotify($params, $postData));
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
            return $a['hot'] < $b['hot'];
        });
        return HttpHelper::buildResponse($response, array_merge($hot, $list));
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
        if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"], $params["country_code"] ?? '')) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        list($openid, $lastId, $mobile, $uuid, $hadPurchased) = ReferralService::register(
            $this->ci['referral_landing_openid'],
            $params['iv'] ?? '',
            $params['encrypted_data'] ?? '',
            $this->ci['referral_landing_session_key'],
            $params['mobile'] ?? '',
            $params['country_code'] ?? '',
            $params['referrer'] ?? ''
        );
        return HttpHelper::buildResponse($response, ['openid' => $openid, 'last_id' => $lastId, 'mobile' => $mobile, 'uuid' => $uuid, 'had_purchased' => $hadPurchased]);
    }
}

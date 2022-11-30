<?php


namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\BAApplyModel;
use App\Services\BAService;
use App\Services\ShopService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Services\WechatTokenService;
use App\Libs\Valid;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Wx extends ControllerBase
{

    public function login(Request $request, Response $response)
    {
        $old_token = $this->ci["token"];
        if (!empty($old_token)){
            WechatTokenService::deleteToken($old_token);
        }

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }


        $boundInfo = BAApplyModel::getRecord(['open_id' => $openId]);


        // 没有找到该openid的绑定关系
        if (empty($boundInfo)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WechatTokenService::generateToken($boundInfo['ba_id'],$openId);


        return HttpHelper::buildResponse($response, [
            'token' => $token,
            'ba_id' => $boundInfo['ba_id']
        ]);
    }


    /**
     * 门店列表
     * @param Request $request
     * @param Response $response
     * @return Response|static
     */
    public function shopList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {

            list($page, $count) = Util::formatPageCount($params);
            list($list, $totalCount) = ShopService::getShopList($params, $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'shop_list' => $list,
            'total_count' => $totalCount
        ], StatusCode::HTTP_OK);
    }


    /**
     * 上报BA申请
     * @param Request $request
     * @param Response $response
     * @return Response|static
     */
    public function apply(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'job_number',
                'type' => 'required',
                'error_code' => 'job_number_is_required'
            ],
            [
                'key' => 'idcard',
                'type' => 'required',
                'error_code' => 'idcard_is_required'
            ],
            [
                'key' => 'wx_code',
                'type' => 'required',
                'error_code' => 'wc_code_is_required'
            ],
            [
                'key' => 'shop_id',
                'type' => 'required',
                'error_code' => 'shop_id_is_required'
            ]

        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $oldToken = $request->getHeader('token');
        $oldToken = $oldToken[0] ?? null;
        if (!empty($oldToken)) {
            WechatTokenService::deleteToken($oldToken);
        }

        try {

            $res = Util::validPhoneNumber($params['mobile'], 86);

            if (empty($res)) {
                throw new RunTimeException(['mobile_format_not_right']);
            }
            

            $data = WeChatMiniPro::factory()->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);

            if (empty($data) || empty($data['openid'])) {
                throw new RunTimeException(['can_not_obtain_open_id']);
            }

            $openId = $data['openid'];


            $info = BAService::addApply($openId, $params);

            $token = WechatTokenService::generateToken($info['id'], $openId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, ['token' => $token]);
    }

    /**
     * 申请信息
     * @param Request $request
     * @param Response $response
     * @return Response|static
     */
    public function applyInfo(Request $request, Response $response)
    {

        try {
            $baInfo = $this->ci['ba_info'];

            $info = BAService::getBaApplyInfo($baInfo);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'info' => $info
        ], StatusCode::HTTP_OK);
    }
}
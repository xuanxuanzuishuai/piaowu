<?php


namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Models\BaWeixinModel;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Services\WechatTokenService;
use App\Libs\Valid;
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


        $boundInfo = BaWeixinModel::getRecord(['open_id' => $openId, 'status' => BaWeixinModel::STATUS_NORMAL]);


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

}
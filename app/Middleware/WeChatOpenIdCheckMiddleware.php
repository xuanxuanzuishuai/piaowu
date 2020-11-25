<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 17:17
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/** 检查token，如果token无效则根据code获取openid
 * Class WeChatOpenIdCheckMiddleware
 * @package App\Middleware
 */
class WeChatOpenIdCheckMiddleware extends MiddlewareBase
{
    // 在此数组中的url，如果带了code就检查，没有也不会报错
    public static $ignoreCheckCodeUrlList = [
        "/teacher_wx/teacher/register",
        "/student_wx/student/register",
        "/classroom_teacher_wx/teacher/register",
    ];

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--WeChatAuthCheckMiddleware--', []);
        $appId = $request->getHeader('app-id')[0] ?? NULL;
        if (empty($appId)) {
            return $response->withJson(Valid::addAppErrors([], 'need_app_id'), StatusCode::HTTP_OK);
        }
        $this->container['app_id'] = $appId;

        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;
        $this->container["token"] = $token;

        $currentUrl = $request->getUri()->getPath();
        $arr = [
            '/student_wx' => Constants::USER_TYPE_STUDENT
        ];
        $userType = $arr[$this->getURLPrefix($currentUrl)] ?? '';
        if (empty($userType)) {
            return $response->withJson(Valid::addAppErrors([], 'request_error'), StatusCode::HTTP_OK);
        }

        $checkResult = $this::checkNeedWeChatCode($request, $appId, $userType);
        // 是否要跳转到微信端获取用户code
        $needWeChatCode = $checkResult["needWeChatCode"];
        $openId = $checkResult["openId"] ?? '';
        SimpleLogger::info("checkWeChatCode", ["open_id" => $openId, "need" => $needWeChatCode, "user_type" => $userType]);

        // 本地环境方便调试
        if ($_ENV['DEBUG_WEIXIN_SKIP_MIDDLEWARE'] == "1" and !empty($request->getParam("test_open_id"))) {
            $needWeChatCode = false;
            $openId = $request->getParam("test_open_id");
        }

        $this->container['open_id'] = $openId;
        // 需要获取微信code
        if ($needWeChatCode and !in_array($currentUrl, self::$ignoreCheckCodeUrlList)) {
            SimpleLogger::info('need weixin code:', []);
            return $response->withJson(Valid::addAppErrors([], 'need_code'), StatusCode::HTTP_OK);
        }

        $response = $next($request, $response);
        return $response;
    }
    
    public function checkNeedWeChatCode(Request $request, $app_id, $user_type) {
        $openId = null;
        $needWeChatCode = false;
        $code = $request->getParam('wx_code');
        // 已经获取微信的用户code，通过微信API获取openid
        if (!empty($code)) {
            //当前系统对应的应用busi_type
            $arr = [
                Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
            ];
            $busiType = $arr[$app_id] ?? Constants::SMART_WX_SERVICE;
            $data = WeChatMiniPro::factory($app_id, $busiType)->getWeixnUserOpenIDAndAccessTokenByCode($code);
            SimpleLogger::info("getWeixnUserOpenIDAndAccessTokenByCode", ["data" => $data]);
            if (!empty($data['openid'])) {
                    $openId = $data['openid'];
            } else {
                // 获取用户open id失败, 需要重新获取code换取open id
                $needWeChatCode = true;
            }
        } else {
            // 当前没有code
            $needWeChatCode = true;
        }
        return ["needWeChatCode" => $needWeChatCode, "openId" => $openId];
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 17:17
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;
use App\Services\WeChatService;

/** 检查token，如果token无效则根据code获取openid
 * Class WeChatOpenIdCheckMiddleware
 * @package App\Middleware
 */
class WeChatOpenIdCheckMiddleware extends MiddlewareBase
{
    // 在此数组中的url，如果带了code就检查，没有也不会报错
    public static $ignoreCheckCodeUrlList = [
        "/teacher_wx/teacher/register",
        "/student_wx/student/register"
    ];

    public function __invoke(Request $request, Response $response, $next)
    {
        $currentUrl = $request->getUri()->getPath();
        SimpleLogger::info('--WeChatAuthCheckMiddleware--', []);
        $needle = "/student_wx";
        $length = strlen($needle);
        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER;
        $user_type = 2;
        if (substr($currentUrl, 0, $length) === $needle){
            $user_type = 1;
            $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        }
        $checkResult = $this::checkNeedWeChatCode($request, $app_id, $user_type);
        // 是否要跳转到微信端获取用户code
        $needWeChatCode = $checkResult["needWeChatCode"];
        $openId = $checkResult["openId"];
        SimpleLogger::info("checkWeChatCode", ["open_id" => $openId, "need" => $needWeChatCode, "user_type" => $user_type]);

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
            $data = WeChatService::getWeixnUserOpenIDAndAccessTokenByCode($code, $app_id, $user_type);
            SimpleLogger::info("getWeixnUserOpenIDAndAccessTokenByCode", ["data" => $data]);
            if (!empty($data)) {
                if (!empty($data['openid'])) {
                    $openId = $data['openid'];
                } else {
                    $needWeChatCode = true;
                }
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

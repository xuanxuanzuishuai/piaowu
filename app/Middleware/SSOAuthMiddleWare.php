<?php
/*
 * @Date: 2022-03-01
 * @LastEditors:
 * @LastEditTime: 2022-03-01
 */


namespace App\Middleware;

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class SSOAuthMiddleWare
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $pathStr = $request->getUri()->getPath();
        $pathStr = preg_replace('/\/[0-9]+/', '/', $pathStr);
        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_op', 'app_secret_op']);

        if ($pathStr == "/sso/logout") {
            $uc = new \App\Libs\UserCenter($appId, $appSecret);
            //用户中心退出
            $token = $request->getCookieParam("token");
            //如果取到service ticket说明是uc统一注销请求，这时cookie里取不到token，把service ticket转成token
            $sso_service_ticket = $request->getParam("sso_service_ticket");
            if(!empty($sso_service_ticket)) {
                $token = base64_encode($sso_service_ticket);
            }

            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["token" => $token, "pathStr" => "sso/logout", "sso_service_ticket" => $sso_service_ticket]);
            //当前系统logout
            $cookie = "token={$token}; max-age=-1; path=/;";
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
            EmployeeModel::delEmployeeToken($token);
            $loginurl = $uc->GetSSOLogoutUrl();
            return $response->withRedirect($loginurl, 302);

        } else if ($pathStr == "/sso/login"){
            $serviceTicket = $request->getQueryParams()['sso_service_ticket'];
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["serviceTicket" => $serviceTicket, "pathStr" => "sso/login"]);
            if (empty($serviceTicket)) {
                $response = $next($request, $response);
                return $response->withJson([
                    'code' => Valid::CODE_PARAMS_ERROR,
                    'data' => ["sso_service_ticket" => ""]
                ], StatusCode::HTTP_OK);
            }
            //用户中心验证service ticket ， 成功后拿到用户信息生成ERP的token， 设置到 cookie 里 。 
            //然后在 EmployeeAuthCheckMiddleWare 获取COOKIE验证登录状态即可完成登录验证 
            $uc = new \App\Libs\UserCenter($appId, $appSecret);
            $ucData = $uc->VerifyServiceTicket($serviceTicket);
            if (!empty($ucData) && $ucData['code'] == 0) {
                $token = base64_encode($serviceTicket);
                $expires = $ucData['data']['expires'];
                $userInfo = EmployeeModel::getByUuid($ucData['data']['user']['uuid']);

                EmployeeModel::setEmployeeCache($userInfo, $token,  $expires);
                // 更新用户上次登录时间
                EmployeeModel::updateEmployee($userInfo['id'], ['last_login_time' => time()]);

                //设置cookie
                $cookie = "token={$token}; max-age={$expires}; path=/;";
                $response = $response->withAddedHeader('Set-Cookie', $cookie);
                SimpleLogger::info(__FILE__ . ':' . __LINE__, ["token" => $token, "userInfo" => $userInfo, "cookie" => $cookie, "serviceTicket" => $serviceTicket, "pathStr" => "sso/login"]);
                return $response->withJson([
                    'code' => Valid::CODE_SUCCESS,
                    'data' => [
                        'token' => $token,
                        'expires' => $expires,
                        'user' => $userInfo,
                        'wsinfo' => []
                    ]
                ], StatusCode::HTTP_OK);
                
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, ["serviceTicket" => $serviceTicket, "err" => "sso/login 认证失败，重新登录"]);
                //认证失败，重新登录 
                // $loginurl = $uc->GetSSOLoginUrl();
                $cookie = "token=; max-age=-1; path=/;";
                $response = $response->withAddedHeader('Set-Cookie', $cookie);
                return $response->withJson([
                    'code' => Valid::CODE_UNAUTHOR,
                    'data' => ["token" => ""]
                ], StatusCode::HTTP_OK);
            }
        }
        
        $response = $next($request, $response);

        return $response;

    }
}
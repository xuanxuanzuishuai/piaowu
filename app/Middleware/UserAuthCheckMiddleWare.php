<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\UserModel;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class UserAuthCheckMiddleWare
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
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;

        if (empty($token)) {
            if(isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev') {
                $userID = $_ENV['DEV_USER_ID'];
            } else {
                SimpleLogger::error(__FILE__ . __LINE__, ['empty token']);
                $result = Valid::addAppErrors([], 'empty_token');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } else {
            $userID = UserModel::getUserUid($token);
        }

        if (empty($userID)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid token']);
            $result = Valid::addAppErrors([], 'invalid_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $user = UserModel::getUserInfo($userID, null);

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'UserAuthCheckMiddleWare',
            'user' => $user
        ]);

        // 延长登录token过期时间
        UserModel::refreshUserToken($userID);

        $this->container['user'] = $user;

        // 内部审核账号，使用审核版本app也可看到所有资源
        $reviewTestUsers = AppConfigModel::get('REVIEW_TESTER');
        if (!empty($reviewTestUsers)) {
            $userMobiles = explode(',', $reviewTestUsers);
            $this->container['tester'] = in_array($user['mobile'], $userMobiles);
        }

        $response = $next($request, $response);

        return $response;

    }
}
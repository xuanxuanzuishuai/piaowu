<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/24
 * Time: 5:51 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\OrganizationModelForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ClassroomAuthMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token = $this->container['org_token'] ?? NULL;
        if (empty($token)) {
            if (isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev') {
                $orgId = $_ENV['DEV_ORG_ID'];
            } else {
                SimpleLogger::error(__FILE__ . __LINE__, ['empty org token']);
                $result = Valid::addAppErrors([], 'empty_org_token');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } else {
            $cache = OrganizationModelForApp::getOrgCacheByToken($token);
            $orgId = $cache['org_id'];
        }

        if (empty($orgId)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid org token']);
            $result = Valid::addAppErrors([], 'invalid_org_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $org = OrganizationModelForApp::getById($orgId);

        // 延长登录token过期时间
        OrganizationModelForApp::refreshOrgToken($token);

        $this->container['org'] = $org;
        $this->container['org_account'] = $cache['account'] ?? '';

        $this->container['opn_pro_ver'] = $this->container['version'];
        $this->container['opn_publish'] = 1;

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'ClassroomAuthMiddleWare',
            'org' => $this->container['org'],
            'org_account' => $this->container['org_account'],
            'opn_pro_ver' => $this->container['opn_pro_ver'],
            'opn_publish' => $this->container['opn_publish'],
        ]);

        $response = $next($request, $response);

        return $response;
    }
}
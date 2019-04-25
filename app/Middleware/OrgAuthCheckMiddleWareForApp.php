<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/25
 * Time: 1:49 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\OrganizationModelForApp;
use App\Services\AppVersionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OrgAuthCheckMiddleWareForApp extends MiddlewareBase
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

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'OrgAuthCheckMiddleWareForApp',
            'org' => $org
        ]);

        // 延长登录token过期时间
        OrganizationModelForApp::refreshOrgToken($token);

        $this->container['org'] = $org;
        $this->container['org_account'] = $cache['account'] ?? '';

        $reviewVersion = AppConfigModel::get(AppConfigModel::REVIEW_VERSION_FOR_TEACHER_APP);
        $isReviewVersion = ($this->container['platform'] == AppVersionService::PLAT_IOS) && ($reviewVersion == $this->container['version']);
        $this->container['is_review_version'] = $isReviewVersion;

        $this->container['opn_is_tester'] = false;
        $this->container['opn_auditing'] = $isReviewVersion ? 1 : 0;
        $this->container['opn_publish'] = 1;

        $response = $next($request, $response);

        return $response;
    }
}
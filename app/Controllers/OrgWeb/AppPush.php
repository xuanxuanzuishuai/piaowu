<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/1/18
 * Time: 11:13 上午
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\DictService;
use App\Services\PushServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AppPush extends ControllerBase
{
    public function push(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'push_user_type',
                'type' => 'required',
                'error_code' => 'push_user_type_is_required'
            ],
            [
                'key' => 'push_title',
                'type' => 'required',
                'error_code' => 'push_title_is_required'
            ],
            [
                'key' => 'push_content',
                'type' => 'required',
                'error_code' => 'push_content_is_required'
            ],
            [
                'key' => 'jump_type',
                'type' => 'required',
                'error_code' => 'jump_type_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['file_name'] = $_FILES['file_name'];

        try {
            PushServices::push($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 推送列表
     */
    public function pushList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'],$params['limit']) = Util::formatPageCount($params);
        list($list, $totalCount) = PushServices::pushList($params);
        return HttpHelper::buildResponse($response, [
            'rules'       => $list,
            'total_count' => $totalCount
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 下载推送指定用户信息模板
     */
    public function downloadPushUserTemplate(Request $request, Response $response)
    {
        $url = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'push_user_template');
        $ossUrl = AliOSS::replaceCdnDomainForDss($url);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $ossUrl]
        ]);
    }
}
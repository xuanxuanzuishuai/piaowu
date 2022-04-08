<?php
/**
 * 渠道线索录入
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022-04-08
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\TemplatePosterModel;
use App\Services\AbroadLaunchService;
use App\Services\PosterTemplateService;
use I18N\Lang;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ChannelLeads extends ControllerBase
{
    /**
     * 海报文案添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ]

        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $employeeId = $this->getEmployeeId();
            $appId = $params['app_id'] ?? Constants::REAL_APP_ID;
            AbroadLaunchService::ChannelSaveLeads($appId, $employeeId, $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 海报文案列表
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        $appId = $params['app_id'] ?? Constants::REAL_APP_ID;
        list($params['page'], $params['count']) = Util::appPageLimit($params);
        $data= AbroadLaunchService::getList($appId, $params);
        return HttpHelper::buildResponse($response, $data);
    }
}

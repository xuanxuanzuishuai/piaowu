<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/21
 * Time: 6:19 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Services\PersonalLinkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class PersonalLink extends ControllerBase
{
    /**
     * 专属售卖链接的相关package列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $packageList = PersonalLinkService::getPackages();

        return HttpHelper::buildResponse($response, [
            'list' => $packageList,
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 生成专属链接
     */
    public function create(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = $this->ci['employee']['id'];
        $url = PersonalLinkService::createPersonalLink($employeeId, $params['package_id']);
        $link = WeChatMiniPro::factory([
            'app_id' => $_ENV['STUDENT_WEIXIN_APP_ID'],
            'app_secret' => $_ENV['STUDENT_WEIXIN_APP_SECRET'],
        ])->getShortUrl($url);
        empty($link) && $link = $url;
        return HttpHelper::buildResponse($response, [
            'link' => $link,
        ]);
    }
}
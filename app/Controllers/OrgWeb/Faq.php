<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/26
 * Time: 6:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;
use App\Services\FaqService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Faq extends ControllerBase
{
    /**
     * 添加话术
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'title',
                'type' => 'required',
                'error_code' => 'faq_title_is_required'
            ],
            [
                'key' => 'desc',
                'type' => 'required',
                'error_code' => 'faq_desc_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        //写入数据
        try {
            FaqService::addFaq($params['title'], $params['desc'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 修改话术
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function modify(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'title',
                'type' => 'required',
                'error_code' => 'faq_title_is_required'
            ],
            [
                'key' => 'desc',
                'type' => 'required',
                'error_code' => 'faq_desc_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        try {
            FaqService::modifyFaq($params['id'], $params['title'], $params['desc'], $params['status'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 话术详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $detail = FaqService::faqDetail($params['id']);
        //返回数据
        return HttpHelper::buildResponse($response, $detail);
    }


    /**
     * 数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        //获取数据
        $list = FaqService::faqList($params, $params['page'], $params['count']);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 关键字搜索
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function search(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'key',
                'type' => 'required',
                'error_code' => 'keyword_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $res = FaqService::searchFaqByELK($params['key']);
        return HttpHelper::buildResponse($response, $res);

    }
}
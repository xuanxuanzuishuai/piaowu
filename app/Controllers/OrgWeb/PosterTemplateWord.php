<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/06/10
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\TemplatePosterModel;
use App\Services\PosterTemplateService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class PosterTemplateWord extends ControllerBase
{
    /**
     * 海报文案添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addWord(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'content_is_required'
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
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{
            //参数校验
            $employeeId = $this->getEmployeeId();
            $params['type'] = TemplatePosterModel::INDIVIDUALITY_POSTER;
            PosterTemplateService::addWordData($params, $employeeId);
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
    public function wordList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($list, $pageId, $pageLimit, $totalCount) = PosterTemplateService::getWordList($params);
        return HttpHelper::buildResponse($response, [
            'data' => $list,
            'page_id' => $pageId,
            'page_limit' => $pageLimit,
            'total_count' => $totalCount
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取某条模板文案信息
     */
    public function getWordInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_word_id',
                'type' => 'required',
                'error_code' => 'poster_word_id_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $info = PosterTemplateService::getOnePosterWordInfo($params['poster_word_id']);
        return HttpHelper::buildResponse($response, [
            'data' => $info
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 更新海报模板文案内容
     */
    public function editWordInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_word_id',
                'type' => 'required',
                'error_code' => 'poster_word_id_is_required'
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
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = $this->getEmployeeId();
        $info = PosterTemplateService::editWordData($params, $employeeId);
        return HttpHelper::buildResponse($response, [
            'data' => $info
        ]);
    }
}
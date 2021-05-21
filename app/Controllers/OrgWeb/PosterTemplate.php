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
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\TemplatePosterModel;
use App\Services\PosterTemplateService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class PosterTemplate extends ControllerBase
{
    /**
     * 个性化海报添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function individualityAdd(Request $request, Response $response)
    {
        //接收数据
        $rules = [
	        [
		        'key' => 'name',
		        'type' => 'required',
		        'error_code' => 'name_is_required'
	        ],
            [
                'key' => 'name',
                'type' => 'lengthBetween',
                'value' => 1,
                'flag' => 10,
                'error_code' => 'name_length_between_1_10'
            ],
            [
                'key' => 'poster_path',
                'type' => 'required',
                'error_code' => 'poster_path_is_required'
            ],
            [
	            'key' => 'example_path',
	            'type' => 'required',
	            'error_code' => 'example_path_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key' => 'order_num',
                'type' => 'required',
                'error_code' => 'order_num_between_1_10'
            ],
            [
	            'key' => 'order_num',
	            'type' => 'min',
	            'value' => 1,
	            'error_code' => 'order_num_between_1_100'
	        ],
            [
	            'key' => 'order_num',
	            'type' => 'max',
	            'value' => 100,
	            'error_code' => 'order_num_between_1_100'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
	    $params['name'] = Util::filterEmoji($params['name'] ?? '');
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{
            //参数校验
            //$params['name'] = Util::filterEmoji($params['name']);
            //if (mb_strlen($params['name']) > 10) {
            //    throw new RunTimeException(['name_less_10']);
            //}
            //if (intval($params['order_num']) > 100 or intval($params['order_num']) <= 0) {
            //    throw new RunTimeException(['order_num_must_than_0_and_less_100']);
            //}
            $employeeId = $this->getEmployeeId();
            $params['type'] = TemplatePosterModel::INDIVIDUALITY_POSTER;
            PosterTemplateService::addData($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 标准化海报添加
     */
    public function standardAdd(Request $request, Response $response)
    {
        //接收数据
        $rules = [
	        [
		        'key' => 'name',
		        'type' => 'required',
		        'error_code' => 'name_is_required'
	        ],
	        [
		        'key' => 'name',
		        'type' => 'lengthBetween',
		        'value' => 1,
		        'flag' => 10,
		        'error_code' => 'name_length_between_1_10'
	        ],
            [
                'key' => 'poster_path',
                'type' => 'required',
                'error_code' => 'poster_path_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ],
	        [
		        'key' => 'order_num',
		        'type' => 'min',
		        'value' => 1,
		        'error_code' => 'order_num_between_1_100'
	        ],
	        [
		        'key' => 'order_num',
		        'type' => 'max',
		        'value' => 100,
		        'error_code' => 'order_num_between_1_100'
	        ]
        ];
        //验证合法性
        $params = $request->getParams();
	    $params['name'] = Util::filterEmoji($params['name'] ?? '');
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{
            //参数校验
            //$params['name'] = Util::filterEmoji($params['name']);
            //if (mb_strlen($params['poster_name']) > 10) {
            //    throw new RunTimeException(['poster_name_less_10']);
            //}
            //if (intval($params['order_num']) > 100 or intval($params['order_num']) <= 0) {
            //    throw new RunTimeException(['order_num_must_than_0_and_less_100']);
            //}
            $employeeId = $this->getEmployeeId();
            $params['type'] = TemplatePosterModel::STANDARD_POSTER;
            $params['example_path'] = $params['example_path'] ?? '';
            PosterTemplateService::addData($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 个性化海报列表
     */
    public function individualityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['type'] = TemplatePosterModel::INDIVIDUALITY_POSTER;
        list($list, $pageId, $pageLimit, $totalCount) = PosterTemplateService::getList($params);
        return HttpHelper::buildResponse($response,[
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
     * 标准化海报列表
     */
    public function standardList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['type'] = TemplatePosterModel::STANDARD_POSTER;
        list($list, $pageId, $pageLimit, $totalCount) = PosterTemplateService::getList($params);
        return HttpHelper::buildResponse($response, [
            'data' => $list,
            //'page_id' => $pageId,
            //'page_limit' => $pageLimit,
            'total_count' => $totalCount
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 某个模板海报的信息
     */
    public function getPosterInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $info = PosterTemplateService::getOnePosterInfo($params['id']);
        return HttpHelper::buildResponse($response, [
            'data' => $info
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 编辑海报模板信息
     */
    public function editPosterInfo(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'id',
                    'type' => 'required',
                    'error_code' => 'id_is_required'
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
            $info = PosterTemplateService::editData($params, $employeeId);
            return HttpHelper::buildResponse($response, [
                'data' => $info
            ]);
        }catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
    }
}
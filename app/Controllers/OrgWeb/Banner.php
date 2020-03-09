<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2020/03/06
 * Time: 10:00 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\BannerService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Banner extends ControllerBase
{
    /**
     * Banner 数据列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $res = BannerService::getList($params, $page, $count);
        //获取数据
        return $response->withJson($res, StatusCode::HTTP_OK);
    }

    /**
     * 获取banner详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //获取数据
        $res = BannerService::getDetail($params['id']);
        return $response->withJson($res, StatusCode::HTTP_OK);
    }

    /**
     * 添加banner
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required',
            ],
            [
                'key'        => 'desc',
                'type'       => 'required',
                'error_code' => 'desc_is_required',
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required',
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required',
            ],
            [
                'key'        => 'sort',
                'type'       => 'required',
                'error_code' => 'sort_is_required',
            ],
            [
                'key'        => 'show_main',
                'type'       => 'required',
                'error_code' => 'show_main_is_required',
            ],
            [
                'key'        => 'show_list',
                'type'       => 'required',
                'error_code' => 'show_list_is_required',
            ],
            [
                'key'        => 'action_type',
                'type'       => 'required',
                'error_code' => 'action_type_is_required',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = $this->getEmployeeId();
        $res = BannerService::add($params, $employeeId);
        return $response->withJson($res, StatusCode::HTTP_OK);
    }

    /**
     * 编辑数据
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function edit(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required',
            ],
            [
                'key'        => 'desc',
                'type'       => 'required',
                'error_code' => 'desc_is_required',
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required',
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required',
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required',
            ],
            [
                'key'        => 'sort',
                'type'       => 'required',
                'error_code' => 'sort_is_required',
            ],
            [
                'key'        => 'show_main',
                'type'       => 'required',
                'error_code' => 'show_main_is_required',
            ],
            [
                'key'        => 'show_list',
                'type'       => 'required',
                'error_code' => 'show_list_is_required',
            ],
            [
                'key'        => 'action_type',
                'type'       => 'required',
                'error_code' => 'action_type_is_required',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = BannerService::edit($params);
        return $response->withJson($res, StatusCode::HTTP_OK);
    }

}
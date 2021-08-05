<?php


namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\BannerConfigModel;
use App\Services\DictService;
use App\Services\SourceMaterialService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class SourceMaterial extends ControllerBase
{

    /**
     * 素材保存
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sourceSave(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 30,
                'error_code' => 'name_length_invalid'
            ],
            [
                'key' => 'image_path',
                'type' => 'required',
                'error_code' => 'image_path_is_required'
            ],
            [
                'key' => 'mark',
                'type' => 'lengthMax',
                'value' => 100,
                'error_code' => 'mark_length_invalid'
            ],

        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            if (empty($params['id'])) {
                SourceMaterialService::soucrceAdd($params, $employeeId);
            } else {
                SourceMaterialService::soucrceEdit($params, $employeeId);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 素材库列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sourceList(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $data = SourceMaterialService::sourceList($params, $page, $limit);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 启用状态修改
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editEnableStatus(Request $request, Response $response)
    {

        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'integer',
                'error_code' => 'id_is_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'required',
                'error_code' => 'enable_status_is_required'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'enable_status_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            SourceMaterialService::editEnableStatus($params['id'], $params['enable_status'], $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 素材类型添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sourceTypeAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 30,
                'error_code' => 'name_length_invalid'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            SourceMaterialService::sourceTypeAdd($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 资源类型列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sourceTypeList(Request $request, Response $response)
    {
        $typeArray = [SourceMaterialService::SOURCE_ENABLE_STATUS,SourceMaterialService::SOURCE_TYPE_CONFIG];
        $data = array_merge(DictService::getListsByTypes($typeArray));
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 分享配置保存
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareSave(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'image_path',
                'type'       => 'required',
                'error_code' => 'image_path_is_required'
            ],
            [
                'key'        => 'remark',
                'type'       => 'required',
                'error_code' => 'remark_is_required'
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 100,
                'error_code' => 'remark_length_invalid'
            ],
            [
                'key'        => 'poster_word_id',
                'type'       => 'required',
                'error_code' => 'poster_word_id_is_required'
            ],
            [
                'key'        => 'poster_id',
                'type'       => 'required',
                'error_code' => 'poster_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['employee_id'] = $this->getEmployeeId();
            if (empty($params['id'])) {
                $data = SourceMaterialService::shareAdd($params);
            } else {
                $data = SourceMaterialService::shareEdit($params);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['id' => $data]);
    }

    /**
     * 分享配置详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareDetail(Request $request, Response $response)
    {
        try {
            $data = SourceMaterialService::shareDetail();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * banner新增
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function bannerSave(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 25,
                'error_code' => 'name_length_invalid'
            ],
            [
                'key' => 'site_type',
                'type' => 'required',
                'error_code' => 'site_type_is_required'
            ],
            [
                'key' => 'user_group',
                'type' => 'required',
                'error_code' => 'user_group_is_required'
            ],
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key' => 'image_type',
                'type' => 'required',
                'error_code' => 'image_type_is_required'
            ],
            [
                'key' => 'image_path',
                'type' => 'required',
                'error_code' => 'image_path_is_required'
            ],
            [
                'key' => 'jump_rule',
                'type' => 'required',
                'error_code' => 'jump_rule_is_required'
            ],
            [
                'key' => 'order',
                'type' => 'required',
                'error_code' => 'order_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'value' => 200,
                'error_code' => 'remark_length_invalid'
            ],
        ];
        $params = $request->getParams();
        if ($params['jump_rule'] == BannerConfigModel::IS_ALLOW_JUMP) {
            $rules[] = [
                'key'        => 'jump_url',
                'type'       => 'required',
                'error_code' => 'jump_url_is_required'
            ];
        }
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['employee_id'] = $this->getEmployeeId();
            $data = SourceMaterialService::bannerSave($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['id' => $data]);
    }

    /**
     * 启用状态修改
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function bannerEditEnableStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'enable_status',
                'type' => 'required',
                'error_code' => 'enable_status_is_required'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'enable_status_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['employee_id']  = $this->getEmployeeId();
            SourceMaterialService::bannerEditEnableStatus($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 下拉列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function selectLists(Request $request, Response $response)
    {
        try {
            $data = SourceMaterialService::selectLists();
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * banner列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function bannerLists(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $data = SourceMaterialService::bannerLists($params, $page, $limit);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * banner详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function bannerDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::bannerDetail($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

}

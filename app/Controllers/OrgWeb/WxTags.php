<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/6/28
 * Time: 3:31 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\UserCenter;
use App\Models\UserWeixinModel;
use App\Services\WxTagsService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class WxTags extends ControllerBase
{

    /**
     * 创建标签
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'tag_name',
                'type' => 'required',
                'error_code' => 'tag_name_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //应用和公众号类型后端固定
        $params['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $params['busi_type'] = UserWeixinModel::BUSI_TYPE_STUDENT_SERVER;
        $employeeId = self::getEmployeeId();

        try {
            WxTagsService::addTag($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 修改标签
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        //接收参数
        $rules = [
            [
                'key' => 'tag_name',
                'type' => 'required',
                'error_code' => 'tag_name_is_required'
            ],
            [
                'key' => 'tag_id',
                'type' => 'required',
                'error_code' => 'tag_id_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            WxTagsService::updateTag($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 删除标签
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function del(Request $request, Response $response)
    {
        //接收参数
        $rules = [
            [
                'key' => 'tag_id',
                'type' => 'required',
                'error_code' => 'tag_id_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            WxTagsService::delTag($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 标签列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = WxTagsService::tagList($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }
}
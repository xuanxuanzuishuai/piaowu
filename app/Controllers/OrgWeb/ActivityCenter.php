<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/7
 * Time: 11:57
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ActivityCenterModel;
use App\Services\ActivityCenterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ActivityCenter extends ControllerBase
{
    /**
     * 添加活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function create(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $result = $this->_checkParams($params);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            ActivityCenterService::createActivity($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getActivityList(Request $request, Response $response)
    {

        $params = $request->getParams();
        list($page, $pageSize) = Util::formatPageCount($params);

        $result = ActivityCenterService::getList($params, $page, $pageSize);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 更新状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editStatus(Request $request, Response $response)
    {

        $params = $request->getParams();

        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'in',
                'value'      => array_keys(ActivityCenterModel::STATUS_DICT),
                'error_code' => 'status_is_error'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            ActivityCenterService::editStatus($params['activity_id'], $params['status']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 更新权重
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editWeight(Request $request, Response $response)
    {

        $params = $request->getParams();

        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'weight',
                'type'       => 'required',
                'error_code' => 'weight_is_required'
            ],
            [
                'key'        => 'weight',
                'type'       => 'integer',
                'error_code' => 'weight_is_error'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            ActivityCenterService::editWeight($params['activity_id'], $params['weight']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param array $params
     * @return array|int[]
     * @throws RunTimeException
     */
    private static function _checkParams(array $params)
    {
        $rules = [
            //必填项
            ['key' => 'name', 'type' => 'required', 'error_code' => 'name_is_required'],
            ['key' => 'name', 'type' => 'lengthMax', 'value' => 20, 'error_code' => 'name_max_length_is_20'],
            ['key' => 'url', 'type' => 'required', 'error_code' => 'url_is_required'],
            ['key' => 'url', 'type' => 'url', 'error_code' => 'url_is_error'],
            ['key' => 'banner', 'type' => 'required', 'error_code' => 'banner_is_required'],
            ['key' => 'show_rule', 'type' => 'required', 'error_code' => 'show_rule_is_required'],
            ['key' => 'button', 'type' => 'required', 'error_code' => 'button_is_required'],
            ['key' => 'channel', 'type' => 'required', 'error_code' => 'channel_is_required'],
            ['key' => 'label', 'type' => 'lengthMax', 'value' => 6, 'error_code' => 'label_max_length_is_20'],

        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $result;
        }

        Util::containEmoji($params['name'], true);
        Util::containEmoji($params['button'], true);
        Util::containEmoji($params['label'], true);

        if (!Util::isChineseText($params['button'])){
            throw new RunTimeException(['param_is_chinese_text']);
        }

        if (!Util::isChineseText($params['label']) && !empty($params['label'])){
            throw new RunTimeException(['param_is_chinese_text']);
        }

        return ['code' => 0];
    }


    /**
     * 更新活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editActivity(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $result = $this->_checkParams($params);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            ActivityCenterService::editActivity($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 获取详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getActivityDetail(Request $request, Response $response)
    {
        $params = $request->getParams();

        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $detail = ActivityCenterService::getDetail($params['activity_id']);
        return HttpHelper::buildResponse($response, $detail);
    }


}
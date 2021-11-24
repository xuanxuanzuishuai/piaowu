<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/08
 * Time: 11:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\CopyManageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class CopyManage extends ControllerBase
{
    /**
     * 文案编辑
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'content_is_required'
            ],
            [
                'key' => 'content',
                'type' => 'lengthMax',
                'error_code' => 'length_invalid',
                'value' => 9000
            ],
            [
                'key' => 'content_id',
                'type' => 'required',
                'error_code' => 'content_id_is_required'
            ],
            [
                'key' => 'content_id',
                'type' => 'integer',
                'error_code' => 'content_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = CopyManageService::update($params['content_id'], $params['content'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 文案列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'content_id',
                'type' => 'integer',
                'error_code' => 'content_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $data = CopyManageService::list($params);
        return HttpHelper::buildResponse($response, ['data' => $data, 'operation_button' => self::getEmployeeOperationButton()]);
    }


}

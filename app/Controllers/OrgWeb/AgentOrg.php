<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentOrgService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AgentOrg extends ControllerBase
{
    /**
     * 代理商机构专属曲谱教材关联
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgOpnRelation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_org_id',
                'type' => 'required',
                'error_code' => 'agent_org_id_required'
            ],
            [
                'key' => 'opn_id',
                'type' => 'required',
                'error_code' => 'opn_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AgentOrgService::orgOpnRelation($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 代理商机构专属曲谱教材列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgOpnList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $logData = AgentOrgService::orgOpnList($params);
        return HttpHelper::buildResponse($response, $logData);
    }


    /**
     * 代理商机构统计数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgStaticsData(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $logData = AgentOrgService::orgStaticsData($params);
        return HttpHelper::buildResponse($response, $logData);
    }

    /**
     * 学生列表
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $logData = AgentOrgService::studentList($params);
        return HttpHelper::buildResponse($response, $logData);
    }


    /**
     * 学生添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key'        => 'real_name',
                'type'       => 'required',
                'error_code' => 'real_name_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['operator_id'] = self::getEmployeeId();

        try {
            AgentOrgService::addStudent($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 删除机构学生
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentDel(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['operator_id'] = self::getEmployeeId();
        try {
            AgentOrgService::delStudent($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * execl导入
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentImport(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $file = $_FILES['filename'];
        if (empty($file)) {
            return $response->withJson(Valid::addErrors([], 'import', 'filename_is_required'));
        }
        $extension = strtolower(pathinfo($_FILES['filename']['name'])['extension']);
        if (!in_array($extension, ['xls', 'xlsx'])) {
            return $response->withJson(Valid::addErrors([], 'org_student_import', 'must_excel_format'));
        }
        //临时文件完整存储路径
        $filename = '/tmp/import_trade_no_' . md5(rand() . time()) . '.' . $extension;
        if (move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
            return $response->withJson(Valid::addErrors([], 'org_student_import', 'move_file_fail'));
        }
        try {
            $employee = $this->ci['employee'];
            // 检查订单数据
            AgentOrgService::studentImportAdd($filename, $employee, $params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        } finally {
            //删除临时文件
            unlink($filename);
        }
        return HttpHelper::buildResponse($response, []);
    }
}
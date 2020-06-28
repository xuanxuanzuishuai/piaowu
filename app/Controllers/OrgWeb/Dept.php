<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/22
 * Time: 3:01 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\DeptPrivilegeService;
use App\Services\DeptService;
use Slim\Http\Request;
use Slim\Http\Response;


class Dept extends ControllerBase
{
    /**
     * 树形列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function tree(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $treeData = DeptService::tree();

        return HttpHelper::buildResponse($response, $treeData);
    }

    /**
     * Banner 数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();

        list($list, $totalCount) = DeptService::list($params);

        return HttpHelper::buildResponse($response, [
            'list' => $list,
            'total_count' => $totalCount
        ]);
    }

    /**
     * Banner 数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function modify(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $ret = DeptService::modify($params, $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['result' => $ret]);
    }

    /**
     * 部门权限
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function deptPrivilege(Request $request, Response $response)
    {
        $params = $request->getParams();

        $privileges = DeptPrivilegeService::getByDept($params['dept_id']);

        return HttpHelper::buildResponse($response, ['privileges' => $privileges]);
    }

    /**
     * 编辑部门权限
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function privilegeModify(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $ret = DeptPrivilegeService::modify($params, $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['result' => $ret]);
    }
}
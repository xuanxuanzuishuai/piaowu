<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/25
 * Time: 下午7:59
 */

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\OrganizationModel;
use App\Services\EmployeeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{

    /**
     * 用户中心登录验证地址
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function usercenterurl(Request $request, Response $response, $args)
    {
        list($ucHost,$ucAppId) = DictConstants::get(DictConstants::USER_CENTER, ['host', 'app_id_dss']);
        return $response->withJson(array(
            'code' => 0,
            'data' => [
                "url" => "$ucHost/#/login?app_id=$ucAppId"
            ]), StatusCode::HTTP_OK);
    }

    public function tokenlogin(Request $request, Response $response, $args)
    {
        $xyzToken = $request->getParam("token");
        if (empty($xyzToken)) {
            return $response->withJson([
                'code' => Valid::CODE_PARAMS_ERROR,
                'data' => []
            ], StatusCode::HTTP_FORBIDDEN);
        }

        SimpleLogger::info(__FILE__, ['token' => $xyzToken]);

        $res = EmployeeService::checkUcToken($xyzToken);
        if (!empty($res['code']) && $res['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($res, StatusCode::HTTP_OK);
        }
        list($token, $userInfo, $expires) = $res;

        //判断机构是否被废除，废除要返回错误
        if(!empty($userInfo['org_id']) && $userInfo['org_id'] > 0) {
            $org = OrganizationModel::getById($userInfo['org_id']);
            if($org['status'] == OrganizationModel::STATUS_STOP) {
                return $response->withJson(Valid::addErrors([],'auth','org_is_disabled'));
            }
            //判断激活时间是否为空，为空则更新
            if(empty($org['start_time'])) {
                $today = strtotime('today');
                $end = strtotime('today +1 year');
                $affectRows = OrganizationModel::updateRecord($org['id'], ['start_time' => $today, 'end_time' => $end], false);
                if($affectRows != 1) {
                    return $response->withJson(Valid::addErrors([],'auth','update_org_active_time_fail'));
                }
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'token' => $token,
                'expires' => $expires,
                'user' => $userInfo,
                'wsinfo' => []
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * 登出
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function signout(Request $request, Response $response, $args)
    {
        $token = $request->getHeader('token');
        EmployeeService::logout($token[0]);
        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => []], StatusCode::HTTP_OK);
    }
}
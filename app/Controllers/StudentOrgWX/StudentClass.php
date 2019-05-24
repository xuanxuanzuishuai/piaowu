<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentOrgWX;

use App\Controllers\ControllerBaseForOrg;
use App\Libs\Valid;
use App\Models\ClassUserModel;
use App\Services\ClassTaskService;
use App\Services\ClassUserService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentClass extends ControllerBaseForOrg
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 预约体验课班级
     */
    public function OrderClass(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $userInfo = $this->ci['user_info'];
        $ctIds = ClassTaskService::getCTIds($params['class_id']);
        ClassUserService::bindCUs($params['class_id'], [ClassUserModel::USER_ROLE_S => [$userInfo['user_id'] => [$params['oprice']]]], $ctIds);
        return $response->withJson([
            'code' => 0,
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 当前学生的约课记录
     */
    public function getOrderClass(Request $request, Response $response)
    {
        $userInfo = $this->ci['user_info'];
        $scheduleInfo = ClassUserService::getUserClassInfo(['user_id' => $userInfo['user_id']], ClassUserModel::STATUS_NORMAL);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'schedule_info' => $scheduleInfo
            ]
        ]);
    }

}

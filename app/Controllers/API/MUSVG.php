<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/13
 * Time: 6:47 PM
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\OrganizationModelForApp;
use App\Models\StudentModelForApp;
use App\Models\TeacherModelForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MUSVG extends ControllerBase
{
    /**
     * 验证musvg传给测评服务的token
     * 学生app传学生token
     * 老师app未选择老师时传机构token
     * 老师app选择老师后传老师token
     * 依次验证3种情况，为老师或学生token时返回uuid，为机构时返回机构id，否则返回错误
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getUserId(Request $request, Response $response)
    {
        Util::unusedParam($request);

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'user_id' => $this->ci['ai_uid'],
        ], StatusCode::HTTP_OK);
    }
}
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
use App\Services\CampusService;
use App\Services\ScheduleService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OrgCampus extends ControllerBaseForOrg
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 当前机构提供哪些体验课
     */
    public function getOrgCampusList(Request $request, Response $response)
    {
        $campus = CampusService::getCampus();
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "campus" => $campus
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 当前机构某个校区提供体验课的排课时间
     */
    public function getOrgCampusArrange(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'campus_id',
                'type' => 'required',
                'error_code' => 'campus_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $timeArrange = ScheduleService::getOrgCampusArrage($params['campus_id'], $params['course_id']);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                'time_arrange' => $timeArrange
            ]
        ], StatusCode::HTTP_OK);
    }

}

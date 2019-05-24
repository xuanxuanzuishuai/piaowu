<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentOrgWX;

use App\Controllers\ControllerBaseForOrg;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\CourseModel;
use App\Services\CourseService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Course extends ControllerBaseForOrg
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 当前系统提供哪些体验课
     */
    public function getTestCourse(Request $request, Response $response)
    {
        $params['course_type'] = CourseModel::TYPE_TEST;
        //格式化分页参数
        if(isset($params['page'])) {
            list($page, $count) = Util::formatPageCount($params);
        }
        else {
            $page = -1;
            $count = 20;
        }

        list($totalCount, $courseData) = CourseService::getCourseUnitList($page, $count, $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'course_data' => $courseData,
            ]
        ], StatusCode::HTTP_OK);
    }
}

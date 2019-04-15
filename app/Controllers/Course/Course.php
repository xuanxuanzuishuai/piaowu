<?php
/**
 * Created by PhpStorm.
 * User: wangxiong
 * Date: 2018/11/9
 * Time: 下午2:18
 */

namespace App\Controllers\Course;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\CourseModel;
use App\Services\Product\CourseService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Course extends ControllerBase
{


    /**
     * 课程添加
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */

    public function add(Request $request, Response $response, $args)
    {
        // 参数校验
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'level',
                'type' => 'required',
                'error_code' => 'level_is_required',
            ],
            [
                'key' => 'product_line',
                'type' => 'required',
                'error_code' => 'product_line_is_required',
            ],
            [
                'key' => 'product_line',
                'type' => 'integer',
                'error_code' => 'product_line_must_be_integer',
            ],
            [
                'key' => 'course_type',
                'type' => 'required',
                'error_code' => 'course_type_is_required',
            ],
            [
                'key' => 'duration',
                'type' => 'required',
                'error_code' => 'course_duration_is_required',
            ],
            [
                'key' => 'duration',
                'type' => 'integer',
                'error_code' => 'course_duration_must_be_integer',
            ],
            [
                'key' => 'oprice',
                'type' => 'required',
                'error_code' => 'course_oprice_is_required',
            ],

            [
                'key' => 'oprice',
                'type' => 'numeric',
                'error_code' => 'course_oprice_must_be_numeric',
            ],
            [
                'key' => 'oprice',
                'type' => 'min',
                'value' => 0.01,
                'error_code' => 'oprice_must_be_gt_0',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'required',
                'error_code' => 'course_class_lowest_is_required',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'integer',
                'error_code' => 'course_class_lowest_must_be_integer',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'course_class_lowest_must_be_gt_0',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'course_class_highest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'integer',
                'error_code' => 'course_class_highest_must_be_integer',
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'course_name_is_required',
            ],
            [
                'key' => 'desc',
                'type' => 'required',
                'error_code' => 'course_desc_is_required',
            ],
            [
                'key' => 'thumb',
                'type' => 'required',
                'error_code' => 'course_thumb_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operatorId = $this->employee['id'];
        $params['operator_id'] = $operatorId;

        // 班型最低人数必须小于等于班型最高人数
        if ($params['class_lowest'] > $params['class_highest']) {
            $errorInfo = Valid::addErrors([], 'class_lowest', 'course_class_lowest_must_be_lt_class_highest');
            return $response->withJson($errorInfo, StatusCode::HTTP_OK);
        }

        $result = CourseService::addOrEditCourse($params);
        if (!is_numeric($result)) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }

    /**
     * 课程列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */

    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'product_line',
                'type' => 'integer',
                'error_code' => 'product_line_must_be_integer',
            ],
            [
                'key' => 'instrument',
                'type' => 'integer',
                'error_code' => 'instrument_must_be_integer',
            ],
            [
                'key' => 'level',
                'type' => 'integer',
                'error_code' => 'course_level_must_be_integer',
            ],
            [
                'key' => 'duration',
                'type' => 'integer',
                'error_code' => 'course_duration_must_be_integer',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //格式化分页参数
        list($page, $count) = Util::formatPageCount($params);
        list($totalCount, $courseData) = CourseService::getCourseUnitList($page, $count, $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'course_data' => $courseData,
            ]
        ], StatusCode::HTTP_OK);
    }


    /**
     * 课程详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */

    public function detail(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'course_id',
                'type' => 'integer',
                'error_code' => 'course_id_must_be_integer',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        // 获取课程详情
        $courseDetail = CourseService::getCourseDetailById($params['course_id']);
        if (!empty($courseDetail)) {
            $courseDetail['is_hardware'] = (isset($courseDetail['course_type']) && $courseDetail['course_type'] == CourseModel::TYPE_HARDWARE) ? 1 : 0;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $courseDetail
        ]);
    }


    /**
     * 课程编辑
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */

    public function modify(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'course_id',
                'type' => 'integer',
                'error_code' => 'course_id_must_be_integer',
            ],
            [
                'key' => 'level',
                'type' => 'required',
                'error_code' => 'level_is_required',
            ],
            [
                'key' => 'product_line',
                'type' => 'required',
                'error_code' => 'product_line_is_required',
            ],
            [
                'key' => 'product_line',
                'type' => 'integer',
                'error_code' => 'product_line_must_be_integer',
            ],
            [
                'key' => 'course_type',
                'type' => 'required',
                'error_code' => 'course_type_is_required',
            ],
            [
                'key' => 'duration',
                'type' => 'required',
                'error_code' => 'course_duration_is_required',
            ],
            [
                'key' => 'duration',
                'type' => 'integer',
                'error_code' => 'course_duration_must_be_integer',
            ],
            [
                'key' => 'oprice',
                'type' => 'required',
                'error_code' => 'course_oprice_is_required',
            ],
            [
                'key' => 'oprice',
                'type' => 'numeric',
                'error_code' => 'course_oprice_must_be_numeric',
            ],
            [
                'key' => 'oprice',
                'type' => 'min',
                'value' => 0.01,
                'error_code' => 'oprice_must_be_gt_0',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'required',
                'error_code' => 'course_class_lowest_is_required',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'integer',
                'error_code' => 'course_class_lowest_must_be_integer',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'course_class_lowest_must_be_gt_0',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'course_class_highest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'integer',
                'error_code' => 'course_class_highest_must_be_integer',
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'course_name_is_required',
            ],
            [
                'key' => 'desc',
                'type' => 'required',
                'error_code' => 'course_desc_is_required',
            ],
            [
                'key' => 'thumb',
                'type' => 'required',
                'error_code' => 'course_thumb_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 判断是否存在
        $courseDerail = CourseService::getCourseDetailById($params['course_id']);
        if (empty($courseDerail)) {
            return $response->withJson(Valid::addErrors([], 'course_id', 'course_id_not_exist'));
        }
        // 判断是否未发布
        $courseStatus = $courseDerail['status'];
        if ($courseStatus != CourseModel::COURSE_STATUS_WAIT) {
            return $response->withJson(Valid::addErrors([], 'course_id', 'course_status_must_be_wait'));
        }

        $operatorId = $this->employee['id'];
        $params['operator_id'] = $operatorId;

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        // 班型最低人数必须小于等于班型最高人数
        if ($params['class_lowest'] > $params['class_highest']) {
            $errorInfo = Valid::addErrors([], 'class_lowest', 'course_class_lowest_must_be_lt_class_highest');
            return $response->withJson($errorInfo, StatusCode::HTTP_OK);
        }
        $result = CourseService::addOrEditCourse($params, $params['course_id']);
        if (!is_numeric($result)) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }
}



<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Controllers\Boss;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\ClassroomService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Classroom extends ControllerBase
{
    /**
     * Get all classrooms information
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args) {
        $params = $request->getParams();
        $classrooms = ClassroomService::getClassrooms($params);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classrooms" => $classrooms
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * Get a classroom's detail information
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args) {
        $rules = [
            [
                "key" => "id",
                "type" => "required",
                "error_code" => "classroom id is required"
            ]
        ];

        $param = $request->getParams();

        $result = Valid::validate($param, $rules);
        if ($result["code"] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $classroom = ClassroomService::getById($param["id"]);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classroom" => $classroom
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * Create or modify a classroom's information
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args) {
        $rules = [
            [
                "key" => "name",
                "type" => "required",
                "error_code" => "classroom name is required"
            ],
            [
                "key" => "campus_id",
                "type" => "required",
                "error_code" => "classroom campus_id is required"
            ]
        ];

        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result["code"] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $cRId = ClassroomService::insertOrUpdateClassroom($param["id"], $param);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classroom_id" => $cRId
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                "key" => "name",
                "type" => "required",
                "error_code" => "classroom name is required"
            ],
            [
                "key" => "campus_id",
                "type" => "required",
                "error_code" => "classroom campus_id is required"
            ]
        ];

        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result["code"] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $cRId = ClassroomService::insertOrUpdateClassroom($param["id"], $param);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classroom_id" => $cRId
            ]
        ], StatusCode::HTTP_OK);
    }
}


<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Controllers\Boss;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\ClassroomModel;
use App\Services\ClassroomService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Classroom extends ControllerBase
{
    public static function list(Request $request, Response $response, $args) {
        $classrooms = ClassroomModel::getClassrooms();
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classrooms" => $classrooms
            ]
        ], StatusCode::HTTP_OK);
    }

    public static function detail(Request $request, Response $response, $args) {
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

        $classroom = ClassroomModel::getById($param["id"]);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "classroom" => $classroom
            ]
        ], StatusCode::HTTP_OK);
    }

    public static function modify(Request $request, Response $response, $args) {
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


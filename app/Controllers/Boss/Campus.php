<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Controllers\Boss;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\CampusService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Campus extends ControllerBase
{
    /**
     * Get all campus information
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args) {
        $campus = CampusService::getCampus();
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "campus" => $campus
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * Get a campus's information
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
                "error_code" => "campus id is required"
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result["code"] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $campus = CampusService::getById($param["id"]);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "campus" => $campus
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * Create or modify a campus's information
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
                "error_code" => "campus name is required"
            ]
        ];

        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result["code"] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $campusId = CampusService::insertOrUpdate($param["id"], $param);
        return $response->withJson([
            "code" => Valid::CODE_SUCCESS,
            "data" => [
                "campusId" => $campusId
            ]
        ], StatusCode::HTTP_OK);
    }
}
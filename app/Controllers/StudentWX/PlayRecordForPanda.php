<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 11:09
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\AIPLCenter;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\PlayRecordModel;
use App\Models\StudentModelForApp;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\PlayRecordService;


class PlayRecordForPanda extends ControllerBase
{
    /**
     * 获取练琴月历
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getMonthStatistics(Request $request, Response $response){
        $rules = [
            [
                'key' => 'month',
                'type' => 'required',
                'error_code' => 'month_is_required'
            ],
            [
                'key' => 'year',
                'type' => 'required',
                'error_code' => 'year_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $user_id = $this->ci['user_info']['user_id'];
        $ret = PlayRecordModel::getMonthPlayRecordStatistics($params["year"], $params["month"], $user_id);
        $user_info = StudentModelForApp::getStudentInfo($user_id, null);

        if (!empty($user_info)){
            $user_name = $user_info["name"];
        } else{
            $user_name = "";
        }
        $result = [];
        foreach ($ret as $value){
            array_push($result, $value["play_date"]);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                "name" => $user_name,
                "play_date_list" => $result
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 练琴月历中的每日统计
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getMonthDayStatistics(Request $request, Response $response){
        $rules = [
            [
                'key' => 'date',
                'type' => 'required',
                'error_code' => 'date_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $start_time = strtotime($params["date"]);
        $end_time = $start_time + 86399;
        $user_id = $this->ci['user_info']['user_id'];
        $ret = PlayRecordModel::getPlayRecordReport($user_id, $start_time, $end_time);

        $result = [
            "duration" => 0,
            "max_score" => 0
        ];
        $lesson_ids = [];
        foreach ($ret as $value){
            $current_max_score = max($value["max_dmc"], $value["max_ai"]);
            $result["duration"] += $value["duration"];
            $lesson_ids[$value["lesson_id"]] = 1;
            $r = ($current_max_score > $result["max_score"]);
            $max_score = $r ? $current_max_score : $result["max_score"];
            $result["max_score"] = $max_score;

        }
        $result["lesson_numbers"] = sizeof($lesson_ids);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }
}

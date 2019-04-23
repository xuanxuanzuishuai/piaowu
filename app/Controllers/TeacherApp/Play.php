<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/22
 * Time: 15:44
 */


namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\PlayRecordModel;
use App\Services\UserPlayServices;
use App\Services\StorageService;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 1v1老师端，学生演奏曲谱
 *
 */
class Play extends ControllerBase
{

    /**
     * 静态演奏结束，上传练琴记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function end(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'play_data_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        //$userID = $this->ci['student']['id'];
        $userID = 22;
        // 新增record_type字段区分演奏类型，situation_type区分课上课下
        $params['data']['record_type'] = PlayRecordModel::TYPE_DYNAMIC;
        $params['data']['situation_type'] = PlayRecordModel::TYPE_ON_CLASS;
        list($errorCode, $ret) = UserPlayServices::addRecord($userID, $params['data']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        $data = [
            'record_id' => $ret['record_id'],
            'play_result' => $ret['play_result']
        ];

        list($tokenErrorCode, $tokenRet) = StorageService::getAccessCredentials($userID, $ret['record_id']);

        if (empty($tokenErrorCode)) {
            $data['credentials'] = $tokenRet['Credentials'];
            $data['bucket'] = $tokenRet['bucket'];
            $data['path'] = $tokenRet['path'];
            $data['end_point'] = $tokenRet['end_point'];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 动态演奏结束
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function aiEnd(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'play_data_is_required' => 'play_data_is_required'
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        // 插入练琴纪录表
        // $userId = $this->ci['student']['id'];
        $userId = 22;
        $param['data']['record_type'] = PlayRecordModel::TYPE_AI;
        $param['data']['situation_type'] = PlayRecordModel::TYPE_ON_CLASS;
        list($errCode, $ret) = UserPlayServices::addRecord($userId, $param['data']);
        if (!empty($errCode)) {
            $errors = Valid::addAppErrors([], $errCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }

        // 检查作业
        $param['data']['record_id'] = $ret['record_id'];
        list($homeworkErrCode, $allHomeworks, $finished) = HomeworkService::checkHomework($userId, $param['data']);
        if (!empty($homeworkErrCode)) {
            $errors = Valid::addAppErrors([], $homeworkErrCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }
        $db->commit();

        // 处理返回数据
        SimpleLogger::debug("*********check homework******", ['all' => $allHomeworks, 'finished' => $finished]);
        $data = ['record_id' => $ret['record_id']];
        if (!empty($finished)) {
            // 优先返回达成的作业
            $homework = $finished[0];
            $homeworkInfo = [
                'id' => $homework['id'],
                'task_id' => $homework['task_id'],
                'baseline' => json_decode($homework['baseline'], true),
                'complete' => 1
            ];
        } elseif (!empty($allHomeworks)) {
            // 如果未达成，返回未达成的作业
            $homework = $allHomeworks[0];
            $homeworkInfo = [
                'id' => $homework['id'],
                'task_id' => $homework['task_id'],
                'baseline' => json_decode($homework['baseline'], true),
                'complete' => 0
            ];
        } else {
            $homeworkInfo = [];
        }
        $data['homework'] = $homeworkInfo;
        return $response->withJson(['code' => 0, 'data' => $data], StatusCode::HTTP_OK);
    }
}
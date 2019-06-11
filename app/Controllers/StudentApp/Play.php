<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/21
 * Time: 6:58 PM
 * 练琴记录相关
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\PlayRecordModel;
use App\Services\PlayRecordService;
use App\Services\UserPlayServices;
use App\Services\StorageService;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 学生演奏曲谱
 *
 */

class Play extends ControllerBase
{

    /**
     * url:/user/play/save
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function save(Request $request, Response $response){
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        SimpleLogger::debug('>>>>>>> appValidate', [
            '$params' => $params,
            '$rules' => $rules,
            '$result' => $result,
        ]);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userID = $this->ci['student']['id'];

        $save = UserPlayServices::getSave($userID, $params['lesson_id']);
        if (empty($save)) {
            $save = null;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'save' => $save
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 静态演奏结束，上传练琴记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function end(Request $request, Response $response){
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

        $userID = $this->ci['student']['id'];
        $params['data']['lesson_type'] = PlayRecordModel::TYPE_DYNAMIC;
        $params['data']['client_type'] = PlayRecordModel::CLIENT_STUDENT;
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
    public function aiEnd(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'play_data_is_required'
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 没有学生信息时返回空
        if (empty($this->ci['student'])) {
            return $response->withJson(['code' => 0], StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        // 插入练琴纪录表
        $userId = $this->ci['student']['id'];
        $param['data']['lesson_type'] = PlayRecordModel::TYPE_AI;
        $param['data']['client_type'] = PlayRecordModel::CLIENT_STUDENT;
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
        SimpleLogger::debug("*********check homework******", ['all'=>$allHomeworks, 'finished'=>$finished]);
        $data = ['record_id' => $ret['record_id']];
        if(!empty($finished)){
            // 优先返回达成的作业
            $homework = $finished[0];
            $homeworkInfo = [
                'id'=> $homework['id'],
                'task_id'=> $homework['task_id'],
                'baseline'=> json_decode($homework['baseline'], true),
                'complete'=> 1
            ];
        }elseif(!empty($allHomeworks)){
            // 如果未达成，返回未达成的作业
            $homework = $allHomeworks[0];
            $homeworkInfo = [
                'id'=> $homework['id'],
                'task_id'=> $homework['task_id'],
                'baseline'=> json_decode($homework['baseline'], true),
                'complete'=> 0
            ];
        }else{
            $homeworkInfo = [];
        }
        $data['homework'] = $homeworkInfo;
        return $response->withJson(['code'=>0, 'data'=>$data], StatusCode::HTTP_OK);
    }

    public function rank(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ],
            [
                'key' => 'org',
                'type' => 'required',
                'error_code' => 'org_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        //$studentId = 29;
        $lessonId = $params['lesson_id'];
        $isOrg = $params['org'] == 1;
        $ranks = PlayRecordService::getRanks($studentId, $lessonId, $isOrg);
        return $response->withJson(['code'=>0, 'data'=>$ranks], StatusCode::HTTP_OK);
    }

}
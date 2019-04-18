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
use App\Libs\Valid;
use App\Models\PlayRecordModel;
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
     * url:/user/play/end
     * 学生练琴结束，上传练琴记录
     * @param Request $request
     * @param Response $response
     * @return mixed
     *
     */
    public function PlayEnd(Request $request, Response $response){
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

        $userID = $this->user['id'];
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
     * url:/user/play/save
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function PlaySave(Request $request, Response $response){
        $rules = [
            [
                'key' => 'opern_id',
                'type' => 'required',
                'error_code' => 'opern_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userID = $this->user['id'];

        $save = UserPlayServices::getSave($userID, $params['opern_id']);
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
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function AiPlayEnd(Request $request, Response $response){
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'play_data_is_required' => 'play_data_is_required'
            ]
        ];

        $param = $request->getParams();
        $result = Valid::validate($param, $rules);

        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        //$userId = $this->ci['user']['id']
        $userId = 888888;
        $param['data']['record_type'] = PlayRecordModel::TYPE_AI;
        $param['data']['situation_type'] = PlayRecordModel::TYPE_OFF_CLASS;
        list($errCode, $ret) = UserPlayServices::addRecord($userId, $param['data']);
        if (!empty($errCode)) {
            $errors = Valid::addAppErrors([], $errCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }
        $param['data']['record_id'] = $ret['record_id'];
        list($homeworkErrCode, $homework) = HomeworkService::checkHomework($userId, $param['data']);
        if (!empty($homeworkErrCode)) {
            $errors = Valid::addAppErrors([], $homeworkErrCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }
        $db->commit();

        $data = [
            'record_id' => $ret['record_id'],
            'homework' => $homework
        ];
        return $response->withJson($data, StatusCode::HTTP_OK);
    }

}
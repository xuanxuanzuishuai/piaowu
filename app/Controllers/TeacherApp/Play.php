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

        $userID = $this->ci['student']['id'];
        $params['data']['lesson_type'] = PlayRecordModel::TYPE_DYNAMIC;
        // 老师端练琴以schedule_id识别,此时schedule_id可能尚未生成。因此统一用0标记schedule_id
        // 统计数据时schedule_id为0时，表示老师端课上练琴
        $params['data']['schedule_id'] = 0;
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
                'error_code' => 'play_data_is_required'
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
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
        // 同end接口
        $param['data']['schedule_id'] = 0;
        list($errCode, $ret) = UserPlayServices::addRecord($userId, $param['data']);
        if (!empty($errCode)) {
            $errors = Valid::addAppErrors([], $errCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }

        // 琴房的演奏不检查作业
        $db->commit();
        $data = ['record_id' => $ret['record_id'], 'homework' => []];
        return $response->withJson(['code' => 0, 'data' => $data], StatusCode::HTTP_OK);
    }
}
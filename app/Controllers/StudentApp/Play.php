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
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\PlayRecordModel;
use App\Models\StudentModelForApp;
use App\Services\AIPlayRecordService;
use App\Services\UserPlayServices;
use App\Services\StorageService;
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
    public function save(Request $request, Response $response)
    {
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
        $params['data']['client_type'] = PlayRecordModel::CLIENT_STUDENT;
        $params['data']['ai_type'] = PlayRecordModel::AI_EVALUATE_PLAY;

        $isAnonymous = StudentModelForApp::isAnonymousStudentId($userID);
        if ($isAnonymous) {
            list($errorCode, $ret) = UserPlayServices::emptyRecord($params['data']);
        } else {
            list($errorCode, $ret) = UserPlayServices::addRecord($userID, $params['data']);
        }

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

        // 插入到新数据表
        // 区分新版app和旧版app的数据
        $isOldVersion = AIPlayRecordService::isOldVersionApp($this->ci['version']);
        $params['data']['old_format'] = ($isOldVersion ? Constants::STATUS_TRUE : Constants::STATUS_FALSE);
        AIPlayRecordService::insertOldPracticeData($userID, $params['data'], $this->ci['version']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 上课模式结束
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function classEnd(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'play_data_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 没有学生信息时返回空
        if (empty($this->ci['student'])) {
            return $response->withJson(['code' => 0], StatusCode::HTTP_OK);
        }
        if (empty($params['data']['lesson_id'])) {
            $result = Valid::addAppErrors([], 'lesson_id_is_required');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        /* TODO 这个接口准备作废，转为靠消息队列上报数据
        // 插入练琴纪录表
        $userId = $this->ci['student']['id'];

        try {
            $recordId = PlayClassRecordService::addRecord($userId, $params['data']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, ['record_id' => $recordId]);
        */

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 演奏排行榜接口，只排ai测评的
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rank(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        $lessonId = $params['lesson_id'];
        $issueNumber = !empty($params['issue_number']) ? $params['issue_number'] : '';
        $ranks = AIPlayRecordService::getLessonRankData($lessonId, $studentId, $issueNumber);
        return $response->withJson(['code'=>0, 'data'=>$ranks], StatusCode::HTTP_OK);
    }

    public function playDuration(Request $request, Response $response)
    {
        $studentId = $this->ci['student']['id'];
        $playSum = AIPlayRecordService::getStudentSumDuration($studentId);

        return HttpHelper::buildResponse($response, ['total_duration' => $playSum]);
    }

    public function setJoinRanking(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'is_join_ranking',
                'type' => 'required',
                'error_code' => 'is_join_ranking_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $playRecordInfo = AIPlayRecordModel::getRecord(['id' => $params['id']]);
        if ($playRecordInfo['is_join_ranking'] != $params['is_join_ranking']) {
            AIPlayRecordModel::updateRecord($params['id'], ['is_join_ranking' => $params['is_join_ranking']]);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);

    }

    /**
     * 获取用户是否加入排行榜
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function joinRankingStatus(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $studentInfo = StudentModelForApp::getById($this->ci['student']['id']);
        return HttpHelper::buildResponse($response, ['join_ranking_status' => $studentInfo['is_join_ranking']]);
    }
}
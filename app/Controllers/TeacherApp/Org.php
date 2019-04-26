<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:17 PM
 */

namespace App\Controllers\TeacherApp;


use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\HomeworkService;
use App\Services\OrganizationServiceForApp;
use App\Controllers\ControllerBase;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Org extends ControllerBase
{

    public function getStudents(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];
        $students = OrganizationServiceForApp::getStudents($orgId, $params['teacher_id']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> ['students' => $students],
        ], StatusCode::HTTP_OK);
    }

    public function selectStudent(Request $request, Response $response)
    {

        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];
        $orgAccount = $this->ci['org_account'];
        //$orgId = 2;
        //$orgAccount = 10000001;
        list($errorCode, $loginData) = OrganizationServiceForApp::teacherLogin($orgId,
            $orgAccount,
            $params['teacher_id'],
            $params['student_id']
        );

        // 回课数据
        list($tasks, $statistics, $books) = HomeworkService::scheduleFollowUp($params['teacher_id'], $params['student_id']);
        SimpleLogger::debug("FOLLOW UP CLASS>>>>>>>>>>>>>>>>>>>>>>>>>>>", [$tasks, $statistics, $books]);
        $homework = [];
        foreach ($tasks as $task){

            $taskBase = [
                'lesson_id' => $task['lesson_id'],
                'lesson_name' => $task['lesson_name'],
                'complete' => $task['complete'],
                'book_name' => $task['book_name'],
                'res' => $task['res'],
                'cover' => $task['cover'],
                'score_id' => $task['score_id'],
            ];
            $play = $statistics[$task['lesson_id']];
            $book = $books[$task['lesson_id']];
            $homework[] = array_merge($taskBase, $play, $book);
        }
        $loginData['homework'] = $homework;

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $loginData,
        ], StatusCode::HTTP_OK);
    }
}
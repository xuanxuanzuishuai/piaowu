<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/5/15
 * Time: 18:12
 */

namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\TeacherNoteModel;
use App\Services\TeacherNoteService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Note extends ControllerBase{

    public function createNote(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'note_data_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $teacherId = $this->ci['teacher']['id'];
        $orgId = $this->ci['org']['id'];
        TeacherNoteService::createNote($teacherId, $params['lesson_id'], $params['data'], $orgId);
        return $response->withJson(['code' => 0], StatusCode::HTTP_OK);
    }

    public function updateNote(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'note_data_is_required'
            ],
            [
                'key' => 'note_id',
                'type' => 'required',
                'error_code' => 'note_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $noteId = $params['note_id'];
        $teacherId = $this->ci['teacher']['id'];
        $orgId = $this->ci['org']['id'];
        $data = ['content' => json_encode($params['data'])];

        TeacherNoteService::updateNote($teacherId, $noteId, $data, $orgId);
        return $response->withJson(['code' => 0], StatusCode::HTTP_OK);
    }

    public function deleteNote(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'note_id',
                'type' => 'required',
                'error_code' => 'note_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $noteId = $params['note_id'];
        $teacherId = $this->ci['teacher']['id'];
        $orgId = $this->ci['org']['id'];
        $data = ['deleted' => TeacherNoteModel::TRUE];

        TeacherNoteService::updateNote($teacherId, $noteId, $data, $orgId);
        return $response->withJson(['code' => 0], StatusCode::HTTP_OK);
    }

    public function listNote(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $teacherId = $this->ci['teacher']['id'];
        $orgId = $this->ci['org']['id'];
        $lessonId = $params['lesson_id'];

        $ret = TeacherNoteService::queryNote($teacherId, $orgId, $lessonId);
        return $response->withJson(['code' => 0, 'data' => $ret], StatusCode::HTTP_OK);
    }

}
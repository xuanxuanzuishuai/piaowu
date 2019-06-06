<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/5/17
 * Time: 15:28
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\TeacherNoteModel;
use App\Libs\AliOSS;

class TeacherNoteService{

    public static function createNote($teacherId, $lessonId, $data, $orgId=null){

        $content = json_encode($data);
        if(strlen($content) > TeacherNoteModel::NOTE_CONTENT_LEN){
            return 'note_content_invalid';
        }

        $temp = [];
        $temp['teacher_id'] = $teacherId;
        $temp['lesson_id'] = $lessonId;
        $temp['org_id'] = $orgId;
        $temp['note_type'] = $data['note_type'];
        $temp['content'] = $content;
        $temp['deleted'] = TeacherNoteModel::FALSE;
        $temp['create_time'] = time();
        $temp['update_time'] = time();

        TeacherNoteModel::insertRecord($temp);
        return null;
    }

    public static function updateNote($teacherId, $noteId, $data, $orgId){
        $note = TeacherNoteModel::getById($noteId);
        if( empty($note)
            or (int)$note['teacher_id'] != $teacherId
            or (int)$note['org_id'] != $orgId){
            return;
        }
        TeacherNoteModel::updateNote($noteId, $data);
    }

    public static function queryNote($teacherId, $orgId, $lessonId){
        $where = ['ORDER' => ['create_time' => 'DESC']];
        $notes = TeacherNoteModel::queryNote($teacherId, $orgId, $lessonId, $where);
        $ali = new AliOSS();
        $func = function ($item) use ($ali) {
            $item['content'] = json_decode($item['content'], true);
            $item['content']['signed_board'] = $ali->signUrls($item['content']['board']);
            $item['content']['signed_cover_file'] = $ali->signUrls($item['content']['coverFile']);
            $item['content']['signed_cover_thumb'] = $ali->signUrls($item['content']['coverFile'],
                "", "", AliOSS::PROCESS_STYLE_NOTE_THUMB);
            return $item;
        };
        return array_map($func, $notes);
    }

}
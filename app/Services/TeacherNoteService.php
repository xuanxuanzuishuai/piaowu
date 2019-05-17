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

class TeacherNoteService{

    public static function createNote($teacherId, $lessonId, $data, $orgId=null){

        $contents = [];
        foreach ($data as $content){
            $temp = [];
            $temp['teacher_id'] = $teacherId;
            $temp['lesson_id'] = $lessonId;
            $temp['org_id'] = $orgId;
            $temp['note_type'] = $content['note_type'];
            $temp['content'] = json_encode($content);
            $temp['deleted'] = TeacherNoteModel::FALSE;
            $temp['create_time'] = time();
            $temp['update_time'] = time();
            $contents[] = $temp;
        }
        TeacherNoteModel::batchInsert($contents);
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
        $notes = TeacherNoteModel::queryNote($teacherId, $orgId, $lessonId);
        $func = function ($item){
            $item['content'] = json_decode($item['content'], true);
            return $item;
        };
        return array_map($func, $notes);
    }




}
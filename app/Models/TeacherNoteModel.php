<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/5/15
 * Time: 11:31
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class TeacherNoteModel extends Model{

    public static $table = 'teacher_note';

    // 是否
    const TRUE = 1;
    const FALSE = 0;

    public static function newNote($note){
        self::insertRecord($note);
    }

    public static function queryNote($teacherId, $orgId=null, $lessonId=null,
                                     $where=[], $join=[], $columns=[]){
        $where[self::$table . '.teacher_id'] = $teacherId;
        $where[self::$table . '.deleted'] = self::FALSE;
        if (!empty($orgId)){
            $where[self::$table . '.org_id'] = $orgId;
        }
        if (!empty($lessonId)){
            $where[self::$table . '.lesson_id'] = $lessonId;
        }

        $columns = [
            self::$table . '.teacher_id',
            self::$table . '.org_id',
            self::$table . '.lesson_id',
            self::$table . '.note_type',
            self::$table . '.content',
            self::$table . '.deleted',
            self::$table . '.create_time',
            self::$table . '.update_time',
            self::$table . '.id'
        ];
        $db = MysqlDB::getDB();
        if(empty($join)) {
            $ret = $db->select(self::$table, $columns, $where);
        }else{
            $ret = $db->select(self::$table, $join, $columns, $where);
        }
        return $ret;
    }

    public static function updateNote($noteId, $newValues=[]){
        if(empty($newValues)){
            return;
        }
        $newValues['update_time'] = time();
        $db = MysqlDB::getDB();
        $db->update(self::$table, $newValues, ['id' => $noteId]);
    }

    public static function getNotesByIds($ids){
        $db = MysqlDB::getDB();
        $ret = $db->select(self::$table, "*", [
            "id" => $ids,
        ]);
        return $ret;
    }
}

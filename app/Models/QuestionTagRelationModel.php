<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/23
 * Time: 上午10:51
 */

namespace App\Models;


use App\Libs\MysqlDB;

class QuestionTagRelationModel extends Model
{
    public static $table = 'question_tag_relation';

    public static function updateStatusByQuestionId($questionId, $status)
    {
        $db = MysqlDB::getDB();
        return $db->updateGetCount(self::$table, ['status' => $status], ['question_id' => $questionId]);
    }
}
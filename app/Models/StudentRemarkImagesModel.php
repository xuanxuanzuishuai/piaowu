<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/3/20
 * Time: 4:09 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use phpDocumentor\Reflection\Types\Self_;

class StudentRemarkImagesModel extends Model
{
    public static $table = 'student_remark_image';

    public static function addRemarkImages($data)
    {
        MysqlDB::getDB()->insert(self::$table, $data);
    }

    public static function getRemarkImages($remarkId)
    {
        return MysqlDB::getDB()->select(self::$table, [
            self::$table . '.id',
            self::$table . '.student_remark_id',
            self::$table . '.image_url',
            self::$table . '.create_time'
        ], [
            'student_remark_id' => $remarkId,
            'status' => 1
        ]);
    }
}
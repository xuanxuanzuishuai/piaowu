<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/8
 * Time: 11:53
 */
namespace App\Models\Dss;

class DssGiftCodeDetailedModel extends DssModel
{
    public static $table = "gift_code_detailed";


    public static function studentDetails()
    {
        $limit        = 2;
        $table        = self::$table;
        $studentTable = DssStudentModel::$table;
        $packageType  = DssStudentModel::REVIEW_COURSE_49;
        $sql = " SELECT
                    b.id,
                    b.thumb
                FROM {$table} a
                INNER JOIN {$studentTable} b ON a.apply_user = b.id
                WHERE
                 a . package_type = {$packageType} 
                AND b.thumb <> ''
                ORDER BY a.id DESC LIMIT {$limit}
        ";
        return self::dbRO()->queryAll($sql);
    }

}
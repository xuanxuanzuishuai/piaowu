<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/10/26
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentMedalCategoryModel extends Model
{
    public static $table = "student_medal_category";

    const DEFAULT_SHOW = 1;
    const NOT_SHOW = 0;

    /**
     * @param $studentId
     * @return array|null
     * 获取默认展示奖章类别的等级最大medal_id
     */
    public static function getDefaultShowMedalId($studentId)
    {
        $sql = "select * from (
select ROW_NUMBER()over(partition by g.category_id order by g.extension->>'$.level' desc) row_num,
m.medal_id from " . self::$table . " c
inner join " . StudentMedalModel::$table . " m on c.student_id =:student_id
and c.is_default = " . self::DEFAULT_SHOW . "
and c.medal_category_id = m.medal_category_id
inner join " . GoodsV1Model::$table . " g on m.medal_id = g.id
where m.student_id =:student_id
) as tmp where tmp.row_num = 1";
        return MysqlDB::getDB()->queryAll($sql, [':student_id' => $studentId]);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentMedalModel extends Model
{
    public static $table = 'student_medal';

    const IS_ACTIVE_SHOW = 1; //已弹出
    const NOT_ACTIVE_SHOW = 0; //未弹出

    /**
     * @param $studentId
     * @return array|null
     * 异步获得尚未弹出告知用户的奖章信息(同类取等级最高)
     */
    public static function getNeedAlertMedalInfo($studentId)
    {
        $sql = "select * from (
select ROW_NUMBER()over(PARTITION by g.category_id 
order by g.extension->>'$.level' desc) as row_num, 
m.medal_id,g.category_id
from " . self::$table . " m
inner join " . GoodsV1Model::$table . " g on m.medal_id = g.id
where m.student_id =:student_id and m.is_show = " . self::NOT_ACTIVE_SHOW . "
) as t where row_num = 1;";
        $map[':student_id'] = $studentId;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    /**
     * @param $studentId
     * @return array|null
     * 外在展示用户奖章信息(获取时间倒序，同类别等级最高)
     */
    public static function showStudentMedalInfo($studentId)
    {
        $sql = "select * from (
select ROW_NUMBER()over(PARTITION by m.medal_category_id order by g.extension->>'$.level' desc) row_num, 
 m.medal_id, m.create_time 
 from " . self::$table . " m
 inner join " . GoodsV1Model::$table . " g on m.medal_id = g.id
 where m.student_id =:student_id
 )as tmp where tmp.row_num = 1 order by tmp.create_time desc";
        $map[':student_id'] = $studentId;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }
}
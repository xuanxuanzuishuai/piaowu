<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-08-10 18:13:00
 * Time: 上午11:49
 */

namespace App\Models\CHModel;

class AprViewLessonModel extends CHOBModel
{
    /**
     * ai_play_record -- lesson id有序表
     * MySQL更新会插入一条新数据，以字段ts来区分相同id数据的有效性
     */
    public static $table = "apr_view_lesson_all";
}

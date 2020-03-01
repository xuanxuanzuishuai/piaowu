<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Models;
class CollectionCourseModel extends Model
{
    //表名称
    public static $table = "collection_course";
    //开放状态:1未开放 2已开放
    const COLLECTION_COURSE_STATUS_NOT_PUBLISH = 1;
    const COLLECTION_COURSE_STATUS_IS_PUBLISH = 2;
}
<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/11
 * Time: 17:45
 */
namespace App\Models\Dss;

class DssCollectionModel extends DssModel
{
    public static $table = "collection";
    //开放状态:1未开放 2已开放
    const STATUS_NOT_PUBLISH = 1;
    const STATUS_IS_PUBLISH  = 2;

    //集合类型1普通集合2公共集合
    const TYPE_NORMAL = 1;
    const TYPE_PUBLIC = 2;
    //集合中学员数量上限
    const MAX_CAPACITY = 500;

    //班级授课类型 1体验课2正式课3全部课程
    const TEACHING_TYPE_TRIAL  = 1;
    const TEACHING_TYPE_NORMAL = 2;
}
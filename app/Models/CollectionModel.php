<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Models;
class CollectionModel extends Model
{
    //表名称
    public static $table = "collection";
    //开放状态:1未开放 2已开放
    const COLLECTION_STATUS_NOT_PUBLISH = 1;
    const COLLECTION_STATUS_IS_PUBLISH = 2;
    //班级状态
    //1、待组班：当前日期还未进入班级组班期，即在组班期开始日之前。
    //2、组班中：当前日期进入班级组班期，即当前日期在组班期中。
    //3、待开班：当前日期出了班级组班期，但还未进入开班期。
    //4、开班中：当前日期进入班级开班期，即当前日期在开班期中。
    //5、已结班：当前日期出了班级开班期，即当前日期在开班期截止日期之后。
    const COLLECTION_PREPARE_STATUS = 1;
    const COLLECTION_READY_TO_GO_STATUS = 2;
    const COLLECTION_WAIT_OPEN_STATUS = 3;
    const COLLECTION_OPENING_STATUS = 4;
    const COLLECTION_END_STATUS = 5;
    //集合类型1普通集合2公共集合
    const COLLECTION_TYPE_NORMAL = 1;
    const COLLECTION_TYPE_PUBLIC = 2;
}
<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;
//use Intervention\Image\ImageManagerStatic as Image;

class OrganizationModel extends Model
{
    public static $table = "organization";

    const STATUS_NORMAL = 1; //正常
    const STATUS_STOP = 0; //停用

    const ORG_ID_INTERNAL = 0; //内部角色固定org_id
    const ORG_ID_DIRECT = 1; //直营角色固定org_id
    
    /** 根据ID查询一条机构记录
     * @param $orgId
     * @return array|null
     */
    public static function getInfo($orgId)
    {
        $db = MysqlDB::getDB();
        $records = $db->queryAll("select o.*,o2.name parent_name from organization o left join 
        organization o2 on o.parent_id = o2.id where o.id = :org_id",[':org_id' => $orgId]);
        return empty($records) ? [] : $records[0];
    }
}

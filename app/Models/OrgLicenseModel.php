<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/27
 * Time: 5:24 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;

class OrgLicenseModel extends Model
{
    public static $table = "org_license";

    /**
     * 获取可用license数量
     * TODO 数量不用及时更新，可以缓存
     * @param $orgId
     * @return number
     */
    public static function getValidNum($orgId)
    {
        $db = MysqlDB::getDB();

        $now = time();
        $num = $db->sum(self::$table, 'license_num', [
            'org_id' => $orgId,
            'status' => Constants::STATUS_TRUE,
            'active_time[<]' => $now,
            'expire_time[>]' => $now,
        ]);

        return $num;
    }
}
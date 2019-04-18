<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/3/22
 * Time: 10:49 AM
 */

namespace ERP\Models;

use App\Libs\MysqlDB;

class AccessControlModel
{
    static $table = 'access_control';

    const STATUS_DENY = 0;
    const STATUS_ALLOW = 1;

    const STATUS_TYPE_MAIN = 'status'; // 总限制状态，优先级最高，在ERP后台设置
    const STATUS_TYPE_CLASSROOM = 'classroom_status'; // 教室后端设置的优先级

    const CACHE_PRI = 'AC_';

    public static function getByMobile($mobile)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['mobile' => $mobile]);
    }

    public static function getStatus($mobile)
    {
        $acData = self::getByMobile($mobile);
        if (empty($acData)) {
            return true;
        }

        if ($acData[self::STATUS_TYPE_MAIN] == self::STATUS_ALLOW
            && $acData[self::STATUS_TYPE_CLASSROOM] == self::STATUS_ALLOW) {
            return true;
        }

        return false;
    }
}
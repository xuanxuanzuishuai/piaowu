<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/3/5
 * Time: 11:45 AM
 */

namespace App\Models;

use App\Libs\MysqlDB;

/**
 * app版本
 * Class AppVersionModel
 * @package ERP\Models
 *
 * 版本修改在panda-service里，这里不会收到通知，所以不能缓存
 */

class AppVersionModel
{
    const APP_TYPE_STUDENT = 'aiappstudent';
    const APP_TYPE_TEACHER = 'aiappteacher';

    protected static $table = 'pb_app_version';

    public static function lastVersion($platformId, $appType = self::APP_TYPE_STUDENT)
    {
        $db = MysqlDB::getDB();
        $version = $db->get(self::$table,
            '*',
            [
                'platform' => $platformId,
                'apptype' => $appType,
                'status' => 1,
                'ORDER' => ['version' => 'DESC']
            ]
        );

        return $version;
    }
}
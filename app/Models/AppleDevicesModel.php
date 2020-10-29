<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Services\ChannelService;
use App\Services\WeChatService;

class AppleDevicesModel extends Model
{
    public static $table = 'apple_devices';

    public static function getAppleDevicesMap()
    {
        $result = self::getRecords([], ['apple_code', 'apple_model'], false);
        foreach ($result as $value) {
            $map[$value['apple_code']] = $value['apple_model'];
        }
        return $map ?? [];
    }
}
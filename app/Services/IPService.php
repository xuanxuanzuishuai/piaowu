<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/31
 * Time: 5:20 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Util;

class IPService
{
    /**
     * 检查ip是否在对应服务的白名单内
     * @param $ip
     * @param $service
     * @return bool
     */
    public static function validate($ip, $service)
    {
        $ipWhiteList = trim(DictConstants::get(DictConstants::IP_WHITE_LIST, $service));
        if (is_array($ipWhiteList)) {
            $ipWhiteList = implode(',', $ipWhiteList);
        }
        $ipWhiteList = explode(',', $ipWhiteList);
        return Util::checkIp($ip, $ipWhiteList);
    }
}
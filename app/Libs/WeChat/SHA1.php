<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/4
 * Time: 上午10:25
 */

namespace App\Libs\WeChat;

/**
 * SHA1 class
 *
 * 计算公众平台的消息签名接口.
 */
class SHA1
{
    public static function getSHA1(array $array)
    {
        sort($array, SORT_STRING);
        $str = implode($array);
        return sha1($str);
    }
}

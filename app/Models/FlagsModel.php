<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/3
 * Time: 2:54 PM
 */

namespace App\Models;


use App\Libs\Constants;

class FlagsModel extends Model
{
    static $table = 'flags';

    static $hashCachePri = 'hash';

    public static function getHash()
    {
        $flags = FlagsModel::getRecords(['status' => Constants::STATUS_TRUE], ['id', 'name'], false);
        $hash = array_combine(array_column($flags, 'id'), array_column($flags, 'name'));
        return $hash;
    }
}
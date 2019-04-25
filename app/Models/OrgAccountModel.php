<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:58 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class OrgAccountModel extends Model
{
    static $table = 'org_account';

    public static function getByAccount($account)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['account' => $account]);
    }

}
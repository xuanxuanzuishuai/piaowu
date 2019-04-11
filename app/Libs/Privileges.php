<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2017/9/1
 * Time: 下午1:43
 */

namespace App\Libs;

class Privileges
{
    private static $excludes = [
        '/employee/auth/usercenterurl',
        '/employee/auth/tokenlogin',
        '/api/alioss/callback'
    ];

    public static function isAdminExclude($path)
    {
        foreach (self::$excludes as $exclude) {
            $str = str_ireplace('/', '\/', $exclude);
            if (preg_match('/' . $str . '/', $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $path
     * @return bool
     */
    public static function isAPICheck($path){
        $apiCheckUriPrefixs = [
            '/api/app/',
            '/crm/',
            '/pay_callback/',
            '/referee/',
            '/api/consumer/',
            '/area/'
        ];

        $apiCheck = false;
        foreach ($apiCheckUriPrefixs as $prefix){
            if (strpos($path, $prefix) === 0){
                $apiCheck = true;
                break;
            }
        }
        return $apiCheck;
    }
}

<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-05-14
 * Time: 10:36
 */

namespace App\Libs;


class MsgPipe
{
    private static $msgPipe;
    private $msg = null;

    /**
     * @return MsgPipe
     */
    public static function getMsgPipe()
    {
        if (self::$msgPipe == null) {
            self::$msgPipe = new MsgPipe();
        }

        return self::$msgPipe;
    }

    /**
     * @param $key
     * @param $msg
     * @return bool
     */
    public static function setMsg($key, $msg)
    {
        self::getMsgPipe()->msg[$key] = $msg;
        return true;
    }

    /**
     * @param $key
     * @return mixed
     */
    public static function getMsg($key)
    {
        return self::getMsgPipe()->msg[$key];
    }
}
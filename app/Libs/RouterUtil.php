<?php

namespace App\Libs;

/**
 * Slim路由工具辅助
 * @author tianye@xiaoyezi.com
 * @since 2017-04-10 13:46:36
 */
class RouterUtil
{
    /**
     * 根据请求的URL获取访问的子系统类型
     */
    public static function getSYSTypeBYRequestURI()
    {

        $path = $_SERVER['REQUEST_URI'];
        $path = explode('?', $path);
        $path = $path[0];
        $urlItems = explode('/', $path);

        return $urlItems[1];

    }

    /**
     * 获取当前Router的URL
     */
    public static function getCurrentURL()
    {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }
}

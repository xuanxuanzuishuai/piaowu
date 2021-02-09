<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 15:59
 */

namespace App\Libs\EventListener;

/**
 * 监听器基础类
 * Class Listener
 * @package App\Libs\EventListener
 */
abstract class Listener
{
    /**
     * 默认监听
     * @param Event $event
     * @return mixed
     */
    abstract public function handle(Event $event);

    /**
     * 监听
     * @param Event $event
     * @param Listener $listener
     * @param string $handle
     * @return mixed
     */
    public static function listen(Event $event, Listener $listener, $handle = 'handle')
    {
        return call_user_func_array([$listener, $handle], [$event]);
    }
}
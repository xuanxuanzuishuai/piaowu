<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/2/9
 * Time: 6:06 PM
 */

namespace App\Libs\EventListener;


use App\Libs\SimpleLogger;

/**
 * 事件基础类
 * Class Event
 * @package App\Libs\EventListener
 */
class Event
{
    /**
     * @var mixed 事件透传数据
     */
    protected $payload = null;

    /**
     * Event constructor.
     * @param $payload
     */
    function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * @return mixed 事件透传数据
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * 触发事件
     * @param Event $event
     */
    public static function fire(Event $event)
    {
        $ret = [];
        try {
            $listeners = ListenerProvider::getInstance()->getListening($event);
            foreach ($listeners as $lk => $listener) {
                if (empty($listener)) {
                    continue;
                }
                if (count($listener) < 2) {
                    $listener[] = "handle"; //默认handle方法
                }
                list($listenerClassName, $listenerHandleName) = $listener;
                //通过反射机制建立 =>注册的监听器<= 这个类的反射类
                $class = new \ReflectionClass($listenerClassName);
                //相当于实例化类，得到对象
                $listenerObject = $class->newInstanceArgs();
                //触发监听
                $ret[$lk]['res'] = Listener::listen($event, $listenerObject, $listenerHandleName);
                $ret[$lk]['listener'] = $listener;
            }
        } catch (\Exception $e) {

        }
        SimpleLogger::info('event listener run res:', $ret);
    }
}
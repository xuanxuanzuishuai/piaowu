<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 15:59
 */

namespace App\Libs\EventListener;

use App\Libs\SimpleLogger;
use \Exception;

class ListenerProvider
{
    protected static $instance;

    /**
     * 事件监听关联数组
     * @var array
     */
    protected $listens = [
        //事件 一对多关联监听器
        'App\Libs\EventListener\AgentOpEvent' => [
            [AgentOpListener::class, 'handle']
        ],
    ];

    /**
     * 私有的构造方法，禁止外部直接实例化，保证对象全局唯一
     * ListenerProvider constructor.
     */
    private function __construct()
    {
        //todo
    }

    /**
     * 获取对象：全局单例
     * @return ListenerProvider
     */
    static public function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }
        self::$instance = new ListenerProvider();
        return self::$instance;
    }

    /**
     * 获取监听列表
     * @param Event $event
     * @return array|mixed
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function getListening(Event $event)
    {
        //获取事件的类全称：包括命名空间
        $event_name = get_class($event);
        if (!array_key_exists($event_name, $this->listens)) {
            SimpleLogger::error('event not set listener', []);
            return [];
        }
        //获取当前事件已注册的事件监听列表
        $listeners = $this->listens[$event_name];
        if (empty($listeners)) {
            return [];
        }
        //检测事件监听类是否正确：1。监听器类继承关系    2。监听器的处理方法是否存在
        foreach ($listeners as $listener) {
            list($listenerClassName, $listenerHandleName) = $listener;
            $class = new \ReflectionClass($listenerClassName);
            if (!$class->isSubclassOf(Listener::class)) {
                throw new \Exception($listenerClassName . " is not one instance of Listener");
            }

            if (!$class->hasMethod($listenerHandleName)) {
                throw new Exception("undefined method: " . $listenerHandleName);
            }
        }
        return $listeners;
    }
}
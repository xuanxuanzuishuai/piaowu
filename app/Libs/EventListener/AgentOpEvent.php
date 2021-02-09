<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 15:59
 */

namespace App\Libs\EventListener;


/**
 * 代理商账户后台操作事件
 * Class AgentOpEvent
 * @package App\Libs\EventListener
 */
class AgentOpEvent extends Event
{
    public function __construct($order)
    {
        parent::__construct($order);
    }
}
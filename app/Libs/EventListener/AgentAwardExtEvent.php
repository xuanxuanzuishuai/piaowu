<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 15:59
 */

namespace App\Libs\EventListener;


/**
 * 代理商奖励扩展信息处理事件
 * Class AgentOpEvent
 * @package App\Libs\EventListener
 */
class AgentAwardExtEvent extends Event
{
    public function __construct($awardData)
    {
        parent::__construct($awardData);
    }
}
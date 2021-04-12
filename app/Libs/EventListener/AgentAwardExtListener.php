<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 15:59
 */

namespace App\Libs\EventListener;

use App\Libs\SimpleLogger;
use App\Models\AgentOperationLogModel;
use App\Services\AgentAwardExtService;

/**
 * 代理商奖励扩展信息处理事件监听器
 * Class AgentOpListener
 * @package App\Libs\EventListener
 */
class AgentAwardExtListener extends Listener
{
    /**
     * 事件处理
     * @param Event $event
     * @return mixed
     */
    public function handle(Event $event)
    {
        $contents = $event->getPayload();
        if (empty($contents)) {
            SimpleLogger::error('agent award data empty', []);
            return false;
        }
        return AgentAwardExtService::addAgentAwardExtData($contents['agent_award_detail_id']);
    }
}
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

/**
 * 代理商账户后台操作事件监听器
 * Class AgentOpListener
 * @package App\Libs\EventListener
 */
class AgentOpListener extends Listener
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
            SimpleLogger::error('valid log data', []);
            return false;
        }
        return AgentOperationLogModel::recordOpLog($contents, $contents['operator_id'], $contents['op_type']);
    }
}
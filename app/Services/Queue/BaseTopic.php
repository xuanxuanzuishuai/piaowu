<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:19 AM
 */

namespace App\Services\Queue;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\NsqLookUpdLib;
use App\Libs\NsqProducerLib;
use App\Libs\SimpleLogger;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class BaseTopic
{
    private $topicName;
    private $sourceAppId;
    private $publishTime;
    private $execTime;
    private $eventType;
    private $msgBody;
    private $nsqd;

    /**
     * BaseTopic constructor.
     * @param $topicName
     * @param null $publishTime
     * @param int $sourceAppId
     * @throws Exception
     */
    protected function __construct($topicName, $publishTime = null, $sourceAppId = QueueService::FROM_OP)
    {
        //获取消息队列topic前缀和主机地址
        list($prefix, $lookupds) = DictConstants::getValues(DictConstants::QUEUE_CONFIG, ['NSQ_TOPIC_PREFIX', 'NSQ_LOOKUPS']);
        //队列参数
        $this->topicName = $prefix . $topicName;
        $this->sourceAppId = $sourceAppId;
        $this->publishTime = $publishTime;
        //解析主机，随机选择一台服务器
        $arrLookupHost = explode(",", $lookupds);
        SimpleLogger::debug("Found lookupds:", $arrLookupHost);
        $lookUpdAddr = $arrLookupHost[array_rand($arrLookupHost, 1)];
        SimpleLogger::debug("Used lookupd:", [$lookUpdAddr]);
        //创建NSQ链接对象
        $nsqObj = new NsqLookUpdLib($lookUpdAddr);
        try {
            $arrNsqHost = array_map(function ($v) {
                return $v['broadcast_address'] . ':' . $v['tcp_port'];
            }, $nsqObj->nodes()['producers']);

        } catch (GuzzleException $e) {
            throw new Exception("LOOKUP_NODES error:" . $e->getMessage());
        }

        SimpleLogger::info('NSQ HOST(s)', $arrNsqHost);
        //创建nsq生产者
        $this->nsqd = new NsqProducerLib($arrNsqHost, $_ENV['LOG_FILE_PATH']);
    }

    /**
     * 设置事件类型
     * @param $eventType
     */
    protected function setEventType($eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * 设置消息体
     * @param $msgBody
     */
    protected function setMsgBody($msgBody)
    {
        $this->msgBody = $msgBody;
    }

    /**
     * 获取投递数据参数
     * @return array
     */
    private function getData()
    {
        $data = [
            'topic_name' => $this->topicName,
            'source_app_id' => $this->sourceAppId,
            'publish_time' => $this->publishTime,
            'exec_time' => $this->execTime,
            'event_type' => $this->eventType,
            'msg_body' => $this->msgBody
        ];
        return $data;
    }

    /**
     * 投递消息到队列
     * @param int $deferTime
     * @throws Exception
     */
    public function publish($deferTime = 0)
    {
        if (empty($this->eventType)) {
            throw new Exception("EventType not set!");
        }
        if (empty($this->msgBody)) {
            throw new Exception("MsgBody not set!");
        }
        if ($deferTime > 0) {
            $this->publishTime = time();
            $this->execTime = time() + $deferTime;
            $this->nsqd->publishDefer($this->topicName, $this->getData(), $deferTime);
        } else {
            $this->publishTime = $this->execTime = time();
            $this->nsqd->publish($this->topicName, $this->getData());
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:21 AM
 */

namespace App\Libs;

use OkStuff\PhpNsq\PhpNsq;

class NsqProducerLib
{

    /**
     * NSQ主机地址（包含协议和端口）
     */
    private $nsqdAddrs = [];
    private $logPath = '/tmp';

    /**
     * NsqdLib constructor.
     * @param array $nsqdAddrs
     * @param string $logPath
     */
    public function __construct($nsqdAddrs, $logPath = '/tmp')
    {
        $this->nsqdAddrs = $nsqdAddrs;
        $this->logPath = $logPath;
    }

    /**
     * 发布消息服务器配置
     * @return array
     */
    private function config()
    {
        $config = [
            'nsq' => [
                'nsqd-addrs' => $this->nsqdAddrs,
                'logdir' => $this->logPath,
            ]
        ];
        return $config;
    }

    /**
     * 发布消息
     * @param $topicName
     * @param $data
     */
    public function publish($topicName, $data)
    {
        $phpnsq = new PhpNsq($this->config());
        $message = json_encode($data);
        SimpleLogger::info("QUEUE", array($message));
        $phpnsq->setTopic($topicName)->publish($message);
    }

    /**
     * 延迟发布消息
     * @param $topicName
     * @param $data
     * @param $deferTime int 延时时间（秒）
     */
    public function publishDefer($topicName, $data, $deferTime)
    {
        $phpNsq = new PhpNsq($this->config());
        $message = json_encode($data);
        // SimpleLogger::info("QUEUE", array($message));
        //
        $phpNsq->setTopic($topicName)->publishDefer($message, $deferTime*1000);
    }

    /**
     * 批量发布消息
     * @param $topicName
     * @param array ...$data
     */
    public function publishMulti($topicName, ...$data)
    {
        $phpNsq = new PhpNsq($this->config());
        SimpleLogger::info("QUEUE", ...$data);
        $phpNsq->setTopic($topicName)->publishMulti(...$data);
    }

}
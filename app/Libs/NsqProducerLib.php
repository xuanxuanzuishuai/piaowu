<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/11/10
 * Time: 6:09 PM
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
     */
    public function __construct($nsqdAddrs, $logPath = '/tmp')
    {
        $this->nsqdAddrs = $nsqdAddrs;
        $this->logPath = $logPath;
    }

    /**
     * 发布消息用服务器配置
     * @return array
     * @throws \Exception
     */
    private function config(){
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
    public function publish($topicName, $data){

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
    public function publishDefer($topicName, $data, $deferTime){
        $phpnsq = new PhpNsq($this->config());
        $message = json_encode($data);
        SimpleLogger::info("QUEUE", array($message));
        $phpnsq->setTopic($topicName)->publishDefer($message,$deferTime);
    }

    /**
     * 批量发布消息
     * @param $topicName
     * @param array ...$datas
     */
    public function publishMulti($topicName, ...$datas){
        $phpnsq = new PhpNsq($this->config());
        SimpleLogger::info("QUEUE", ...$datas);
        $phpnsq->setTopic($topicName)->publishMulti(...$datas);
    }

}
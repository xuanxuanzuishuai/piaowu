<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:21 AM
 */

namespace App\Libs;


use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class NsqLookUpdLib
{
    const TOPIC_CREATE = '/topic/create?topic=%s';
    const TOPIC_DELETE = '/topic/delete?topic=%s';
    const TOPIC_EMPTY = '/topic/empty?topic=%s';
    const TOPIC_PAUSE = '/topic/pause?topic=%s';
    const TOPIC_UNPAUSE = '/topic/unpause?topic=%s';
    const CHANNEL_CREATE = '/channel/create?topic=%s&channel=%s';
    const CHANNEL_DELETE = '/channel/delete?topic=%s&channel=%s';
    const CHANNEL_EMPTY = '/channel/empty?topic=%s&channel=%s';
    const CHANNEL_PAUSE = '/channel/pause?topic=%s&channel=%s';
    const CHANNEL_UNPAUSE = '/channel/unpause?topic=%s&channel=%s';

    const LOOKUP_NODES = '/nodes';
    const LOOKUP_TOPICS = '/topics';
    const LOOKUP_LOOKUP = '/lookup?topic=%s';
    const LOOKUP_TOMBSTONE = '/topic/tombstone?topic=%s&node=%s';
    const LOOKUP_INFO = '/info';

    /**
     *  NSQ主机地址（包含协议和端口）
     */
    private $nsqlookupHost;

    /**
     * NsqLib constructor.
     * @param string $nsqlookupdHost NSQLookup主机地址（包含协议和端口)
     */
    public function __construct($nsqlookupdHost)
    {
        $this->nsqlookupHost = $nsqlookupdHost;
    }

    /**
     * @param $api
     * @param string $method
     * @param array $data
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function commonAPI($api, $method = 'POST', $data = [])
    {
        try {
            $client = new Client(['debug' => false]);
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "data" => $data]);
            $response = $client->request($method, $api, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "body" => $body, "status" => $status]);

            if (StatusCode::HTTP_OK == $status) {
                $res = json_decode($body, true);
                if (!empty($res['meta']) && $res['meta']['status'] !== 0) {
                    SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);
                    return false;
                }
                return $res;
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
                return false;
            }

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e, true)]);
        }
        return false;
    }

    /**
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createTopic($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::TOPIC_CREATE, $topicName);
        return $this->commonAPI($url);
    }

    /**
     * 删除Topic
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteTopic($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::TOPIC_DELETE, $topicName);
        return $this->commonAPI($url);
    }

    /**
     * 清空Topic
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function emptyTopic($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::TOPIC_EMPTY, $topicName);
        return $this->commonAPI($url);
    }

    /**
     * 暂停Topic
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function pauseTopic($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::TOPIC_PAUSE, $topicName);
        return $this->commonAPI($url);
    }


    /**
     * 继续Topic
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function unpauseTopic($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::TOPIC_UNPAUSE, $topicName);
        return $this->commonAPI($url);
    }

    /**
     * 创建Channel
     * @param $topicName
     * @param $channelName
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createChannel($topicName, $channelName)
    {
        $url = sprintf($this->nsqlookupHost . self::CHANNEL_CREATE, $topicName, $channelName);
        $this->commonAPI($url);
    }

    /**
     * 删除Channel
     * @param $topicName
     * @param $channelName
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteChannel($topicName, $channelName)
    {
        $url = sprintf($this->nsqlookupHost . self::CHANNEL_DELETE, $topicName, $channelName);
        $this->commonAPI($url);
    }

    /**
     * 清空Channel消息
     * @param $topicName
     * @param $channelName
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function emptyChannel($topicName, $channelName)
    {
        $url = sprintf($this->nsqlookupHost . self::CHANNEL_EMPTY, $topicName, $channelName);
        $this->commonAPI($url);
    }

    /**
     * 暂停Channel
     * @param $topicName
     * @param $channelName
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function pauseChannel($topicName, $channelName)
    {
        $url = sprintf($this->nsqlookupHost . self::CHANNEL_PAUSE, $topicName, $channelName);
        $this->commonAPI($url);
    }

    /**
     * 继续Channel
     * @param $topicName
     * @param $channelName
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function unpauseChannel($topicName, $channelName)
    {
        $url = sprintf($this->nsqlookupHost . self::CHANNEL_UNPAUSE, $topicName, $channelName);
        $this->commonAPI($url);
    }

    /**
     * NSQ节点
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function nodes()
    {
        $url = sprintf($this->nsqlookupHost . self::LOOKUP_NODES);
        return $this->commonAPI($url, 'GET');
    }

    /**
     * 查看Topics
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function topics()
    {
        $url = sprintf($this->nsqlookupHost . self::LOOKUP_TOPICS);
        return $this->commonAPI($url, 'GET');
    }

    /**
     * 查询某个Topic的所有Procedures
     * @param $topicName
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function lookup($topicName)
    {
        $url = sprintf($this->nsqlookupHost . self::LOOKUP_LOOKUP, $topicName);
        return $this->commonAPI($url, 'GET');
    }

    /**
     * 禁用Topic的某个Producer
     * @param $topic
     * @param $node
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function tombstone($topic, $node)
    {
        $url = sprintf($this->nsqlookupHost . self::LOOKUP_TOMBSTONE, $topic, $node);
        return $this->commonAPI($url);
    }

    /**
     * 版本信息
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function info()
    {
        $url = $this->nsqlookupHost . self::LOOKUP_INFO;
        return $this->commonAPI($url, 'GET');
    }

}
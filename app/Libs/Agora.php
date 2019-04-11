<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/12/2
 * Time: 下午6:13
 */

namespace App\Libs;



use App\Services\DictService;
use GuzzleHttp\Client;

class Agora
{
    private static $URL_VIDEO_INFO = '/api/1.0/video/info';
    private $client;

    public function __construct()
    {
        if (empty($this->client))
            $this->client = new Client();
    }

    /**
     * 获取录制视频地址
     * @param $scheduleId
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getVideoInfo($scheduleId)
    {
        $url = DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::AGORA_RECORDER_URL);

        $res = $this->client->request("POST", $url . self::$URL_VIDEO_INFO, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['channel' => $scheduleId])
        ]);
        $responseCode = $res->getStatusCode();
        if (200 == $responseCode) {
            $body = $res->getBody();
            $data = json_decode($body, true);

            if (isset($data['meta']['code']) && $data['meta']['code'] == 0) {
                $tz = new \DateTimeZone('Asia/Shanghai');
                $utc = new \DateTimeZone('UTC');
                foreach ($data['data']['videos'] as &$videoUrl) {
                    $videoName = preg_replace_callback('/(\d+\/\d+_\d+\/\d+_)(\d+)(\.mp4)/', function ($parts) use (&$tz, &$utc) {
                        $date = \DateTime::createFromFormat('YmdHisu', $parts[2], $utc);
                        return $parts[1] . $date->setTimezone($tz)->format('YmdHisv') . $parts[3];
                    }, $videoUrl);
                    $videoUrl = [
                        'url' => $data['data']['base_url'] . '/' . $videoUrl,
                        'name' => $data['data']['base_url'] . '/' . $videoName,
                    ];
                }
                return $data['data'];

            } else {
                SimpleLogger::error(__FILE__ . ':' . __LINE__, ['schedule_id' => $scheduleId, 'data' => $data]);
                return ['errors' => [$data['meta']]];
            }
        } else {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, ['schedule_id' => $scheduleId]);
            SimpleLogger::error(__FILE__ . ':' . __LINE__, ['body' => "agora record fail" . $scheduleId . $responseCode]);
            return ['errors' => ['code' => $responseCode]];
        }
    }
}
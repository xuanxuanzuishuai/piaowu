<?php
namespace App\Libs;
use AlibabaCloud\Client\AlibabaCloud;
class AliContentCheck
{
    // 设置待检测的图片，一张图片对应一个检测任务。
    // 多张图片同时检测时，处理时间由最后一张处理完的图片决定。
    // 通常情况下批量检测的平均响应时间比单张检测要长。一次批量提交的图片数越多，响应时间被拉长的概率越高。
    // 代码中以单张图片检测作为示例，如果需要批量检测多张图片，请自行构建多个检测任务。
    // 一次请求中可以同时检测多张图片，每张图片可以同时检测多个风险场景，计费按照单图片单场景检测叠加计算。
    // 例如：检测2张图片，场景传递porn和terrorism，则计费按照2张图片鉴黄和2张图片暴恐检测计算。
    const ILLEGAL_RESULT = 'block';
    public $ossConfig;

    public function __construct()
    {
        $this->ossConfig = DictConstants::getSet(DictConstants::ALI_OSS_CONFIG);
        AlibabaCloud::accessKeyClient($this->ossConfig['green_access_key_id'], $this->ossConfig['green_access_secret'])
            ->regionId($this->ossConfig['green_region_id'])// 设置客户端区域，使用该客户端且没有单独设置的请求都使用此设置
            ->asDefaultClient();
    }

    /***
     * @param $url
     * @return mixed
     * 图片检测
     */
    public function checkImgLegal($url)
    {
        $task = array(
            'dataId' => uniqid(),
            'url' => $url
        );
        $body = array(
            "tasks" => array($task),
            "scenes" => explode(',', $this->ossConfig['green_check_type'])
        );
        $params = array();
        return $this->toRequest('/green/image/scan', $body, $params);
    }

    /***
     * @param $content
     * @return \AlibabaCloud\Client\Result\Result|array
     * 文本检测
     */
    public function textScan($content)
    {
        $task = array(
            'dataId' => uniqid(),
            'content' => $content
        );
        $body = array(
            "tasks" => array($task),
            "scenes" => explode(',', $this->ossConfig['green_check_type'])
        );
        $params = array();
        return $this->toRequest('/green/text/scan', $body, $params);
    }

    /**
     * @param $action
     * @param $body
     * @param $params
     * @return \AlibabaCloud\Client\Result\Result|array
     * 执行请求，解析数据
     */
    private function toRequest($action, $body, $params)
    {
        $returnInfo = [];
        try {
            $result = AlibabaCloud::roaRequest()
                ->product('Green')
                ->version('2018-05-09')
                ->pathPattern($action)
                ->method('POST')
                ->options([
                    'query' => $params
                ])
                ->body(json_encode($body))
                ->request();
            if ($result->isSuccess()) {
                $resultInfo = $result->toArray();
                return array_column($resultInfo['data'][0]['results'], 'suggestion', 'scene');
            } else {
                SimpleLogger::info('request green img fail:', ['return info' => $result->toArray()]);
                return $result;
            }
        } catch (\Exception $e) {
            SimpleLogger::info('green img error:', ['exception' => $e]);
        }
        SimpleLogger::info('img check result info:', ['info' => $returnInfo]);
        return $returnInfo;
    }
}



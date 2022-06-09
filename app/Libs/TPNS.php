<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/1/18
 * Time: 11:21 上午
 */

namespace App\Libs;

use App\Models\Dss\DssPushDeviceModel;
use App\Models\PushRecordModel;
use App\Models\Dss\DssStudentModel;
use App\Services\PushServices;
use App\Services\Queue\QueueService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class TPNS
{
    const TPNS_API_PUSH = '/v3/push/app';   //消息推送接口

    const ACTION_TYPE_DEFAULT = 3;          //安卓默认推送动作类型

    const PLATFORM_ANDROID = 1;             //安卓设备
    const PLATFORM_IOS = 2;                 //ios设备

    const MAX_TOKEN_LIST = 1000;            //单次推送最大设备数

    /**
     * @param $platform
     * @param $data
     * @param $service
     * @return array
     * 消息推动计算签名并放入请求头中
     */
    public static function getRequestHeader($platform, $data, $service)
    {
        $timeStamp = time();
        $accessId = $secretKey = '';
        if ($platform == self::PLATFORM_ANDROID) {
            $accessId = $service['access_id_android'];
            $secretKey = $service['secret_key_android'];
        } elseif ($platform == self::PLATFORM_IOS) {
            $accessId = $service['access_id_ios'];
            $secretKey = $service['secret_key_ios'];
        }

        $preSign = $timeStamp . $accessId . json_encode($data['json']);
        $sign = base64_encode(hash_hmac('sha256', $preSign, $secretKey));

        return [
            'Content-Type' => 'application/json',
            'TimeStamp'    => $timeStamp,
            'AccessId'     => $accessId,
            'Sign'         => $sign,
        ];
    }

    /**
     * @param $platform
     * @param $api
     * @param array $data
     * @param string $method
     * @return bool|mixed
     */
    private static function commonAPI($platform, $api, $data = [], $method = 'POST')
    {
        $service = DictConstants::getSet(DictConstants::SERVICE);
        $serviceHost = $service['tpns_host'];
        $fullUrl = $serviceHost . $api;

        try {
            $client = new Client([
                'debug' => false
            ]);

            if ($method == 'GET') {
                $data = ['query' => $data];
            } elseif ($method == 'POST') {
                $data = ['json' => $data];
            }

            $data['headers'] = self::getRequestHeader($platform, $data, $service);
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $fullUrl, 'data' => $data]);
            $response = $client->request($method, $fullUrl, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'body' => $body, 'status' => $status]);

            if (StatusCode::HTTP_OK == $status) {
                $res = json_decode($body, true);
                if (!empty($res['ret_code']) && $res['ret_code'] !== Valid::CODE_SUCCESS) {
                    SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);
                    return $res;
                }
                return $res;
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
                $res = json_decode($body, true);
                return $res;
            }

        } catch (GuzzleException $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        return false;
    }

    /**
     * @param $params
     * @return void
     * 安卓和IOS双平台推送并记录推送数据
     */
    public static function push($params)
    {
        $android = $params['android'];
        $ios = $params['ios'];
        if (!empty($android)) {
            $resultAndroid = self::commonAPI(self::PLATFORM_ANDROID, self::TPNS_API_PUSH, $android);
        } else {
            $resultAndroid['push_id'] = '000000';
        }

        if (!empty($ios)) {
            $resultIos = self::commonAPI(self::PLATFORM_IOS, self::TPNS_API_PUSH, $ios);
        } else {
            $resultIos['push_id'] = '000000';
        }

        $recordData = [
            'jump_type'            => $params['insertData']['jump_type'],
            'push_content_android' => json_encode($android),
            'push_content_ios'     => json_encode($ios),
            'remark'               => $params['insertData']['remark'],
            'push_id_android'      => $resultAndroid['push_id'] ?? '',
            'push_id_ios'          => $resultIos['push_id'] ?? '',
            'create_time'          => time(),
        ];

        if (!empty($recordData)) {
            PushRecordModel::insertRecord($recordData);
        }

        return;
    }

    /**
     * @param $params
     * @return array
     * @throws Exceptions\RunTimeException
     * 整理推送公共参数部分并获取完整的推送TokenList
     */
    public static function getDeviceTokenList($params)
    {
        //全量用户或指定用户推送
        $deviceTokenList = $data = [];
        switch ($params['push_user_type']) {
            case PushServices::PUSH_USER_ALL:
                $data['audience_type'] = 'all';
                break;
            case PushServices::PUSH_USER_PART:
                $data['audience_type'] = 'token_list';
                $uuid = $params['uuid_arr'] ?? '';
                if (!empty($params['file_name'])) {
                    $uuid = PushServices::analysisExcel($params['file_name']);
                }
                $deviceTokenList = PushServices::getDeviceToken($uuid);
                break;
            case PushServices::PUSH_USER_PAY:
                $data['audience_type'] = 'token_list';
                $deviceTokenList = DssPushDeviceModel::getDeviceTokenByUserType(DssStudentModel::REVIEW_COURSE_1980);
                break;
            case PushServices::PUSH_USER_EXPERIENCE:
                $data['audience_type'] = 'token_list';
                $deviceTokenList = DssPushDeviceModel::getDeviceTokenByUserType(DssStudentModel::REVIEW_COURSE_49);
                break;
        }

        //整理通用参数
        $commonData = [
            'audience_type' => $data['audience_type'],
            'message_type'  => 'notify',
            'message'       => [
                'title'   => $params['push_title'],
                'content' => $params['push_content'],
            ],
        ];
        if (!empty($params['push_img_url'])) {
            $commonData['message']['xg_media_resources'] = $params['push_img_url'];
        }

        return [$deviceTokenList, $commonData];
    }

    /**
     * @param $params
     * @param $url
     * @return array|array[]
     * @throws Exceptions\RunTimeException
     * 整理安卓和IOS的完整参数
     */
    public static function formatParams($params, $url)
    {
        $androidList = $iosList = [];
        list($deviceTokenList, $commonData) = self::getDeviceTokenList($params);
        $android = $ios = $commonData;

        $android['message']['android'] = [
            'action' => [
                'action_type' => self::ACTION_TYPE_DEFAULT,
                'intent'      => $url
            ]
        ];

        $ios['environment'] = $_ENV['ENV_NAME'] == 'dev' ? 'dev' : 'product';
        $ios['message']['ios'] = [
            'custom_content' => json_encode(['key' => $url]),
        ];

        if ($commonData['audience_type'] == 'token_list') {
            $androidDeviceTimes = ceil(count($deviceTokenList['android']) / self::MAX_TOKEN_LIST);
            $iosDeviceTimes = ceil(count($deviceTokenList['ios']) / self::MAX_TOKEN_LIST);
            $circleTimes = $androidDeviceTimes > $iosDeviceTimes ? $androidDeviceTimes : $iosDeviceTimes;

            for ($i = 0; $i < $circleTimes; $i++) {
                $startIndex = $i * self::MAX_TOKEN_LIST;
                $android['token_list'] = array_slice($deviceTokenList['android'], $startIndex, self::MAX_TOKEN_LIST) ?? [];
                $ios['token_list'] = array_slice($deviceTokenList['ios'], $startIndex, self::MAX_TOKEN_LIST) ?? [];

                if (empty($android['token_list'])) {
                    $android = [];
                }

                if (empty($ios['token_list'])) {
                    $ios = [];
                }

                $androidList[] = $android;
                $iosList[] = $ios;
            }

        } else {
            $androidList[] = $android;
            $iosList[] = $ios;
        }

        return [$androidList, $iosList];
    }

    /**
     * @param $androidList
     * @param $iosList
     * @param $insertData
     * @return bool
     * 将需要推送的数据循环放入队列
     */
    public static function circlePush($androidList, $iosList, $insertData)
    {
        //安卓和IOS要推送的数量是一样的，此处使用安卓的推送消息数量作为循环推送标准
        $times = count($androidList);
        for ($i = 0; $i < $times; $i++) {
            $params = [
                'android'    => $androidList[$i],
                'ios'        => $iosList[$i],
                'insertData' => $insertData,
                'count'      => $times,
            ];
            QueueService::aiplPush($params);
        }

        return true;
    }

    /******************************类型推送**********************************************/

    /**
     * @param $params
     * @return bool
     * @throws Exceptions\RunTimeException
     * 首页推送
     */
    public static function homePagePush($params)
    {
        $url = 'aipeilian:///cocos?path=home';
        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_HOME_PAGE,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * webView推送
     * @throws Exceptions\RunTimeException
     */
    public static function webViewPush($params)
    {
        $url = 'aipeilian:///h5?path=' . urlencode($params['link_url'])."&jumpTo=".$params['jump_to'];
        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_WEB_VIEW,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * 浏览器链接推送
     * @throws Exceptions\RunTimeException
     */
    public static function browserPush($params)
    {
        $url = 'aipeilian:///browser?path=' . urlencode($params['link_url']);

        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_BROWSER,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * @throws Exceptions\RunTimeException
     * 小程序推送
     */
    public static function liteAppPush($params)
    {
        if (empty($params['link_url'])) {
            $url = 'aipeilian:///miniprogram?appId=' . $params['app_id'] . '&env=release';
        } else {
            $url = 'aipeilian:///miniprogram?appId=' . $params['app_id'] . '&path=' . urlencode($params['link_url']) . '&env=release';
        }

        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_LITE_APP,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * 音符商城推送
     * @throws Exceptions\RunTimeException
     */
    public static function musicNoteMallPush($params)
    {
        $url = 'aipeilian:///h5?path=' . urlencode($params['link_url']);
        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_MUSICAL_NOTE_MALL,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * 练琴日历
     * @throws Exceptions\RunTimeException
     */
    public static function playCalendarPush($params)
    {
        $url = 'aipeilian:///h5?path=' . urlencode($params['link_url']);
        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_PLAY_CALENDAR,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }

    /**
     * @param $params
     * @return bool
     * 套课详情页推动
     * @throws Exceptions\RunTimeException
     */
    public static function collectionDetailPush($params)
    {
        $url = 'aipeilian:///h5?path=' . urlencode($params['link_url']);
        list($androidList, $iosList) = self::formatParams($params, $url);

        $insertData = [
            'jump_type' => PushServices::PUSH_JUMP_TYPE_COLLECTION_DETAIL,
            'remark'    => $params['push_remark'] ?? '',
        ];
        return self::circlePush($androidList, $iosList, $insertData);
    }
}
<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: yangyijie
 * Date: 2019/2/27
 * Time: 4:35 PM
 */

namespace App\Libs;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use App\Models\AppConfigModel;

// Download：https://github.com/aliyun/openapi-sdk-php-client
// Usage：https://github.com/aliyun/openapi-sdk-php-client/blob/master/README-CN.md


class AliClient {

    private static $client;

    public static function init()
    {
        $accessKeyId = AppConfigModel::get('ALI_ACCESS_KEY_ID');
        $accessKeySecret = AppConfigModel::get('ALI_ACCESS_KEY_SECRET');
        $reginId = AppConfigModel::get('ALI_REGION_ID');
        if (empty(self::$client)) {
            self::$client = AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                ->regionId($reginId)
                ->asGlobalClient();
        }

        return self::$client;
    }

    /**
     * 获取STS临时授权
     *
     * @param string $roleArn 权限角色ARN，在OSS控制台管理
     * @param string $sessionName 区分用户
     * @param array $policy 授权策略
     * @return array [0]errorMessage [1]result
     *
result:
{
    "RequestId": "B91AAB6C-BA2B-4267-BF17-C5B43099D444",
    "AssumedRoleUser": {
        "AssumedRoleId": "307103336625622569:ai_peilian_1",
        "Arn": "acs:ram::1589000458122044:role/ai-peilian-app-role/ai_peilian_1"
    },
    "Credentials": {
        "AccessKeySecret": "54T9hHqUCdwA66DuufJY2ATKLj89it5ALkyJyDH9n45p",
        "AccessKeyId": "STS.NKEwBub85umPYShpTHTz3oUeK",
        "Expiration": "2019-02-26T10:16:00Z",
        "SecurityToken": "CAIS/gF1q6Ft5B2yfSjIr4jwPPjBj..."
    }
}
     */
    public static function assumeRole($roleArn, $sessionName, $policy = null)
    {
        if (empty(self::$client)) {
            self::init();
        }

        try {
            $result = AlibabaCloud::rpcRequest()
                ->product('Sts')
                ->scheme('https') // https | http
                ->version('2015-04-01')
                ->action('AssumeRole')
                ->method('POST')
                ->options([
                    'query' => [
                        'RoleArn' => $roleArn,
                        'RoleSessionName' => $sessionName,
                        'Policy' => json_encode($policy)
                    ],
                ])
                ->request();
            return [null, $result->toArray()];

        } catch (ClientException $e) {
            return [$e->getErrorMessage() . PHP_EOL];

        } catch (ServerException $e) {
            return [$e->getErrorMessage() . PHP_EOL];
        }
    }
}
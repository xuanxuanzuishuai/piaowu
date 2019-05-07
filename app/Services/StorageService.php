<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/26
 * Time: 6:57 PM
 */

namespace App\Services;


use App\Libs\AliClient;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Models\PlayRecordModel;

class StorageService
{
    const ALI_OSS_BUCKET = 'ai-peilian-app';

    /**
     * 获取指定演奏记录的保存文件的OSS访问授权
     *
     * @param $studentID
     * @param $recordID
     * @return array
     */
    public static function getAccessCredentials($studentID, $recordID)
    {
        $record = PlayRecordModel::getById($recordID);
        if (empty($record) || $record['student_id'] != $studentID) {
            return ['access_denied'];
        }

        $bucket = self::ALI_OSS_BUCKET;
        $path = "${_ENV['ENV_NAME']}/record_${recordID}*";

        $roleArn = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'record_file_arn');
        $sessionName = 'ai_peilian_' . $studentID;
        $policy = [
            'Version' => '1',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['oss:GetObject', 'oss:PutObject'],
                    'Resource' => "acs:oss:*:*:${bucket}/$path"
                ]
            ]
        ];

        list($errorMessage, $result) = AliClient::assumeRole($roleArn, $sessionName, $policy);

        if (!empty($errorMessage)) {
            SimpleLogger::error(__FILE__ . __LINE__, [
                'action' => 'getAccessCredentials',
                'message' => $errorMessage,
                'roleArn' => $roleArn,
                'sessionName' => $sessionName,
                'policy' => $policy,
                'result' => $result
            ]);
            return ['get_access_token_error'];
        }

        $result['bucket'] = $bucket;
        $result['path'] = $path;
        $result['end_point'] = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'endpoint');

        return [null, $result];
    }
}
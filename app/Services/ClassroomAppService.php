<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/14
 * Time: 下午2:18
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\ClassroomAppModel;
use App\Models\ClassroomDeviceModel;
use App\Models\ClassV1Model;
use App\Models\OrgAccountModel;
use App\Models\OrgLicenseModel;

class ClassroomAppService
{
    public static function login($params)
    {
        $account = OrgAccountModel::getRecord([
            'account'  => $params['account'],
            'type'     => OrgAccountModel::TYPE_CLASSROOM,
            'password' => $params['password'],
            'status'   => OrgAccountModel::STATUS_NORMAL,
        ], [], false);

        if(empty($account)) {
            throw new RunTimeException(['account_or_password_not_correct']);
        }

        $orgId = $account['org_id'];

        $token = md5(sprintf('%s%s%s', $params['account'], $params['mac'], microtime()));

        $mac = ClassroomDeviceModel::getRecord([
            'teacher_mac' => $params['mac'], 'org_id' => $orgId, 'status' => Constants::STATUS_TRUE
        ], ['student_devices'], false);

        //所有学生mac地址
        $devices = [];
        if(!empty($mac) && !empty($mac['student_devices'])) {
            $devices = json_decode($mac['student_devices'], 1);
        }

        //教室列表
        $records = ClassV1Model::selectClasses($orgId);
        $classMap = [];
        foreach($records as $record) {
            $classMap[$record['id']]['id']   = intval($record['id']);
            $classMap[$record['id']]['name'] = strval($record['name']);
            $classMap[$record['id']]['desc'] = strval($record['desc']);
            $classMap[$record['id']]['finish_num'] = intval($record['finish_num']);

            $classMap[$record['id']]['members'][] = [
                'id'       => intval($record['user_id']),
                'name'     => strval($record['user_name']),
                'role'     => intval($record['role']),
                'position' => intval($record['position']),
            ];
        }

        //许可证数量，过期时间
        $licenseNum = OrgLicenseModel::getValidNum($orgId, OrgLicenseModel::TYPE_CLASSROOM_NUM);
        $expire = OrgLicenseModel::getExpireByOrg($orgId);
        $startTime = $expire['active_time'] ?? 0;
        $endTime = empty($expire['active_time']) ? 0 : Util::computeExpire($startTime, $expire['duration'], $expire['duration_unit']);

        //如果登录时知道曾用离线模式登录，需要在缓存中记录
        $offlineForbidden = 0;
        if(!empty($params['used_offline'])) {
            ClassroomAppModel::increaseUsedOffline($orgId);
        }

        //每次登录都要检查离线模式使用次数是否达到限制
        $max = DictConstants::get(DictConstants::CLASSROOM_APP_CONFIG, 'used_offline');
        if(ClassroomAppModel::getUsedOffline($orgId) >= $max) {
            $offlineForbidden = 1;
        }

        $data = [
            'devices'          => $devices,
            'forbidden'        => 0,
            'offlineForbidden' => $offlineForbidden,
            'satan'            => 0,
            'notifications'    => [],
            'alert_message'    => '',
            'classroom_token'  => $token,
            'org_info' => [
                'account'     => strval($account['account']),
                'license_num' => intval($licenseNum),
                'start_time'  => intval($startTime),
                'end_time'    => intval($endTime),
            ],
            'classes' => array_values($classMap),
        ];

        $value = [
            'org_id'  => $orgId,
            'account' => $account['account'],
        ];

        ClassroomAppModel::setClassroomToken($token, $value);

        return $data;
    }

    public static function checkVersion()
    {
        $data = [
            'force' => 0,
            'force_update_left_days' => 0,
            'version' => '1.0.0.0',
            'url' => '',
        ];

        $data['crc32'] = strval(crc32(implode('', $data)));

        return $data;
    }
}
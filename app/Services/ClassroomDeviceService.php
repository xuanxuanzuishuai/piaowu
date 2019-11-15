<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/15
 * Time: 上午10:09
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ClassroomDeviceModel;

class ClassroomDeviceService
{
    public static function updateDevices($orgId, $teacherMac, $devices)
    {
        ClassroomDeviceModel::batchUpdateRecord(
            ['status' => Constants::STATUS_FALSE],
            ['teacher_mac' => $teacherMac, 'org_id' => $orgId, 'status' => Constants::STATUS_TRUE],
            false
        );
        $data = [
            'teacher_mac'     => $teacherMac,
            'student_devices' => json_encode($devices, 1),
            'org_id'          => $orgId,
            'create_time'     => time(),
        ];
        $success = ClassroomDeviceModel::batchInsert($data, false);
        if(!$success) {
            throw new RunTimeException(['save_fail']);
        }
        return true;
    }
}
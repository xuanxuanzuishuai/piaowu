<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/15
 * Time: 上午10:26
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ClassroomAppModel;
use App\Models\ClassRecordModel;
use App\Models\ClassV1Model;
use App\Models\ClassV1UserModel;
use App\Models\OrgLicenseModel;

class ClassroomScheduleService
{
    public static function start($orgId, $account, $classId, $students)
    {
        //检查机构是否拥有要进入的教室
        $class = ClassV1Model::getRecord(['org_id' => $orgId, 'id' => $classId, 'status' => Constants::STATUS_TRUE], ['id'], false);
        if(empty($class)) {
            throw new RunTimeException(['classroom_not_exist']);
        }

        $licenseNum = OrgLicenseModel::getValidNum($orgId, OrgLicenseModel::TYPE_CLASSROOM_NUM);

        $licenseNeed = array_sum(array_column($students, 'present'));

        $scheduleTokens = ClassroomAppModel::getScheduleSet($orgId);

        //检查要上课的班级是否已经在使用，同时统计消耗的许可证数量
        $licenseUsed = 0;
        if(!empty($scheduleTokens)) {
            foreach($scheduleTokens as $st) {
                $schedule = ClassroomAppModel::getSchedule($st);
                if(!empty($schedule)) {
                    $s = json_decode($schedule, 1);
                    if($s['class_id'] == $classId) {
                        throw new RunTimeException(['classroom_has_used']);
                    }
                    $licenseUsed += $s['license'];
                } else {
                    //一旦schedule token已经不存在，就从set中删除
                    ClassroomAppModel::removeScheduleSetMember($orgId, $st);
                }
            }
        }

        //检查许可证是否被耗尽
        if($licenseUsed + $licenseNeed > $licenseNum) {
            throw new RunTimeException(['license_exhausted']);
        }

        //检查是否有服务费
        $lastActive = OrgLicenseModel::getLastActiveLicense($orgId);
        if(empty($lastActive)) {
            throw new RunTimeException(['license_expired']);
        }

        //更新学生座位
        foreach($students as $student) {
            ClassV1UserModel::updatePosition($student['id'], ClassV1UserModel::ROLE_STUDENT, $classId, $student['position']);
        }

        $token = md5(sprintf('%s%s%s', $account, $classId, microtime()));
        $value = [
            'class_id' => $classId,
            'students' => $students,
            'license'  => $licenseNeed,
            'token'    => $token,
        ];

        ClassroomAppModel::setScheduleToken($token, $value);
        ClassroomAppModel::addScheduleSet($orgId, [$token]);

        //更新教室数据
        $affectedRows = ClassV1Model::updateRecord($classId, ['finish_num[+]' => 1, 'update_time' => time()], false);
        if(empty($affectedRows)) {
            throw new RunTimeException(['update_finish_num_fail']);
        }

        //插入一条上课记录
        $lastId = ClassRecordModel::insertRecord([
            'org_id'     => $orgId,
            'class_id'   => $classId,
            'students'   => json_encode($students, 1),
            'token'      => $token,
            'start_time' => time(),
        ], false);
        if(empty($lastId)) {
            throw new RunTimeException(['save_class_record_fail']);
        }

        return ['schedule_token' => $token];
    }

    public static function end($orgId, $schedule)
    {
        //更新下课时间
        $affectedRows = ClassRecordModel::batchUpdateRecord(['end_time' => time()], [
            'org_id'   => $orgId,
            'class_id' => $schedule['class_id'],
            'token'    => $schedule['token'],
        ], false);

        if(empty($affectedRows)) {
            throw new RunTimeException(['update_class_record_fail']);
        }

        ClassroomAppModel::delScheduleToken($schedule['token']);
        ClassroomAppModel::removeScheduleSetMember($orgId, $schedule['token']);
    }
}
<?php


namespace App\Services\LeadsPool;


use App\Libs\SimpleLogger;
use App\Models\CollectionModel;
use App\Models\LeadsPoolLogModel;
use App\Models\PackageExtModel;
use App\Models\StudentModel;
use App\Services\CollectionService;
use App\Services\StudentRefereeService;
use App\Services\StudentService;

class LeadsService
{
    /**
     * 新leads处理
     * @param $event
     * @return bool
     */
    public static function newLeads($event)
    {
        $student = StudentService::getByUuid($event['uuid']);

        if (empty($student)) {
            SimpleLogger::info("student not found", ['uuid' => $event['uuid']]);
            return false;
        }

        $refereeData = StudentRefereeService::studentRefereeUserData($student['id']);
        if (!empty($refereeData)) {
            return self::refLeads($student['id'], $refereeData['assistant_id'], $event['package_id']);
        }

        if (empty($student['assistant_id'])) {
            return self::pushLeads($event['uuid'], $student, $event['package_id']);
        }

        return true;
    }

    /**
     * leads 转介绍分配
     * @param $studentId
     * @param $assistantId
     * @param $packageId
     * @return bool
     */
    public static function refLeads($studentId, $assistantId, $packageId)
    {
        return self::assign('',
            $studentId,
            $assistantId,
            $packageId,
            date('Ymd'),
            LeadsPoolLogModel::TYPE_REF_ASSIGN);
    }

    /**
     * leads 自动分配
     * @param $id
     * @param $studentInfo
     * @param $packageId
     * @return bool
     */
    public static function pushLeads($id, $studentInfo, $packageId)
    {
        $config = [
            'id' => $id,
            'student' => $studentInfo,
            'package_id' => $packageId
        ];
        $leads = new Leads($config);

        SimpleLogger::info("push leads", $config);

        $pm = PoolManager::getInstance();
        $pm->addLeads($leads);

        return true;
    }

    /**
     * leads 分配到助教
     * @param $pid
     * @param $studentId
     * @param $assistantId
     * @param $packageId
     * @param $date
     * @param int $assignType
     * @return bool
     */
    public static function assign($pid, $studentId, $assistantId, $packageId, $date, $assignType = LeadsPoolLogModel::TYPE_ASSIGN)
    {
        $package = PackageExtModel::getByPackageId($packageId);
        $collection = CollectionService::getRefCollection($assistantId,
            CollectionModel::COLLECTION_READY_TO_GO_STATUS,
            $package['package_type'],
            $package['trial_type']);

        if (empty($collection)) {
            SimpleLogger::info('leads assign: no valid collection', [
                '$pid' => $pid,
                '$studentId' => $studentId,
                '$assistantId' => $assistantId,
                '$packageId' => $packageId,
            ]);
            return false;
        }

        /*
        $success = StudentService::allotCollectionAndAssistant($studentId, $collection, EmployeeModel::SYSTEM_EMPLOYEE_ID, $packageId);
        if ($success) {
            SimpleLogger::error('student update collection and assistant error', []);
        }
        */

        $success = true;
        SimpleLogger::info('leads assign success', [
            '$pid' => $pid,
            '$studentId' => $studentId,
            '$assistantId' => $assistantId,
            '$packageId' => $packageId,
        ]);

        LeadsPoolLogModel::insertRecord([
            'pid' => $pid,
            'type' => $assignType,
            'pool_id' => $assistantId,
            'pool_type' => Pool::TYPE_EMPLOYEE,
            'create_time' => time(),
            'date' => $date,
            'leads_student_id' => $studentId,
            'detail' => null,
        ]);

        return boolval($success);
    }

    /**
     * 分配到池
     * @param $pid
     * @param $studentId
     * @param $poolId
     * @param $date
     * @return bool
     */
    public static function move($pid, $studentId, $poolId, $date)
    {
        StudentModel::updateRecord($studentId, ['pool_id' => $poolId]);

        LeadsPoolLogModel::insertRecord([
            'pid' => $pid,
            'type' => LeadsPoolLogModel::TYPE_MOVE,
            'pool_id' => $poolId,
            'pool_type' => Pool::TYPE_POOL,
            'create_time' => time(),
            'date' => $date,
            'leads_student_id' => $studentId,
            'detail' => null,
        ]);

        return true;
    }
}
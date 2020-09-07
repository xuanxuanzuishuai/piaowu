<?php


namespace App\Services\LeadsPool;


use App\Libs\SimpleLogger;
use App\Models\CollectionModel;
use App\Models\EmployeeModel;
use App\Models\LeadsPoolLogModel;
use App\Models\PackageExtModel;
use App\Models\StudentModel;
use App\Services\CollectionService;
use App\Services\Queue\QueueService;
use App\Services\ReviewCourseService;
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
        //获取学生信息
        $student = StudentService::getByUuid($event['uuid']);
        if (empty($student)) {
            SimpleLogger::info("student not found", ['uuid' => $event['uuid']]);
            return false;
        }
        //获取课包信息
        $package = PackageExtModel::getByPackageId($event['package_id']);
        if (empty($package)) {
            SimpleLogger::info("package not found", ['package_id' => $event['package_id']]);
            return false;
        }
        //更新点评课标记
        $studentPackageType = ReviewCourseService::updateStudentReviewCourseStatus($student, $package);
        // 已有班级，不用分班
        if (!empty($student['collection_id'])) {
            SimpleLogger::info("student has collection", ['student_uuid' => $event['uuid'], 'collection_id' => $event['collection_id']]);
            return true;
        }
        // 学生不是体验阶段，不用分班
        if ($studentPackageType != PackageExtModel::PACKAGE_TYPE_TRIAL) {
            SimpleLogger::info("student has_review_course is not trial,don't need alloc collection", ['student_uuid' => $event['uuid'], 'student_package_type' => $studentPackageType]);
            return true;
        }
        // 非体验课包，不用分班
        if ($package['package_type'] != PackageExtModel::PACKAGE_TYPE_TRIAL) {
            SimpleLogger::info("package type is not trial,don't need alloc collection", ['student_uuid' => $event['uuid'], 'package_type' => $package['package_type']]);
            return true;
        }
        //分配结果变量：false代表分配失败 true代表分配成功
        $allotCollectionRes = false;
        // 检测学生是否存在转介绍人以及转介绍人的班级信息
        $refereeData = StudentRefereeService::studentRefereeUserData($student['id']);
        if (!empty($refereeData)) {
            $allotCollectionRes = self::refLeads($student['id'], $refereeData['assistant_id'], $event['package_id']);
        }
        //检测当前学生是否存在助教:如存在则直接检测当前助教是否有可分配的组班中的班级
        if (empty($allotCollectionRes) && !empty($student['assistant_id'])) {
            $allotCollectionRes = self::assistantAssign($student['id'], $package, $student['assistant_id']);
        }
        //正常班级分配
        if (empty($allotCollectionRes)) {
            $allotCollectionRes = self::pushLeads($event['uuid'], $student, $event['package_id']);
        }
        //无可分配路径，进入公海班
        if (empty($allotCollectionRes)) {
            self::defaultAssign($student['id'], $event['package_id']);
        }

        QueueService::studentPaid([
            'uuid' => $event['uuid'],
            'package_id' => $event['package_id'],
        ]);

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
        return $pm->addLeads($leads);
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
        //获取当前助教正在组班中的班级信息
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
                '$assignType' => $assignType,
            ]);

            LeadsPoolLogModel::insertRecord([
                'pid' => $pid,
                'type' => LeadsPoolLogModel::TYPE_NO_COLLECTION,
                'pool_id' => $assistantId,
                'pool_type' => Pool::TYPE_EMPLOYEE,
                'create_time' => time(),
                'date' => $date,
                'leads_student_id' => $studentId,
                'detail' => json_encode([
                    'assistant_id' => $assistantId,
                    'package_id' => $packageId,
                    'assign_type' => $assignType
                ]),
            ]);

            return false;
        }
        //分配班级操作
        $success = self::allotCollectionAndAssistant($studentId, $collection, $packageId, $assistantId, $date, $assignType, $pid);
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

    /**
     * 分配公海班级
     * @param $studentId
     * @param $packageId
     * @return mixed
     */
    public static function defaultAssign($studentId, $packageId)
    {
        //如果没有可加入的班级，则加入“公海班”，推送默认二维码，不分配助教
        $collection = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "LIMIT" => 1], ['id', 'assistant_id', 'type'], false);
        $success = self::allotCollectionAndAssistant($studentId, $collection, $packageId, 0, date('Ymd'), LeadsPoolLogModel::TYPE_ASSIGN);
        return boolval($success);
    }

    /**
     * 分配学生所属助教下组班中的班级
     * @param $studentId
     * @param $packageId
     * @return mixed
     */
    public static function assistantAssign($studentId, $package, $assistantId)
    {
        $collection = CollectionService::getRefCollection($assistantId,
            CollectionModel::COLLECTION_READY_TO_GO_STATUS,
            $package['package_type'],
            $package['trial_type']);
        if (empty($collection)) {
            return false;
        }
        $success = self::allotCollectionAndAssistant($studentId, $collection, $package['package_id'], $assistantId, date('Ymd'), LeadsPoolLogModel::TYPE_ASSIGN);
        return boolval($success);
    }

    /**
     * 分配班级操作
     * @param $studentId
     * @param $collection
     * @param $packageId
     * @param $assistantId
     * @param $date
     * @param $assignType
     * @param string $pid
     * @return bool
     */
    private static function allotCollectionAndAssistant($studentId, $collection, $packageId, $assistantId, $date, $assignType, $pid = '')
    {
        //分配班级操作
        $success = StudentService::allotCollectionAndAssistant($studentId, $collection, EmployeeModel::SYSTEM_EMPLOYEE_ID, $packageId);
        if (empty($success)) {
            SimpleLogger::error('student update collection and assistant error', []);

            LeadsPoolLogModel::insertRecord([
                'pid' => $pid,
                'type' => LeadsPoolLogModel::TYPE_SET_COLLECTION_ERROR,
                'pool_id' => $assistantId,
                'pool_type' => Pool::TYPE_EMPLOYEE,
                'create_time' => time(),
                'date' => $date,
                'leads_student_id' => $studentId,
                'detail' => json_encode([
                    'assistant_id' => $assistantId,
                    'package_id' => $packageId,
                    'assign_type' => $assignType,
                    'collection_id' => $collection['id']
                ]),
            ]);
            return false;
        }

        /**
         * 体验班级:赠送时长 发送通知 入班引导页面链接 发送短信
         * 公海班级：无后续操作
         */
        if ($collection['type'] == CollectionModel::COLLECTION_TYPE_NORMAL) {
            ReviewCourseService::giftCourseTimeAndSendNotify($studentId, $collection);
        }
        LeadsPoolLogModel::insertRecord([
            'pid' => $pid,
            'type' => $assignType,
            'pool_id' => $assistantId,
            'pool_type' => Pool::TYPE_EMPLOYEE,
            'create_time' => time(),
            'date' => $date,
            'leads_student_id' => $studentId,
            'detail' => json_encode([
                'assistant_id' => $assistantId,
                'package_id' => $packageId,
                'collection_id' => $collection['id']
            ]),
        ]);
        return true;
    }


}
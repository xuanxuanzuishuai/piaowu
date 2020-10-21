<?php


namespace App\Services\LeadsPool;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\CollectionModel;
use App\Models\EmployeeModel;
use App\Models\LeadsPoolLogModel;
use App\Models\PackageExtModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Services\CollectionService;
use App\Services\Queue\PushMessageTopic;
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
        // 获取学生信息
        $student = StudentService::getByUuid($event['uuid']);
        if (empty($student)) {
            SimpleLogger::info("student not found", ['uuid' => $event['uuid']]);
            return false;
        }

        $package = $event['package'];

        // 检查、更新用户的serv_app_id
        StudentService::updateOutsideFlag($student, $package);
        //更新点评课标记
        $studentPackageType = ReviewCourseService::updateStudentReviewCourseStatus($student, $package);
        //第一次购买年卡&&课包属于智能业务线正式课包&&用户未分配课管
        if (($package['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) &&
            ($package['app_id'] == PackageExtModel::APP_AI) &&
            empty($studentInfo['course_manage_id']) &&
            ($student['has_review_course'] < PackageExtModel::PACKAGE_TYPE_NORMAL)) {
            self::normalLeadsAllot($event, $student);
        }
        //体验卡用户分配助教
        if ($package['package_type'] == PackageExtModel::PACKAGE_TYPE_TRIAL) {
            self::trailLeadsAllot($event, $package, $studentPackageType, $student);
        }
        //学生支付通知
        QueueService::studentPaid([
            'uuid' => $event['uuid'],
            'package_id' => $event['package_id'],
        ]);
        return true;
    }


    /**
     * 体验卡用户分配助教
     * @param $event
     * @param $package
     * @param $studentPackageType
     * @param $student
     * @return bool|mixed
     */
    private static function trailLeadsAllot($event, $package, $studentPackageType, $student)
    {
        // 已有班级，不用分班
        if (!empty($student['collection_id'])) {
            SimpleLogger::info("student has collection", ['student_uuid' => $event['uuid'], 'collection_id' => $student['collection_id']]);
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
            $allotCollectionRes = self::refLeads($student['id'], $refereeData['assistant_id'], $package);
        }
        //检测当前学生是否存在助教:如存在则直接检测当前助教是否有可分配的组班中的班级
        if (empty($allotCollectionRes) && !empty($student['assistant_id'])) {
            $allotCollectionRes = self::assistantAssign($student['id'], $package, $student['assistant_id']);
        }
        //正常班级分配
        if (empty($allotCollectionRes)) {
            $allotCollectionRes = self::pushLeads($event['uuid'], $student, $package);
        }
        //无可分配路径，进入公海班
        if (empty($allotCollectionRes)) {
            self::defaultAssign($student['id'], $package);
        }
        return $allotCollectionRes;
    }

    /**
     * 年卡用户分配课管
     * @param $event
     * @param $studentInfo
     * @return bool|mixed
     */
    public static function normalLeadsAllot($event, $studentInfo)
    {
        //分配课管
        return self::pushCourseManageLeads($event['uuid'], $studentInfo, $event['package']);
    }


    /**
     * leads 转介绍分配
     * @param $studentId
     * @param $assistantId
     * @param $package
     * @return bool
     */
    public static function refLeads($studentId, $assistantId, $package)
    {
        return self::assign('',
            $studentId,
            $assistantId,
            $package,
            date('Ymd'),
            LeadsPoolLogModel::TYPE_REF_ASSIGN);
    }

    /**
     * leads 自动分配
     * @param $id
     * @param $studentInfo
     * @param $package
     * @return bool
     */
    public static function pushLeads($id, $studentInfo, $package)
    {
        $config = [
            'id' => $id,
            'student' => $studentInfo,
            'package' => $package
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
     * @param $package
     * @param $date
     * @param int $assignType
     * @return bool
     */
    public static function assign($pid, $studentId, $assistantId, $package, $date, $assignType = LeadsPoolLogModel::TYPE_ASSIGN)
    {
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
                '$package' => $package,
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
                    'package' => $package,
                    'assign_type' => $assignType
                ]),
            ]);

            return false;
        }
        //分配班级操作
        $success = self::allotCollectionAndAssistant($studentId, $collection, $package, $assistantId, $date, $assignType, $pid);
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
     * @param $package
     * @return mixed
     */
    public static function defaultAssign($studentId, $package)
    {
        //如果没有可加入的班级，则加入“公海班”，推送默认二维码，不分配助教
        $collection = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "LIMIT" => 1], ['id', 'assistant_id', 'type'], false);
        $success = self::allotCollectionAndAssistant($studentId, $collection, $package, 0, date('Ymd'), LeadsPoolLogModel::TYPE_ASSIGN);
        return boolval($success);
    }

    /**
     * 分配学生所属助教下组班中的班级
     * @param $studentId
     * @param $package
     * @param $assistantId
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
        $success = self::allotCollectionAndAssistant($studentId, $collection, $package, $assistantId, date('Ymd'), LeadsPoolLogModel::TYPE_ASSIGN);
        return boolval($success);
    }

    /**
     * 分配班级操作
     * @param $studentId
     * @param $collection
     * @param $package
     * @param $assistantId
     * @param $date
     * @param $assignType
     * @param string $pid
     * @return bool
     */
    private static function allotCollectionAndAssistant($studentId, $collection, $package, $assistantId, $date, $assignType, $pid = '')
    {
        //分配班级操作
        $success = StudentService::allotCollectionAndAssistant($studentId, $collection, EmployeeModel::SYSTEM_EMPLOYEE_ID, $package);
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
                    'package' => $package,
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
                'package' => $package,
                'collection_id' => $collection['id']
            ]),
        ]);
        return true;
    }

    /**
     * leads分配课管
     * @param $id
     * @param $studentInfo
     * @param $package
     * @return bool
     */
    public static function pushCourseManageLeads($id, $studentInfo, $package)
    {
        $config = [
            'id' => $id,
            'student' => $studentInfo,
            'package' => $package
        ];
        $leads = new Leads($config);
        SimpleLogger::info("course manage push leads", $config);
        //分配课管总池子id
        $leadsConfig = DictConstants::getSet(DictConstants::LEADS_CONFIG);
        if (empty($leadsConfig['course_manage_public_pool_id'])) {
            SimpleLogger::error("course manage miss public pool id", ["leads_config" => $leadsConfig]);
            return false;
        }
        $pm = PoolManager::getInstance();
        return $pm->addLeads($leads, $leadsConfig['course_manage_public_pool_id']);
    }

    /**
     * leads 分配到课管
     * @param $pid
     * @param $studentId
     * @param $courseManageId
     * @param $package
     * @param $date
     * @return bool
     */
    public static function assignToCourseManage($pid, $studentId, $courseManageId, $package, $date)
    {
        //检测课管名下学生数量是否超过设置人数
        $allotRes = self::checkCourseManageLeadsCount($pid, $studentId, $courseManageId, $package, $date);
        if ($allotRes === false) {
            return false;
        }
        //分配到课管操作
        $success = self::allotCourseManage($studentId, $courseManageId, $package, $date, $pid);
        return boolval($success);
    }

    /**
     * 检测课管名下学生数量是否超过设置人数
     * @param $pid
     * @param $studentId
     * @param $courseManageId
     * @param $package
     * @param $date
     * @return bool
     */
    private static function checkCourseManageLeadsCount($pid, $studentId, $courseManageId, $package, $date)
    {
        //获取当前已分配到此课管名下的学生数量
        $studentCount = StudentModel::getCount(['course_manage_id' => $courseManageId]);
        $courseManageInfo = EmployeeModel::getById($courseManageId);
        if ($studentCount >= $courseManageInfo['leads_max_nums']) {
            SimpleLogger::info('leads assign: course manage leads max num error', [
                '$pid' => $pid,
                '$studentId' => $studentId,
                '$courseManageId' => $courseManageId,
                '$package' => $package,
                '$assignType' => LeadsPoolLogModel::TYPE_ASSIGN_COURSE_MANAGE,
                '$studentCount' => $studentCount,
                '$leadsMaxNums' => $courseManageInfo['leads_max_nums'],
            ]);
            LeadsPoolLogModel::insertRecord([
                'pid' => $pid,
                'type' => LeadsPoolLogModel::TYPE_NO_COURSE_MANAGE,
                'pool_id' => $courseManageId,
                'pool_type' => Pool::TYPE_EMPLOYEE,
                'create_time' => time(),
                'date' => $date,
                'leads_student_id' => $studentId,
                'detail' => json_encode([
                    'course_manage_id' => $courseManageId,
                    'package' => $package,
                    'assign_type' => LeadsPoolLogModel::TYPE_ASSIGN_COURSE_MANAGE,
                    'student_count' => $studentCount,
                    'leads_max_nums' => $courseManageInfo['leads_max_nums'],
                ]),
            ]);
            return false;
        }
        return true;
    }


    /**
     * 分配到课管操作
     * @param $studentId
     * @param $courseManageId
     * @param $package
     * @param $date
     * @param string $pid
     * @return bool
     */
    private static function allotCourseManage($studentId, $courseManageId, $package, $date, $pid)
    {
        try {
            //开启事务
            $db = MysqlDB::getDB();
            $db->beginTransaction();
            $allotCourseManageRes = StudentService::allotCourseManage([$studentId], $courseManageId, EmployeeModel::SYSTEM_EMPLOYEE_ID);
        } catch (RunTimeException $e) {
            $allotCourseManageRes = false;
        }
        //提交事务
        if ($allotCourseManageRes === true) {
            $db->commit();
            self::successAfter($studentId, $courseManageId, $package, $date, $pid);
        } elseif ($allotCourseManageRes === false) {
            $db->rollBack();
            self::failAfter($studentId, $courseManageId, $package, $date, $pid);
        }
        return $allotCourseManageRes;
    }

    /**
     * 分配失败后置方法
     * @param $studentId
     * @param $courseManageId
     * @param $package
     * @param $date
     * @param $pid
     */
    private static function failAfter($studentId, $courseManageId, $package, $date, $pid)
    {
        //失败日志
        LeadsPoolLogModel::insertRecord([
            'pid' => $pid,
            'type' => LeadsPoolLogModel::TYPE_SET_COURSE_MANAGE_ERROR,
            'pool_id' => $courseManageId,
            'pool_type' => Pool::TYPE_EMPLOYEE,
            'create_time' => time(),
            'date' => $date,
            'leads_student_id' => $studentId,
            'detail' => json_encode([
                'course_manage_id' => $courseManageId,
                'package' => $package,
                'assign_type' => LeadsPoolLogModel::TYPE_ASSIGN_COURSE_MANAGE,
            ]),
        ]);
        SimpleLogger::error('student update course manage fail', []);
    }

    /**
     * 分配成功后置方法
     * @param $studentId
     * @param $courseManageId
     * @param $package
     * @param $date
     * @param $pid
     */
    private static function successAfter($studentId, $courseManageId, $package, $date, $pid)
    {
        //成功日志
        $time = time();
        LeadsPoolLogModel::insertRecord([
            'pid' => $pid,
            'type' => LeadsPoolLogModel::TYPE_ASSIGN_COURSE_MANAGE,
            'pool_id' => $courseManageId,
            'pool_type' => Pool::TYPE_EMPLOYEE,
            'create_time' => $time,
            'date' => $date,
            'leads_student_id' => $studentId,
            'detail' => json_encode([
                'course_manage_id' => $courseManageId,
                'package' => $package,
            ]),
        ]);
        SimpleLogger::error('student update course manage success', []);
        //计算模板消息发送延迟时间
        $deferTime = self::allotCourseManageWxPushTime($time);
        try {
            $topic = new PushMessageTopic();
            $msgBody['student_id'] = $studentId;
            $msgBody['course_manage_id'] = $courseManageId;
            $topic->courseManageNewLeadsPushWx($msgBody)->publish($deferTime);
        } catch (\Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', [
                'dateTime' => time(),
            ]);
        }
    }

    /**
     * 计算分配课管的微信推送消息发放时间
     * @param $time
     * @return false|int
     */
    private static function allotCourseManageWxPushTime($time){
        /**
         * 微信消息推送规则
         * 1.当天12：00之前的信息,当天12：00-12：30发送
         * 2.当天12：00之后的信息,第二天的12：00-12：30发送
         */
        $twelve = strtotime('today +12 hour');
        $randSeconds = mt_rand(0, 1800);
        if ($time <= $twelve) {
            $deferTime = $twelve + $randSeconds - $time;
        } else {
            //获取距离明天中午12:00-12:30这个区间的剩余秒数
            $deferTime = strtotime('tomorrow +12 hour') + $randSeconds - $time;
        }
        return $deferTime;
    }


    /**
     * 课管分配leads微信消息
     * @param $msgBody
     * @return bool
     */
    public static function allotLeadsCourseManageWxPush($msgBody)
    {
        SimpleLogger::info("allot course manage wx push to student start", ['msg_body' => $msgBody]);
        //获取课管信息
        $courseManageInfo = EmployeeModel::getRecord(['id' => $msgBody['course_manage_id']], ['wx_nick', 'wx_thumb', 'wx_qr']);
        //获取学生openId
        $userOpenIdInfo = UserWeixinModel::getBoundUserIds([$msgBody['student_id']], UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
        if (empty($userOpenIdInfo)) {
            SimpleLogger::error("student openid empty", []);
            return false;
        }
        $toBePushedStudentInfo[$msgBody['student_id']]['open_id'] = empty($userOpenIdInfo[0]['open_id']) ? "" : $userOpenIdInfo[0]['open_id'];
        //发送消息
        StudentService::allotCoursePushMessage($msgBody['course_manage_id'], $courseManageInfo, $toBePushedStudentInfo);
        SimpleLogger::info("allot course manage wx push to student end", []);
        return true;
    }
}
<?php
/**
 * 真人业务线
 * 计算用户命中活动
 * author: qingfeng.lian
 * date: 2022/9/7
 */

namespace App\Services\SyncTableData;

use App\Controllers\OrgWeb\RealSharePoster;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealStudentCanJoinActivityHistoryModel;
use App\Models\RealStudentCanJoinActivityModel;
use App\Models\RealWeekActivityModel;
use App\Services\ErpUserService;
use App\Services\SyncTableData\TraitService\StatisticsStudentReferralBaseAbstract;

class RealUpdateStudentCanJoinActivityService
{
    // 计算单一学生学生可参与活动的锁
    const LOCK_HANDLE_STUDENT_CAN_JOIN_ACTIVITY = 'lock_handle_student_can_join_activity_';
    protected $payStudentList      = [];
    protected $weekActivityList    = [];
    protected $handleStudentUuid   = [];
    protected $sleepSecond         = 2;
    protected $sleepHandleNum      = 5000;
    protected $studentFirstPayTime = [];
    protected $runTime             = 0;

    // construct 初始化数据
    protected $computeStudentId    = 0;
    protected $computeActivityId   = 0;
    protected $computeActivityInfo = [];

    public function getAttribute($variableName)
    {
        return $this->{$variableName};
    }

    /**
     * 初始化活动列表
     * @return void
     */
    public function initWeekActivityList()
    {
        $fields = [
            'activity_id',
            'clean_is_join',
            'activity_country_code',
            'target_user_type',
            'target_use_first_pay_time_start',
            'target_use_first_pay_time_end',
            'award_prize_type',
            'start_time',
            'end_time',
            'enable_status',
            'create_time',
        ];
        /**
         * 如果操作了某一个活动，
         * 如果是启用活动则只处理当前活动对应的用户即可 - 活动列表只有当前活动
         * 如果是禁用活动则处理需要处理已经命中的所有用户 - 活动列表应该是所有进行中启用的活动
         */
        if ($this->computeActivityId) {
            // 获取已经开始的活动 - 活动还未开始或已结束不应该再改变参与状态
            $this->computeActivityInfo = RealWeekActivityModel::getRecord([
                'activity_id'    => $this->computeActivityId,
                'start_time[<=]' => $this->runTime,
                'end_time[>]'    => $this->runTime,
            ], $fields);
            if ($this->computeActivityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_ON) {
                $this->weekActivityList = [$this->computeActivityInfo];
                $this->getFirstPayTimeRange();
            }
        }
        // 没有设定活动id 或者 禁用活动
        if (empty($activityId) || (!empty($this->computeActivityInfo) && $this->computeActivityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE)) {
            /** 读取所有活动 */
            $this->weekActivityList = RealWeekActivityModel::getStudentCanSignWeekActivity(null, $this->runTime, $fields);
        }

        if (empty($this->weekActivityList)) return;
        $activityIds = array_column($this->weekActivityList, 'activity_id');
        $list = RealSharePosterPassAwardRuleModel::getActivityTaskCount($activityIds);
        $list = array_column($list, 'total', 'activity_id');
        foreach ($this->weekActivityList as &$item) {
            $item['activity_task_total'] = $list[$item['activity_id']] ?? 0;
            $item['target_user_first_pay_time_start'] = $item['target_use_first_pay_time_start'];
            $item['target_user_first_pay_time_end'] = $item['target_use_first_pay_time_end'];
        }
    }

    /**
     * 初始化学生列表
     * @return void
     */
    public function initStudentList()
    {
        $this->payStudentList = ErpUserService::getIsPayAndCourseRemaining($this->computeStudentId, $this->studentFirstPayTime);
        // 去重
        $this->payStudentList = array_column($this->payStudentList, null, 'uuid');

        // 获取白名单用户
        try {
            $whitList = StatisticsStudentReferralBaseAbstract::getAppObj(Constants::REAL_APP_ID)->getRealWhiteList(
                Constants::REAL_COURSE_YES_PAY,
                true,
                $this->computeStudentId
            );
        } catch (RunTimeException $e) {
            SimpleLogger::info("StatisticsStudentReferralBaseAbstract::getAppObj", [$e->getMessage()]);
        }
        if (!empty($whitList)) {
            // 在用户列表中加入白名单用户
            foreach ($whitList as $item) {
                $this->payStudentList[$item['uuid']] = [
                    'id'             => $item['student_id'],
                    'country_code'   => $item['country_code'],
                    'uuid'           => $item['uuid'],
                    'first_pay_time' => $item['first_pay_time'],
                    'clean_time'     => 0,
                ];
            }
            unset($item);
        }
    }

    public function __construct($init = [])
    {
        $this->computeStudentId = intval($init['student_id'] ?? 0);
        $this->computeActivityId = intval($init['activity_id'] ?? 0);
        $this->runTime = time();
        // 初始化活动列表
        $this->initWeekActivityList();
        // 读取所有付费并且有剩余正式课的用户
        $this->initStudentList();
    }

    public function run()
    {
        /** 单一活动 */
        if (!empty($this->computeActivityInfo)) {
            $this->runWeekActivityCanJoinStudent();
        } /** 单一学生 */
        elseif (!empty($this->computeStudentId)) {
            $this->runStudentHitWeek();
        } /** 所有活动 */
        else {
            $this->runAllStudentHitWeekActivity();
        }
    }

    /**
     * 检查学生命中的真人周周领奖活动
     * @return void
     */
    protected function runStudentHitWeek()
    {
        if (empty($this->computeStudentId)) {
            return;
        }
        // 加5秒锁，失败重试6次
        $key = self::getRealLockKey($this->computeStudentId, self::LOCK_HANDLE_STUDENT_CAN_JOIN_ACTIVITY);
        if (!Util::setLock($key, 5, 6)) {
            SimpleLogger::info('runStudentHitWeek is lock fail', [$this->computeStudentId]);
            return;
        }
        try {
            $this->computeStudentHitWeekActivity();
            // 更新为不可参与
            $sInfo = ErpStudentModel::getRecord(['id' => $this->computeStudentId], ['uuid']);
            $hitInfo = RealStudentCanJoinActivityModel::getRecord(['student_uuid' => $sInfo['uuid']], ['week_activity_id']);
            self::cleanStudentWeekActivityId($sInfo['uuid'], $hitInfo['week_activity_id'], $this->runTime);
        } finally {
            Util::unLock($key);
        }
    }

    /**
     * 已经命中该活动的所有用户重新计算可参与活动
     * 获取已经开始的活动 - 活动还未开始或已结束不应该再改变参与状态
     * @return void
     */
    protected function runWeekActivityCanJoinStudent()
    {
        if (empty($this->computeActivityInfo['activity_id'])) {
            return;
        }
        if ($this->computeActivityInfo['start_time'] > $this->runTime || $this->computeActivityInfo['end_time'] <= $this->runTime) {
            return;
        }
        try {
            $this->computeStudentHitWeekActivity();
            /**
             * 存在以下几种情况需要特殊处理
             * 用户已经是没有付费有效课程数 - 更新为不可参与 - 并且历史记录中标记为终止参与
             */
            // 更新所有更新日期不是今天的为不可参与
            RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId([
                'week_activity_id'    => $this->computeActivityInfo['activity_id'],
                'week_update_time[<]' => $this->runTime,
            ]);

            // 没有付费学员时把命中当前正在运行的活动的用户参与进度更新为终止参与
            // 参与活动历史记录中当前正在运行的活动已经存在的并且当天没有更新的更新为参与终止
            if (!empty($this->weekActivityList)) {
                $activityIds = array_column($this->weekActivityList, 'activity_id');
                RealStudentCanJoinActivityHistoryModel::stopStudentJoinWeekActivityProgress([
                    'activity_id'      => $activityIds,
                    'update_time[<]'   => $this->runTime,
                    'join_progress[!]' => Constants::STUDENT_WEEK_PROGRESS_COMPLETE_JOIN,
                ]);
            }
        } catch (RunTimeException $exception) {
            SimpleLogger::info("RealUpdateStudentCanJoinActivityService::run", ['msg' => $exception->getMessage(), 'data' => $exception->getData()]);
        }
    }

    /**
     * 全量 - 检查所有学生命中的活动
     * @return void
     */
    protected function runAllStudentHitWeekActivity()
    {
        try {
            $this->computeStudentHitWeekActivity();
            /**
             * 存在以下几种情况需要特殊处理
             * 用户已经是没有付费有效课程数 - 更新为不可参与 - 并且历史记录中标记为终止参与
             */
            // 更新所有更新日期不是今天的为不可参与
            RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId(['week_update_time[<]' => $this->runTime]);

            // 没有付费学员时把命中当前正在运行的活动的用户参与进度更新为终止参与
            // 参与活动历史记录中当前正在运行的活动已经存在的并且当天没有更新的更新为参与终止
            if (!empty($this->weekActivityList)) {
                $activityIds = array_column($this->weekActivityList, 'activity_id');
                RealStudentCanJoinActivityHistoryModel::stopStudentJoinWeekActivityProgress([
                    'activity_id'      => $activityIds,
                    'update_time[<]'   => $this->runTime,
                    'join_progress[!]' => Constants::STUDENT_WEEK_PROGRESS_COMPLETE_JOIN,
                ]);
            }
        } catch (RunTimeException $exception) {
            SimpleLogger::info("RealUpdateStudentCanJoinActivityService::run", ['msg' => $exception->getMessage(), 'data' => $exception->getData()]);
        }
    }

    /**
     * 计算学生命中的周周领奖活动
     * @return void
     */
    public function computeStudentHitWeekActivity()
    {
        // 如果没有付费学员就不用检查活动了- 直接把所有用户命中的活动更新为0
        if (empty($this->payStudentList)) {
            return;
        }
        foreach ($this->payStudentList as $_num => $_student) {
            // 过滤重复的uuid
            if (isset($this->handleStudentUuid[$_student['uuid']])) {
                continue;
            }
            // 处理一定数据后休眠
            if ($_num % $this->sleepHandleNum == 0) {
                sleep($this->sleepSecond);
            }
            // 检查命中的周周领奖活动
            list($studentInfo, $hitActivity) = self::checkWeekActivity($_student, $this->weekActivityList);
            // 更新用户命中活动和历史参与记录
            RealStudentCanJoinActivityModel::updateStudentHitWeekActivity($studentInfo, $hitActivity);
            // 记录处理过的uuid
            $this->handleStudentUuid[$_student['uuid']] = 1;
        }
        unset($_num, $_student);
    }

    /**
     * 获取学生首次付费时间范围
     * @return void
     */
    public function getFirstPayTimeRange()
    {
        if (empty($this->computeActivityInfo)) {
            return;
        }
        if ($this->computeActivityInfo['target_user_type'] == OperationActivityModel::TARGET_USER_ALL) {
            return;
        }
        if (isset($this->computeActivityInfo['target_use_first_pay_time_start'])) {
            $this->studentFirstPayTime['start'] = $this->computeActivityInfo['target_use_first_pay_time_start'];
            $this->studentFirstPayTime['end'] = $this->computeActivityInfo['target_use_first_pay_time_end'];
        } else {
            $this->studentFirstPayTime['start'] = $this->computeActivityInfo['target_user_first_pay_time_start'];
            $this->studentFirstPayTime['end'] = $this->computeActivityInfo['target_user_first_pay_time_end'];
        }
    }

    /**
     * 检查学生是否能命中活动列表中的某个活动
     * 注：这里不会检查学生身份，所以这里的学生必须是付费有效 （有付费时间并且有剩余正式课数量）
     * @param $studentInfo
     * @param $weekActivityList
     * @return array
     */
    public static function checkWeekActivity($studentInfo, $weekActivityList)
    {
        if (empty($weekActivityList) || !is_array($weekActivityList)) {
            return [[], []];
        }
        $studentCountryCode = $studentInfo['country_code'] ?? '';
        $hitActivity = [];
        // 处理每个学生与所有正在运行的活动命中关系
        foreach ($weekActivityList as $_activity) {
            // 判断投放区域
            try {
                OperationActivityModel::checkWeekActivityCountryCode(['country_code' => $studentCountryCode], $_activity, Constants::REAL_APP_ID);
            } catch (RunTimeException $e) {
                SimpleLogger::info("checkWeekActivity::exception", [$e->getMessage(), $e->getData()]);
                continue;
            }
            // 检测用户首次付费时间与活动结束时间大小关系， 首次付费时间前结束的活动不可参与
            if ($studentInfo['first_pay_time'] > $_activity['end_time']) {
                continue;
            }
            // 检查首次付费时间
            if (!self::checkStudentIsTargetUser($studentInfo, $_activity)) {
                continue;
            }
            // 一个用户同一天只能命中一个活动，所以找到命中直接退出
            $hitActivity = $_activity;
            break;
        }
        unset($_activity);

        return [$studentInfo, $hitActivity];
    }

    /**
     * 检查学生是否在活动的目标用户中
     * 注：这里不会检查学生身份，所以这里的学生必须是付费有效 （有付费时间并且有剩余正式课数量）
     * @param $studentInfo
     * @param $activityInfo
     * @return bool
     */
    public static function checkStudentIsTargetUser($studentInfo, $activityInfo)
    {
        // 活动清退用户可参与
        if ($activityInfo['clean_is_join'] == RealWeekActivityModel::CLEAN_IS_JOIN_YES) {
            /**
             * 检查清退用户是否可参与周周领奖
             * 清退再续费用户定义：清退用户&首次清退后再续费&当前付费有效
             * 优先级：清退再续费用户 》活动对象：
             * 1。活动此选项选择"是"：可以参与
             * 2。活动此选项选择"否"：不可参与
             */
            $studentIdAttribute = RedisDB::getConn()->hget(SyncBinlogTableDataService::EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_TMP, $studentInfo['id']);
            $studentIdAttribute = !empty($studentIdAttribute) ? json_encode($studentIdAttribute, true) : [];
            // 清退用户是否是清退后再购买
            if (isset($studentIdAttribute['buy_after_clean']) && $studentIdAttribute['buy_after_clean'] == Constants::STATUS_TRUE) {
                return true;
            }
            // 清退后未购买，不可参与
            return false;
        }
        // 全部用户，只要用户是付费有效直接返回可参与
        if ($activityInfo['target_user_type'] == OperationActivityModel::TARGET_USER_ALL) {
            return true;
        }
        // 部分用户 - 首次付费时间小于活动圈定的时间不可参与
        if ($studentInfo['first_pay_time'] <= $activityInfo['target_user_first_pay_time_start']) {
            return false;
        }
        // 部分用户 - 首次付费时间超出活动圈定的时间不可参与
        if ($studentInfo['first_pay_time'] > $activityInfo['target_user_first_pay_time_end']) {
            return false;
        }
        return true;
    }

    /**
     * 获取真人业务线学生锁
     * @param $studentIdOrUUID
     * @param $lockKey
     * @return string
     */
    public static function getRealLockKey($studentIdOrUUID, $lockKey)
    {
        return $lockKey . Constants::REAL_APP_ID . '_' . $studentIdOrUUID;
    }

    /**
     * 清除学生当前可参与活动，
     * 同时如果用户之前已经命中过该活动则标记学生当前活动参与状态为终止参与
     * @param $studentUuid
     * @param $activityId
     * @param $runTime
     * @return void
     */
    public static function cleanStudentWeekActivityId($studentUuid, $activityId, $runTime = 0)
    {
        if (empty($studentUuid)) {
            return;
        }
        $cleanWhere = ['student_uuid' => $studentUuid];
        if (!empty($cleanWhere)) $cleanWhere['week_update_time[<]'] = $runTime;
        RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId($cleanWhere);
        if (!empty($activityId)) {
            RealStudentCanJoinActivityHistoryModel::stopJoinWeekActivity($studentUuid, $activityId, $runTime);
        }
    }

    /**
     * 更新最后上传截图审核状态
     * 如果不是最后一次上传的记录，不更新审核状态
     * @param $studentUuid
     * @param $activityId
     * @param $verifyStatus
     * @param $lastUploadRecord
     * @return bool
     */
    public static function updateLastVerifyStatus($studentUuid, $activityId, $verifyStatus, $lastUploadRecord)
    {
        // 更新用户活动最后一次参与状态 - 拒绝 - 如果不是最后一次上传的记录，不更新审核状态
        if (empty($lastUploadRecord)) {
            if ($verifyStatus == RealStudentCanJoinActivityModel::LAST_VERIFY_STATUS_UNQUALIFIED) {
                RealStudentCanJoinActivityModel::updateLastVerifyStatusIsRefused($studentUuid, $activityId);
            } elseif ($verifyStatus == RealStudentCanJoinActivityModel::LAST_VERIFY_STATUS_WAIT) {
                // 更新用户活动最后一次参与状态 - 待审核
                RealStudentCanJoinActivityModel::updateLastVerifyStatusIsWait($studentUuid, $activityId);
            }
        }
        if ($verifyStatus == RealStudentCanJoinActivityModel::LAST_VERIFY_STATUS_QUALIFIED) {
            // 更新用户活动最后一次参与状态 - 通过
            RealStudentCanJoinActivityModel::updateLastVerifyStatusIsPass($studentUuid, $activityId, [], empty($lastUploadRecord));
        }
        return true;
    }
}
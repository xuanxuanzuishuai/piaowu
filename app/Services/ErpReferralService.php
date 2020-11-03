<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/20
 * Time: 10:56 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\EventTaskModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\StudentModel;
use App\Libs\Valid;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeChatOpenIdListModel;
use Medoo\Medoo;

class ErpReferralService
{
    /** 转介绍事件 */
    const EVENT_TYPE_UPLOAD_POSTER = 4; // 上传分享海报
    const EVENT_TYPE_UPLOAD_POSTER_RETURN_CASH = 5; // 上传分享海报领取返现

    /**
     * 前端传值的对应关系
     */
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    const EXPECT_REISSUE_TRAIL_5 = 6; //补|转介绍付费体验卡-5元
    const EXPECT_REISSUE_TRAIL_10 = 7; //补|转介绍付费体验卡-10元
    const EXPECT_REISSUE_YEAR_100 = 8; //补|转介绍付费年卡-100元
    const EXPECT_REISSUE_YEAR_200 = 9; //补|转介绍付费年卡-200元
    const EXPECT_REISSUE_YEAR_50 = 10; //补|转介绍首购年卡-50元
    const EXPECT_REISSUE_POSTER_10 = 11; //补|上传截图活动-10元
    const EXPECT_REISSUE_RETURN_MONEY_49 = 12; //补|返现活动-49元
    const EXPECT_REISSUE_RETURN_MONEY_9_9 = 13; //补|返现活动-9.9元

    /** 阶段任务对应的任务id */
    const EVENT_TASK_ID_REGISTER       = [200, 1];          // 注册
    const EVENT_TASK_ID_TRIAL_PAY      = [201, 203, 52, 2]; // 体验支付
    const EVENT_TASK_ID_PAY            = [202, 204, 53, 3]; // 支付
    const EVENT_TASK_UPLOAD_SCREENSHOT = [];                // 上传截图审核通过任务,通过getEventTasksList动态获取

    const EVENT_TASK_ID_REISSUE_TRAIL_5 = [209]; //体验卡补发5元
    const EVENT_TASK_ID_REISSUE_TRAIL_10 = [210]; //体验课补10
    const EVENT_TASK_ID_REISSUE_YEAR_100 = [211]; //年卡补100
    const EVENT_TASK_ID_REISSUE_YEAR_200 = [212]; //年卡补200
    const EVENT_TASK_ID_REISSUE_YEAR_50 = [213]; //年卡补50
    const EVENT_TASK_ID_REISSUE_POSTER_10 = [214]; //上传截图补10
    const EVENT_TASK_ID_REISSUE_RETURN_MONEY_49 = [215]; //返现补49
    const EVENT_TASK_ID_REISSUE_RETURN_MONEY_9_9 = [216]; //返现补9.9

    /**
     * 此属性用于和前端交互时的对应
     */
    const EVENT_TASKS = [
        self::EXPECT_REGISTER  => '注册',
        self::EXPECT_TRAIL_PAY => '付费体验卡',
        self::EXPECT_YEAR_PAY  => '付费年卡',
    ];

    /**
     * 转介绍二期-红包审核任务
     */
    const REFEREE_EVENT_TASKS = [
        self::EXPECT_FIRST_NORMAL      => '首购智能正式课',
        self::EXPECT_UPLOAD_SCREENSHOT => '上传截图审核通过',
    ];

    //补发红包相关
    const REISSUE_CASH_AWARD = [
        self::EXPECT_REISSUE_TRAIL_5 => '补|转介绍付费体验卡-5元',
        self::EXPECT_REISSUE_TRAIL_10 => '补|转介绍付费体验卡-10元',
        self::EXPECT_REISSUE_YEAR_100 => '补|转介绍付费年卡-100元',
        self::EXPECT_REISSUE_YEAR_200 => '补|转介绍付费年卡-200元',
        self::EXPECT_REISSUE_YEAR_50 => '补|转介绍首购年卡-50元',
        self::EXPECT_REISSUE_POSTER_10 => '补|上传截图活动-10元',
        self::EXPECT_REISSUE_RETURN_MONEY_49 => '补|返现活动-49元',
        self::EXPECT_REISSUE_RETURN_MONEY_9_9 => '补|返现活动-9.9元'
    ];

    /** 任务状态 */
    const EVENT_TASK_STATUS_COMPLETE = 2;

    /** 奖励状态状态 */
    const AWARD_STATUS_REJECTED = 0; //不发放
    const AWARD_STATUS_WAITING = 1; //待发放
    const AWARD_STATUS_APPROVAL = 2; //审核中
    const AWARD_STATUS_GIVEN = 3; //发放成功
    const AWARD_STATUS_GIVE_ING = 4; //发放中/已发放待领取
    const AWARD_STATUS_GIVE_FAIL = 5; //发放失败


    const AWARD_STATUS = [
        self:: AWARD_STATUS_REJECTED => '不发放',
        self:: AWARD_STATUS_WAITING => '待发放',
        self:: AWARD_STATUS_APPROVAL => '审核中',
        self:: AWARD_STATUS_GIVEN => '发放成功',
        self:: AWARD_STATUS_GIVE_ING => '发放中',
        self:: AWARD_STATUS_GIVE_FAIL => '发放失败'
    ];

    /** 转介绍奖励类型 */
    const AWARD_TYPE_CASH = 1; // 现金
    const AWARD_TYPE_SUBS = 2; // 订阅时长
    const AWARD_TYPE_POINT = 3; //积分
    const AWARD_TYPE_MEDAL = 4; //奖章

    /** 事件任务状态 0 未启用 1 启用 2 禁用 */
    const ERP_EVENT_TASK_STATUS_NOT_ENABLED = 0;
    const ERP_EVENT_TASK_STATUS_ENABLED = 1;
    const ERP_EVENT_TASK_STATUS_DISABLED = 2;

    //专属海报参加人数
    const PERSONAL_POSTER_ATTEND_NUM_KEY = 'personal_poster_attend_num_key';

    /**
     * @return int[]
     * 不展示待发放的节点
     */
    public static function getNotDisplayWaitGiveTask()
    {
        return [
            self::EXPECT_UPLOAD_SCREENSHOT,
            self::EXPECT_REISSUE_TRAIL_5,
            self::EXPECT_REISSUE_TRAIL_10,
            self::EXPECT_REISSUE_YEAR_100,
            self::EXPECT_REISSUE_YEAR_200,
            self::EXPECT_REISSUE_YEAR_50,
            self::EXPECT_REISSUE_POSTER_10,
            self::EXPECT_REISSUE_RETURN_MONEY_49,
            self::EXPECT_REISSUE_RETURN_MONEY_9_9
        ];
    }

    public static function getAwardNode($source)
    {
        $awardNodeArr = [
            'referee' => self::REFEREE_EVENT_TASKS + self::REISSUE_CASH_AWARD,
            'reissue_award' => self::REISSUE_CASH_AWARD
        ];
        return empty($awardNodeArr[$source]) ? self::EVENT_TASKS : $awardNodeArr[$source];
    }

    /**
     * @param $taskId
     * @return int
     * 前端展示用户转介绍阶段任务对应关系
     */
    public static function getTaskRelateToVue($taskId)
    {
        if (in_array($taskId, self::EVENT_TASK_ID_REGISTER)) {
            return self::EXPECT_REGISTER;
        } elseif (in_array($taskId, self::EVENT_TASK_ID_TRIAL_PAY)) {
            return self::EXPECT_TRAIL_PAY;
        } elseif (in_array($taskId, self::EVENT_TASK_ID_PAY)) {
            return self::EXPECT_YEAR_PAY;
        } else {
            return 0;
        }
    }

    /**
     * @param $expectTaskId
     * @return array
     * 通过节点返回各个节点对应的所有的奖励
     */
    public static function getExpectTaskIdRelateAward($expectTaskId)
    {
        $taskIdArr = self::expectTaskRelateRealTask($expectTaskId);
        $taskInfo = EventTaskModel::getRecords(['id' => $taskIdArr]);
        $awardArr = [];
        array_map(function ($item) use(&$awardArr){
            $award = json_decode($item['award'], true)['awards'];
            foreach ($award as $v) {
                $awardArr[] = ['real_task_id' => $item['id'], 'award' => self::formatAward($v)];
            }
        }, $taskInfo);
        return $awardArr;
    }

    /**
     * @param $award
     * @return string
     * 格式化奖励
     */
    public static function formatAward($award)
    {
        if ($award['type'] == self::AWARD_TYPE_CASH) {
            return $award['amount'] / 100 . '元';
        } elseif ($award['type'] == self::AWARD_TYPE_SUBS) {
            return $award['amount'] .  '天';
        } else {
            return '';
        }
    }


    /**
     * @param $taskId
     * @return string
     * 转介绍阶段任务的中文对应
     */
    public static function getTaskRelateZh($taskId)
    {
        if (in_array($taskId, self::EVENT_TASK_ID_REGISTER)) {
            return '注册';
        } elseif (in_array($taskId, self::EVENT_TASK_ID_TRIAL_PAY)) {
            return '付费体验卡';
        } elseif (in_array($taskId, self::EVENT_TASK_ID_PAY)) {
            return '付费年卡';
        } else {
            return '暂不明确';
        }
    }

    /**
     * @return int
     * 当前生效的转介绍注册任务
     */
    public static function getRegisterTaskId()
    {
        $arr = self::EVENT_TASK_ID_REGISTER;
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的体验付费任务
     */
    public static function getTrailPayTaskId($index = 0)
    {
        $arr = self::EVENT_TASK_ID_TRIAL_PAY;
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的年卡付费任务
     */
    public static function getYearPayTaskId($index = 0)
    {
        $arr = self::EVENT_TASK_ID_PAY;
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * @return array
     * 转介绍相关的任务
     */
    public static function getAllReferralTaskId()
    {
        return array_merge(self::EVENT_TASK_ID_REGISTER, self::EVENT_TASK_ID_TRIAL_PAY, self::EVENT_TASK_ID_PAY);
    }

    /**
     * 转介绍列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getReferredList($params)
    {
        if (!empty($params['event_task_id'])) {
            list($includeTasks, $excludeTasks) = self::getRefEventTaskIdFilter($params['event_task_id']);
            $params['event_task_id'] = implode(',', $includeTasks);
            $params['not_event_task_id'] = implode(',', $excludeTasks);
        }
        $erp = new Erp();
        $response = $erp->referredList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        $list = [];
        //批量获取学生信息
        $uuidList = array_merge(array_column($response['data']['records'], 'student_uuid'), array_column($response['data']['records'], 'referrer_uuid'));
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        }
        foreach ($response['data']['records'] as $referred) {
            $tasks = $referred['tasks'] ?? [];
            $maxRefTask = self::findMaxRefTask($tasks);
            $maxRefTaskId = $maxRefTask['event_task_id'] ?? 0;

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $studentInfoList[$referred['student_uuid']]['name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'referrer_uuid' => $referred['referrer_uuid'],
                'referrer_name' => $studentInfoList[$referred['referrer_uuid']]['name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($referred['referrer_mobile']),
                'max_event_task_name' => self::getTaskRelateZh($maxRefTaskId) ?? '-',
                'register_time' => $referred['create_time'],
                'student_id' => $studentInfoList[$referred['student_uuid']]['id'],
                'referral_student_id' => $studentInfoList[$referred['referrer_uuid']]['id'],
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * 用户转介绍列表
     * @param $uuid
     * @param $page
     * @param $count
     * @return array
     * @throws RunTimeException
     */
    public static function getUserReferredList($uuid, $page, $count)
    {
        $params = [
            'page'  => $page,
            'count' => $count,
            'referrer_uuid' => $uuid,
        ];

        $erp = new Erp();
        $response = $erp->referredList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $list = [];
        foreach ($response['data']['records'] as $referred) {
            $tasks = $referred['tasks'] ?? [];
            $userTasks = [];
            foreach ($tasks as $task) {
                //兼容前端展示
                $task['event_task_id'] = self::getTaskRelateToVue($task['event_task_id']);
                $userTasks[$task['event_task_id']] = [
                    'create_time' => $task['create_time'],
                    'event_task_name' => self::getTaskRelateZh($task['event_task_id']),
                ];
            }

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $referred['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'tasks' => $userTasks,
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    private static function findMaxRefTask($tasks)
    {
        $maxTaskIndex = -1;
        $maxTaskId = 0;
        foreach ($tasks as $idx => $task) {
            //只需要特定的转介绍任务
            if (!in_array($task['event_task_id'], self::getAllReferralTaskId())) {
                continue;
            }
            if (empty($maxTaskId) || self::refEventTaskCmp($task['event_task_id'], $maxTaskId)) {
                $maxTaskIndex = $idx;
                $maxTaskId = $task['event_task_id'];
            }
        }
        return $tasks[$maxTaskIndex];
    }

    private static function refEventTaskCmp($a, $b)
    {
        if ($a == $b) { return 0; }
        return $a > $b;
    }

    /**
     * 根据转介绍阶段筛选event_task_id
     * @param $expectTask
     * @return array
     */
    private static function getRefEventTaskIdFilter($expectTask)
    {
        $include = [];
        $exclude = [];
        switch ($expectTask) {
            case self::EXPECT_REGISTER:
                $exclude = array_merge(self::EVENT_TASK_ID_TRIAL_PAY, self::EVENT_TASK_ID_PAY);
                $include = self::EVENT_TASK_ID_REGISTER;
                break;
            case self::EXPECT_TRAIL_PAY:
                $exclude = self::EVENT_TASK_ID_PAY;
                $include = self::EVENT_TASK_ID_TRIAL_PAY;
                break;
            case self::EXPECT_YEAR_PAY:
                $include = self::EVENT_TASK_ID_PAY;
                break;
        }
        return [$include, $exclude];
    }

    /**
     * 获取奖励列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getAwardList($params)
    {
        $erp = new Erp();
        $params['award_relate'] = $params['award_relate'] ?? Erp::AWARD_RELATE_REFERRAL;
        if (!empty($params['reviewer_name'])) {
            $params['reviewer_id'] = EmployeeModel::getRecord(['name[~]' => Util::sqlLike($params['reviewer_name'])], 'id');
        }
        if (!empty($params['student_name'])) {
            $params['student_uuid'] = array_column(StudentModel::getRecords(['name[~]' => $params['student_name']], ['uuid']), 'uuid');
            if (empty($params['student_uuid'])) {
                return ['list' => [], 'total_count' => 0];
            }
            unset($params['student_name']);
        }
        $expectTaskId = $params['event_task_id'];
        if (!empty($params['event_task_id'])) {
            $params['event_task_id'] = self::expectTaskRelateRealTask($params['event_task_id']);
        }
        $response = $erp->awardList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        // 转介绍二期红包列表：
        if ($params['award_relate'] == Erp::AWARD_RELATE_REFEREE) {
            return self::formatRefereeAwardList($response, $expectTaskId);
        }
        // DSS-转介绍-红包审核列表：
        return self::formatAwardList($response, $expectTaskId);
    }

    /**
     * DSS-转介绍-红包审核
     * @param $response
     * @param $expectTaskId
     * @return array
     */
    public static function formatAwardList($response, $expectTaskId)
    {
        $reviewerNames = self::getReviewerNames(array_column($response['data']['records'], 'reviewer_id'));

        $list = [];
        //批量获取学生信息
        $uuidList = array_merge(array_column($response['data']['records'], 'student_uuid'), array_column($response['data']['records'], 'referrer_uuid'));
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        }

        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        if (empty($relateUserEventTaskAwardIdArr)) {
            $relateAwardStatusArr = [];
        } else {
            $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), NULL, 'user_event_task_award_id');
        }
        //列表用户微信关注/绑定情况
        $refereeUuidArr = array_column($response['data']['records'], 'referrer_uuid');
        if (empty($refereeUuidArr)) {
            $weChatRelateInfo = [];
        } else {
            $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($refereeUuidArr), NULL, 'uuid');
        }

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['referrer_uuid']]['subscribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
            $bindStatus = $weChatRelateInfo[$award['referrer_uuid']]['bind_status'] ?? UserWeixinModel::STATUS_DISABLE;
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $studentInfoList[$award['student_uuid']]['name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'referrer_uuid' => $award['referrer_uuid'],
                'referrer_name' => $studentInfoList[$award['referrer_uuid']]['name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($award['referrer_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => self::getExpectTaskName($expectTaskId), //产品期望筛选什么选项，展示什么名称
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => $award['award_type'] == self::AWARD_TYPE_CASH ? ($award['award_amount'] / 100) : $award['award_amount'],
                'fail_reason_zh' => $award['award_status'] == self::AWARD_STATUS_GIVE_FAIL ? WeChatAwardCashDealModel::getWeChatErrorMsg($relateAwardStatusArr[$award['user_event_task_award_id']]['result_code']) : '',
                'award_type' => $award['award_type'],
                'create_time' => $award['create_time'],
                'review_time' => $award['review_time'],
                'reviewer_id' => $award['reviewer_id'],
                'reviewer_name' => $reviewerNames[$award['reviewer_id']] ?? '',
                'reason' => $award['reason'],
                'delay' => $award['delay'],
                'student_id' => $studentInfoList[$award['student_uuid']]['id'],
                'referral_student_id' => $studentInfoList[$award['referrer_uuid']]['id'],
                'subscribe_status' => $subscribeStatus,
                'subscribe_status_zh' => $subscribeStatus == WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注',
                'bind_status' => $bindStatus,
                'bind_status_zh' => $bindStatus == UserWeixinModel::STATUS_NORMAL ? '已绑定' : '未绑定',
            ];
            $list[] = $item;
        }
        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    public static function getReviewerNames($ids)
    {
        $reviewerNames = EmployeeService::getNameMap($ids);
        return array_column($reviewerNames, 'name', 'id');
    }
    /**
     * 转介绍二期红包列表
     * @param $response
     * @param $expectTaskId
     * @return array
     */
    public static function formatRefereeAwardList($response, $expectTaskId)
    {
        $reviewerNames = self::getReviewerNames(array_column($response['data']['records'], 'reviewer_id'));

        $list = [];
        //批量获取学生信息
        $uuidList = array_column($response['data']['records'], 'student_uuid');
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        }

        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        if (empty($relateUserEventTaskAwardIdArr)) {
            $relateAwardStatusArr = [];
        } else {
            $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), null, 'user_event_task_award_id');
        }
        //列表用户微信关注/绑定情况
        $studentUuidArr = array_column($response['data']['records'], 'student_uuid');
        if (empty($studentUuidArr)) {
            $weChatRelateInfo = [];
        } else {
            $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($studentUuidArr), null, 'uuid');
        }

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['student_uuid']]['subscribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
            $bindStatus = $weChatRelateInfo[$award['student_uuid']]['bind_status'] ?? UserWeixinModel::STATUS_DISABLE;
            $item = [
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'student_id'               => $studentInfoList[$award['student_uuid']]['id'],
                'student_uuid'             => $award['student_uuid'],
                'student_name'             => $studentInfoList[$award['student_uuid']]['name'],
                'student_mobile_hidden'    => Util::hideUserMobile($award['student_mobile']),
                'subscribe_status'         => $subscribeStatus,
                'subscribe_status_zh'      => $subscribeStatus == WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注',
                'bind_status'              => $bindStatus,
                'bind_status_zh'           => $bindStatus == UserWeixinModel::STATUS_NORMAL ? '已绑定' : '未绑定',
                'award_status'             => $award['award_status'],
                'award_status_zh'          => $award['award_status_zh'],
                'event_task_name'          => self::getExpectTaskName($expectTaskId), //产品期望筛选什么选项，展示什么名称
                'event_task_type'          => $award['event_task_type'],
                'award_amount'             => $award['award_type'] == self::AWARD_TYPE_CASH ? ($award['award_amount'] / 100) : $award['award_amount'],
                'fail_reason_zh'           => $award['award_status'] == self::AWARD_STATUS_GIVE_FAIL ? WeChatAwardCashDealModel::getWeChatErrorMsg($relateAwardStatusArr[$award['user_event_task_award_id']]['result_code']) : '',
                'reviewer_id'              => $award['reviewer_id'],
                'reviewer_name'            => $reviewerNames[$award['reviewer_id']] ?? '',
                'review_time'              => $award['review_time'],
                'reason'                   => $award['reason'],
                'create_time'              => $award['create_time'],
                'event_task_id'            => $award['event_task_id'],
                'award_type'               => $award['award_type'],
                'delay'                    => $award['delay'],
            ];
            $list[] = $item;
        }
        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * 筛选节点对应的节点名称
     * @param $expectTaskId
     * @return string
     */
    private static function getExpectTaskName($expectTaskId)
    {
        $arr = self::EVENT_TASKS + self::REFEREE_EVENT_TASKS + self::REISSUE_CASH_AWARD;
        return $arr[$expectTaskId] ?? '';
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 社群红包奖励列表
     */
    public static function getCommunityAwardList($params)
    {
        $erp = new Erp();
        $params['award_relate'] = Erp::AWARD_RELATE_COMMUNITY;
        if (!empty($params['reviewer_name'])) {
            $params['reviewer_id'] = EmployeeModel::getRecord(['name[~]' => Util::sqlLike($params['reviewer_name'])], 'id');
        }
        if (!empty($params['event_task_id'])) {
            $params['event_task_id'] = self::expectTaskRelateRealTask($params['event_task_id']);
        }
        $response = $erp->awardList($params);
        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $reviewerNames = EmployeeService::getNameMap(array_column($response['data']['records'], 'reviewer_id'));
        $reviewerNames = array_column($reviewerNames, 'name', 'id');

        $list = [];
        //批量获取学生信息
        $uuidList = array_column($response['data']['records'], 'student_uuid');
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentModel::getStudentCollectionInfo($uuidList, ['s.name', 's.id', 's.uuid', 'c.name (collection_name)']), null, 'uuid');
        }

        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        if (empty($relateUserEventTaskAwardIdArr)) {
            $relateAwardStatusArr = [];
        } else {
            $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), NULL, 'user_event_task_award_id');
        }
        //列表用户微信关注/绑定情况
        $refereeUuidArr = array_column($response['data']['records'], 'student_uuid');
        if (empty($refereeUuidArr)) {
            $weChatRelateInfo = [];
        } else {
            $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($refereeUuidArr), NULL, 'uuid');
        }

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['student_uuid']]['subscribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
            $bindStatus = $weChatRelateInfo[$award['student_uuid']]['bind_status'] ?? UserWeixinModel::STATUS_DISABLE;
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $studentInfoList[$award['student_uuid']]['name'],
                'collection_name' => $studentInfoList[$award['student_uuid']]['collection_name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => $award['event_task_name'],
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => $award['award_type'] == self::AWARD_TYPE_CASH ? ($award['award_amount'] / 100) : $award['award_amount'],
                'fail_reason_zh' => $award['award_status'] == self::AWARD_STATUS_GIVE_FAIL ? WeChatAwardCashDealModel::getWeChatErrorMsg($relateAwardStatusArr[$award['user_event_task_award_id']]['result_code']) : '',
                'award_type' => $award['award_type'],
                'create_time' => $award['create_time'],
                'review_time' => $award['review_time'],
                'reviewer_id' => $award['reviewer_id'],
                'reviewer_name' => $reviewerNames[$award['reviewer_id']] ?? '',
                'reason' => $award['reason'],
                'delay' => $award['delay'],
                'student_id' => $studentInfoList[$award['student_uuid']]['id'],
                'subscribe_status' => $subscribeStatus,
                'subscribe_status_zh' => $subscribeStatus == WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注',
                'bind_status' => $bindStatus,
                'bind_status_zh' => $bindStatus == UserWeixinModel::STATUS_NORMAL ? '已绑定' : '未绑定',
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * @param $expectTaskId
     * @return false|int[]|string[]
     * 前端传期望看到的task和实际对应的task关系
     */
    public static function expectTaskRelateRealTask($expectTaskId)
    {
        $eventTask = self::getEventTasksList(0, self::EVENT_TYPE_UPLOAD_POSTER);
        $arr = [
            self::EXPECT_REGISTER          => self::EVENT_TASK_ID_REGISTER,
            self::EXPECT_TRAIL_PAY         => self::EVENT_TASK_ID_TRIAL_PAY,
            self::EXPECT_YEAR_PAY          => self::EVENT_TASK_ID_PAY,
            self::EXPECT_FIRST_NORMAL      => self::EVENT_TASK_ID_PAY,
            self::EXPECT_UPLOAD_SCREENSHOT => array_column($eventTask, 'id'),
            self::EXPECT_REISSUE_TRAIL_5   => self::EVENT_TASK_ID_REISSUE_TRAIL_5,
            self::EXPECT_REISSUE_TRAIL_10  => self::EVENT_TASK_ID_REISSUE_TRAIL_10,
            self::EXPECT_REISSUE_YEAR_100  => self::EVENT_TASK_ID_REISSUE_YEAR_100,
            self::EXPECT_REISSUE_YEAR_200  => self::EVENT_TASK_ID_REISSUE_YEAR_200,
            self::EXPECT_REISSUE_YEAR_50   => self::EVENT_TASK_ID_REISSUE_YEAR_50,
            self::EXPECT_REISSUE_POSTER_10 => self::EVENT_TASK_ID_REISSUE_POSTER_10,
            self::EXPECT_REISSUE_RETURN_MONEY_49 => self::EVENT_TASK_ID_REISSUE_RETURN_MONEY_49,
            self::EXPECT_REISSUE_RETURN_MONEY_9_9 => self::EVENT_TASK_ID_REISSUE_RETURN_MONEY_9_9
        ];
        return $arr[$expectTaskId] ?? explode(',', $expectTaskId);
    }

    /**
     * 获取用户获得的奖励列表
     * @param $uuid
     * @param $page
     * @param $count
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getUserAwardList($uuid, $page, $count, $params)
    {
        $query = [
            'page'  => $page,
            'count' => $count,
            'referrer_uuid' => $uuid
        ];
        if(!empty($params['award_type'])) {
            $query['award_type'] = $params['award_type'];
        }

        $erp = new Erp();
        $response = $erp->awardList($query);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $list = [];
        foreach ($response['data']['records'] as $award) {
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $award['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => $award['event_task_name'],
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => ($award['award_amount'] / 100),
                'award_type' => $award['award_type'],
                'delay' => $award['delay'],
                'create_time' => $award['create_time'],
            ];
            $list[] = $item;
        }

        $cash = $response['data']['sum'][self::AWARD_TYPE_CASH];
        $sum = empty($cash) ? 0 : $cash / 100;

        return ['list' => $list, 'total_count' => $response['data']['total_count'], 'sum' => $sum];
    }

    /**
     * 更新奖励状态
     * @param $awardId
     * @param $status
     * @param $reviewerId
     * @param $reason
     * @param string $keyCode
     * @param null $eventTaskId
     * @return array|bool
     * @throws RunTimeException
     */
    public static function updateAward(
        $awardId,
        $status,
        $reviewerId,
        $reason,
        $keyCode,
        $eventTaskId = null
    ) {
        if (empty($awardId) || empty($reviewerId)) {
            throw new RunTimeException(['invalid_award_id_or_reviewer_id']);
        }
        $erp = new Erp();
        $time = time();
        //期望结果数据 批量处理支持
        $awardIdArr = array_unique(explode(',', $awardId));
        if (count($awardIdArr) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        $needDealAward = [];
        if (!empty($awardIdArr)) {
            foreach ($awardIdArr as $value) {
                $needDealAward[$value] = [
                    'award_id'    => $value,
                    'status'      => $status,
                    'reviewer_id' => $reviewerId,
                    'reason'      => $reason,
                    'review_time' => $time
                ];
            }
        }
        //实际发放结果数据 调用微信红包，
        if ($status == self::AWARD_STATUS_GIVEN) {
            //当前操作的奖励基础信息
            $awardBaseInfo = $erp->getUserAwardInfo($awardId);
            if (!empty($awardBaseInfo['data']['award_info'])) {
                $needCheckAward = array_filter($awardBaseInfo['data']['award_info'], function ($award) use ($time) {
                    return ($award['award_type'] == self::AWARD_TYPE_CASH && in_array($award['status'], [self::AWARD_STATUS_WAITING, self::AWARD_STATUS_GIVE_FAIL]) && $award['create_time'] + $award['delay'] < $time);
                });
                //验证被推荐人是否已退费
                $info = self::verifyStudentStatus($eventTaskId, array_column($needCheckAward, 'res_uuid'));
                if (!empty($info)) {
                    return ['has_refund' => $info];
                }
                foreach ($awardBaseInfo['data']['award_info'] as $award) {
                    //仅现金,且未发放/已发放失败,且满足奖励延迟时间限制可调用
                    if ($award['award_type'] == self::AWARD_TYPE_CASH
                        && in_array($award['status'], [self::AWARD_STATUS_WAITING, self::AWARD_STATUS_GIVE_FAIL])
                        && $award['create_time'] + $award['delay'] <= $time
                    ) {
                        list($baseAwardId, $dealStatus) = CashGrantService::cashGiveOut($award['uuid'], $award['award_id'], $award['award_amount'], $reviewerId, $keyCode);
                        //重试操作无变化，无须更新erp
                        if ($award['status'] != $dealStatus) {
                            $needDealAward[$baseAwardId]['status'] = $dealStatus;
                        } else {
                            unset($needDealAward[$award['award_id']]);
                        }
                    } else {
                        unset($needDealAward[$award['award_id']]);
                        SimpleLogger::info('award data valid', $award);
                    }
                }
            }
        }

        if (empty($needDealAward)) {
            return [];
        }

        $response = $erp->batchUpdateAward($needDealAward);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        if (!empty($response['data'])) {
            foreach ($response['data'] as $v) {
                if (!empty($v['event_task_id'])) {
                    WeChatService::notifyUserCustomizeMessage(
                        $v['referrer_mobile'] ?: $v['receive_mobile'],
                        $v['event_task_id'],
                        [
                            'mobile' => Util::hideUserMobile($v['student_mobile']),
                            'url' => in_array($keyCode, [WeChatAwardCashDealModel::COMMUNITY_PIC_WORD,WeChatAwardCashDealModel::REISSUE_PIC_WORD]) ? '' : $_ENV['STUDENT_INVITED_RECORDS_URL'],
                            'awardValue' => $v['award_amount'] / 100
                        ]
                    );
                }
            }
        }

        return [];
    }

    /**
     * @param $eventTaskId
     * @param $uuidArr
     * @return string|null
     * 验证当前相关奖励的订单是否退费
     */
    public static function verifyStudentStatus($eventTaskId, $uuidArr)
    {
        if (in_array($eventTaskId, self::notNeedVerifyTaskId()) || empty($eventTaskId) || empty($uuidArr)) {
            return NULL;
        }

        $studentIdArr = StudentModel::getRecords(['uuid' => $uuidArr], 'id');

        if ($eventTaskId == self::EXPECT_TRAIL_PAY) {
            //所有的体验包id
            $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]), 'package_id');
        } else {
            //所有年包id
            $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL]), 'package_id');
        }
        $existNormalBillStudentIdArr = array_column(
            GiftCodeModel::getRecords(
                [
                    'buyer'           => $studentIdArr,
                    'bill_package_id' => $packageIdArr,
                    'bill_app_id'     => PackageExtModel::APP_AI,
                    'code_status'     =>
                    [
                        GiftCodeModel::CODE_STATUS_NOT_REDEEMED,
                        GiftCodeModel::CODE_STATUS_HAS_REDEEMED
                    ]
                ],
                [
                    'buyer' => Medoo::raw('DISTINCT(buyer)')
                ]
            ),
            'buyer'
        );
        $diffArr = array_diff($studentIdArr, $existNormalBillStudentIdArr);
        if (!empty($diffArr)) {
            return implode(',', StudentModel::getRecords(['id' => $diffArr], 'mobile'));
        }
        return NULL;
    }

    /**
     * 不需要校验退费的节点
     * @return array
     */
    public static function notNeedVerifyTaskId()
    {
       $arr = array_keys(self::REISSUE_CASH_AWARD);
       array_push($arr, self::EXPECT_UPLOAD_SCREENSHOT);
       return $arr;
    }


    /**
     * 获取用户转介绍数据
     * @param $params
     * @return array
     */
    public static function getUserReferralInfo($params)
    {
        //远程访问erp获取数据
        $erp = new Erp();
        $response = $erp->userReferralInfo($params);
        $data = [];
        if (empty($response) || $response['code'] != 0) {
            return $data;
        }
        return $response['data']['records'];
    }

    /**
     * 获取event事件列表
     * @param $eventId
     * @param $eventType
     * @return array
     */
    public static function getEventTasksList($eventId = 0, $eventType = self::EVENT_TYPE_UPLOAD_POSTER_RETURN_CASH)
    {
        //远程调用erp获取事件任务信息
        $erp = new Erp();
        $eventTaskList = $erp->eventTaskList($eventId, $eventType);

        if (empty($eventTaskList['data']) && $eventTaskList['code'] != Valid::CODE_SUCCESS) {
            return [];
        }

        //整理事件任务数据
        $tasksData = [];
        array_map(function ($task) use (&$tasksData) {
            if (is_array($task['tasks'])) {
                $tasksData = array_merge($tasksData, $task['tasks']);
            }
        }, $eventTaskList['data']);

        if (empty($tasksData)) {
            return [];
        }

        $data = [];
        foreach ($tasksData as $ek => &$ev) {
            if ($ev['status'] == self::ERP_EVENT_TASK_STATUS_ENABLED) {
                $ev['condition'] = json_decode($ev['condition'], true);
                $data[] = $tasksData[$ek];
            }
        }

        return $data;
    }

    /**
     * @return array
     * 专属海报播报的数据
     */
    public static function broadDataRelate()
    {
        $erp = new Erp();
        $needShowInfo = [
            ['type' => self::EXPECT_REGISTER, 'page' => 1, 'count' => 25],
            ['type' => self::EXPECT_TRAIL_PAY, 'page' => 1, 'count' => 35],
            ['type' => self::EXPECT_YEAR_PAY, 'page' => 1, 'count' => 45]
        ];
        $returnInfo = [];
        foreach ($needShowInfo as $value) {
            list($includeTasks, $excludeTasks) = self::getRefEventTaskIdFilter($value['type']);
            $params['event_task_id'] = $includeTasks;
            $params['page'] = $value['page'];
            $params['count'] = $value['count'];
            $response = $erp->awardList($params);
            $studentInfoList = array_column(StudentModel::getRecords(['uuid' => array_column($response['data']['records'], 'referrer_uuid')], ['name', 'uuid']), null, 'uuid');
            $returnInfo[$value['type']] = array_map(function ($item) use($studentInfoList) {
                if (in_array($item['award_type'], [self::AWARD_TYPE_CASH])) {
                    if ($item['award_type'] == self::AWARD_TYPE_CASH) {
                        $str = $item['award_amount'] / 100 . '元';
                    } else {
                        $str = $item['award_amount'] . '天时长';
                    }
                    return ['referee_name' => self::dealShowName($studentInfoList[$item['referrer_uuid']]['name']), 'award_info' => $str, 'award_id' => $item['user_event_task_award_id']];
                }
            }, $response['data']['records']);

        }
        $joinNum = RedisDB::getConn()->get(self::PERSONAL_POSTER_ATTEND_NUM_KEY) ?: DictConstants::get(DictConstants::PERSONAL_POSTER, 'initial_num');
        return [$returnInfo, $joinNum];
    }

    /**
     * @param $name
     * @return string
     * 隐藏展示的用户名
     */
    private static function dealShowName($name)
    {
        $count = mb_strlen($name);
        if ($count == 1) {
            return $name . '*';
        } elseif ($count == 2) {
            return mb_substr($name, 0, 1) . '*';
        } elseif ($count > 2) {
            return  mb_substr($name, 0, 1) . str_repeat('*', $count - 2) . mb_substr($name, -1, 1) ;
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 11:47 AM
 */

namespace App\Services;

use App\Libs\AIPLClass;
use App\Libs\AliOSS;
use App\Libs\Erp;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\CollectionModel;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\ReviewCourseLogModel;
use App\Models\ReviewCourseTaskModel;
use App\Services\Queue\QueueService;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\PlayRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Services\VoiceCall\VoiceCallTRService;
use App\Models\VoiceCallLogModel;

class ReviewCourseService
{
    /**
     * 点评课学生列表过滤条件
     * @param $filterParams
     * @return array
     */
    public static function studentsFilter($filterParams)
    {
        $filter = [];

        if (isset($filterParams['course_status'])) {
            $op = $filterParams['course_status'] == 1 ? '[>=]' : '[<]';
            $filter['sub_end_date' . $op] = date('Ymd');
        }

        if (!empty($filterParams['last_play_after'])) {
            $filter['last_play_time[>=]'] = $filterParams['last_play_after'];
        }

        if (!empty($filterParams['last_play_before'])) {
            $filter['last_play_time[<]'] = $filterParams['last_play_before'];
        }

        if (!empty($filterParams['sub_start_after'])) {
            $filter['sub_start_time[>=]'] = $filterParams['sub_start_after'];
        }

        if (!empty($filterParams['sub_start_before'])) {
            $filter['sub_start_time[<]'] = $filterParams['sub_start_before'];
        }

        if (!empty($filterParams['sub_end_after'])) {
            $filter['sub_end_time[>=]'] = $filterParams['sub_end_after'];
        }

        if (!empty($filterParams['sub_end_before'])) {
            $filter['sub_end_time[<]'] = $filterParams['sub_end_before'];
        }

        list($page, $count) = Util::formatPageCount($filterParams);
        $filter['LIMIT'] = [($page - 1) * $count, $count];

        return $filter;
    }

    /**
     * 点评课学生列表
     * @param array $filter
     * @return array
     */
    public static function students($filter)
    {
        list($count, $students) = ReviewCourseModel::students($filter);

        $students = array_map(function ($student) {

            switch ($student['has_review_course']) {
                case ReviewCourseModel::REVIEW_COURSE_1980:
                    $reviewCourseType = '1980';
                    break;
                case ReviewCourseModel::REVIEW_COURSE_49:
                    $reviewCourseType = '49';
                    break;
                default:
                    $reviewCourseType = '无';
            }

            return [
                'id' => (int)$student['id'],
                'name' => $student['name'],
                'mobile' => $student['mobile'],
                'course_status' => ($student['sub_end_date'] >= date('Ymd')) ? '未完课' : '已完课',
                'last_play_time' => $student['last_play_time'],
                'last_review_time' => $student['last_review_time'],
                'review_course' => $reviewCourseType,
            ];
        }, $students);

        return [$count, $students];
    }

    /**
     * 点评课学生信息
     * @param int $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function studentInfo($studentId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['unknown_user']);
        }

        $wxBind = UserWeixinModel::getRecord([
            'user_id' => $studentId,
            'busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
            'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
        ], ['id'], false);

        return [
            'id' => $student['id'],
            'name' => $student['name'],
            'mobile' => $student['mobile'],
            'wx_bind' => empty($wxBind) ? '否' : '是',
        ];
    }

    /**
     * 日报列表过滤条件
     * @param array $filterParams
     * @return array
     */
    public static function reportsFilter($filterParams)
    {
        $filter = [];

        if (!empty($filterParams['play_after'])) {
            $filter['created_time[>=]'] = $filterParams['play_after'];
        }

        if (!empty($filterParams['play_before'])) {
            $filter['created_time[<]'] = $filterParams['play_before'];
        }

        if (isset($filterParams['student_id'])) {
            $filter['student_id'] = $filterParams['student_id'];
        }

        list($page, $count) = Util::formatPageCount($filterParams);
        $filter['LIMIT'] = [($page - 1) * $count, $count];

        return $filter;
    }

    /**
     * 日报列表
     * @param array $filter
     * @return array
     */
    public static function reports($filter)
    {
        list($count, $reports) = ReviewCourseModel::reports($filter);
        $reports = array_map(function ($report) {
            return [
                'date' => $report['date'],
                'review_time' => 0,
                'review_teacher' => '无',
            ];
        }, $reports);

        return [$count, $reports];
    }

    /**
     * 日报详情过滤条件
     * @param array $filterParams
     * @return array
     * @throws RunTimeException
     */
    public static function reportDetailFilter($filterParams)
    {
        $filter = [];

        $dayTime = strtotime($filterParams['play_date']);
        if (empty($dayTime)) {
            throw new RunTimeException(['invalid_date']);
        }
        $filter['created_time[<>]'] = [$dayTime, $dayTime + 86399];

        $filter['student_id'] = $filterParams['student_id'];

        if (isset($filterParams['lesson_id'])) {
            $filter['lesson_id'] = $filterParams['lesson_id'];
        }

        return $filter;
    }

    /**
     * 日报详情
     * 内容按曲目id汇总
     * @param $filter
     * @return array
     */
    public static function reportDetail($filter)
    {
        $lessons = ReviewCourseModel::reportDetail($filter);

        $lessonIds = array_column($lessons, 'lesson_id');
        if (!empty($lessonIds)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 'test', 0, 1);
            $resp = $opn->lessonsByIds($lessonIds, 0, []);
            if ($resp['code'] == 0) {
                $lessonsInfo = $resp['data'];
                $lessonsInfo = array_combine(array_column($lessonsInfo, 'id'), $lessonsInfo);
            }
        }

        if (empty($lessonsInfo)) { $lessonsInfo = []; }
        $lessons = array_map(function ($lesson) use ($lessonsInfo) {
            return [
                'lesson_id' => (int)$lesson['lesson_id'],
                'lesson_name' => $lessonsInfo[$lesson['lesson_id']]['lesson_name'] ?? '-',
                'collection_id' => $lessonsInfo[$lesson['lesson_id']]['collection_id'] ?? 0,
                'collection_name' => $lessonsInfo[$lesson['lesson_id']]['collection_name'] ?? '-',
                'total_time' => (int)$lesson['total_time'],
                'total_count' => (int)$lesson['total_count'],
                'ai_count' => (int)$lesson['ai_count'],
                'ai_max_score' => $lesson['ai_max_score'],
                'dynamic_count' => $lesson['total_count'] - $lesson['ai_count']

            ];
        }, $lessons);

        return $lessons;
    }

    /**
     * 日报详情动态演奏
     * @param array $filter
     * @return array
     */
    public static function reportDetailDynamic($filter)
    {
        $items = ReviewCourseModel::reportDetailDynamic($filter);
        $items = array_map(function ($item) {

            switch ($item['cfg_hand']) {
                case PlayRecordModel::CFG_HAND_LEFT:
                    $cfgHand = '左手';
                    break;
                case PlayRecordModel::CFG_HAND_RIGHT:
                    $cfgHand = '右手';
                    break;
                default:
                    $cfgHand = '双手';
            }

            switch ($item['cfg_mode']) {
                case PlayRecordModel::CFG_MODE_STEP:
                    $cfgMode = '识谱';
                    break;
                case PlayRecordModel::CFG_MODE_SLOW:
                    $cfgMode = '慢练';
                    break;
                default:
                    $cfgMode = 'PK';
            }

            return [
                'frag_key' => $item['frag_key'],
                'cfg_hand_lang' => $cfgHand,
                'cfg_mode_lang' => $cfgMode,
                'count' => (int)$item['count'],
                'max_score' => $item['max_score'],
            ];
        }, $items);

        return $items;
    }

    /**
     * 日报详情AI测评
     * @param array $filter
     * @return array
     */
    public static function reportDetailAI($filter)
    {
        $items = ReviewCourseModel::reportDetailAI($filter);

        $maxScore = -1;
        $maxScoreItemIdx = -1;

        $records = [];
        foreach ($items as $i => $item) {
            if ($item['score'] > $maxScore) {
                $maxScore = $item['score'];
                $maxScoreItemIdx = $i;
            }

            // AI数据分段或分手都算分段
            $isFrag = $item['is_frag'] || ($item['cfg_hand'] != PlayRecordModel::CFG_HAND_BOTH);

            $records[$i] = [
                'ai_record_id' => $item['ai_record_id'],
                'created_time' => $item['created_time'],
                'score' => $item['score'],
                'is_frag_lang' => $isFrag ? '是' : '-',
                'is_max_score_lang' => '-'
            ];
        }

        if ($maxScoreItemIdx >= 0) {
            $records[$maxScoreItemIdx]['is_max_score_lang'] = '是';
        }

        return $records;
    }

    /**
     * 日报详情上课模式测评
     * @param array $filter
     * @return array
     */
    public static function reportDetailClass($filter)
    {
        $items = ReviewCourseModel::reportDetailClass($filter);

        $records = [];
        foreach ($items as $i => $item) {
            $records[$i] = [
                'id' => $item['id'],
                'best_record_id' => $item['best_record_id'],
                'create_time' => $item['create_time'],
                'duration' => $item['duration'],
            ];
        }

        return $records;
    }

    /**
     * 发送点评
     * 从学生详情直接发送
     * @param int $studentId
     * @param int $reviewerId
     * @param int $date 20191201
     * @param string $audio
     * @return bool
     * @throws RunTimeException
     */
    public static function simpleReview($studentId, $reviewerId, $date, $audio)
    {
        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($studentId,
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
        );
        if (empty($studentWeChatInfo)) {
            throw new RunTimeException(['review_student_need_bind_wx']);
        }

        $reviewer = EmployeeModel::getById($reviewerId);
        if (empty($reviewer)) {
            throw new RunTimeException(['review_reviewer_not_exist']);
        }

        // 日期格式化为 20120101
        $dayTime = strtotime($date);
        if (empty($dayTime)) {
            throw new RunTimeException(['invalid_date']);
        }
        $date = date('Ymd', $dayTime);

        $now = time();
        $retId = ReviewCourseLogModel::insertRecord([
            'student_id' => $studentId,
            'reviewer_id' => $reviewerId,
            'date' => $date,
            'create_time' => $now,
            'audio' => $audio,
            'send_time' => $now,
        ], false);

        if (empty($retId)) {
            throw new RunTimeException(['insert_failure']);
        }

        $data = [
            'first' => [
                'value' => "老师点评通知",
            ],
            'keyword1' => [
                'value' => '小叶子智能陪练'
            ],
            'keyword2' => [
                'value' => '智能陪练课后点评'
            ],
            'keyword3' => [
                'value' => $reviewer['name'] ?? ''
            ],
            'keyword4' => [
                'value' => '点击查看老师的点评信息吧！'
            ],
            'remark' => [
                'value' => ''
            ]
        ];

        $result = WeChatService::notifyUserWeixinTemplateInfo(
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            $studentWeChatInfo["open_id"],
            $_ENV["WECHAT_TEMPLATE_REVIEW_COURSE"],
            $data,
            $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/review?date=" . $date
        );

        if (empty($result) || !empty($result['errcode'])) {
            throw new RunTimeException(['wx_send_fail']);
        }

        return true;
    }

    /**
     * 获取点评详情
     * @param $studentId
     * @param $date
     * @return array
     * @throws RunTimeException
     */
    public static function getReview($studentId, $date)
    {
        // 日期格式化为 20120101
        $dayTime = strtotime($date);
        if (empty($dayTime)) {
            throw new RunTimeException(['invalid_date']);
        }
        $date = date('Ymd', $dayTime);

        $data = ReviewCourseLogModel::getRecord(['student_id' => $studentId, 'date' => $date, 'ORDER' => ['id' => 'DESC']], '*', false);
        if (empty($data)) {
            return [];
        }

        $review = [
            'id' => $data['id'],
            'audio' => AliOSS::signUrls($data['audio']),
        ];

        return $review;
    }

    public static function QueueSendTaskReview($taskId){
        try {
            self::sendTaskReview($taskId);
        }catch (RunTimeException $e){
            SimpleLogger::error('send_task_review_message', ['filed' => $e]);
        }
    }
    /**
     * 发送点评
     * @param $taskId
     * @return string
     * @throws RunTimeException
     */
    public static function sendTaskReview($taskId)
    {
        $task = ReviewCourseTaskModel::getById($taskId);
        if (empty($task)) {
            throw new RunTimeException(['record_not_found']);
        }

        if ($task['status'] != ReviewCourseTaskModel::STATUS_INIT) {
            throw new RunTimeException(['review_task_has_been_send']);
        }

        $student = StudentModel::getById($task['student_id']);
        if (empty($student)) {
            throw new RunTimeException(['student_not_exist']);
        }

        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($task['student_id'],
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
        );
        if (empty($studentWeChatInfo)) {
            ReviewCourseTaskModel::updateRecord($taskId, [
                'status' => ReviewCourseTaskModel::STATUS_SEND_FAILURE,
                'update_time' => time()
            ]);

            $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
            $sms->sendReviewCompleteNotify($student['mobile'], $student['country_code']);

            return "发送成功 微信推送失败: [0] 学生未绑定公众号";
        }

        $dateStr = date('Y年m月d日', strtotime($task['play_date']));

        $data = [
            'first' => [
                'value' => "老师点评通知",
            ],
            'keyword1' => [
                'value' => '小叶子智能陪练'
            ],
            'keyword2' => [
                'value' => $dateStr . '智能陪练课后点评'
            ],
            'keyword3' => [
                'value' => '小叶子'
            ],
            'keyword4' => [
                'value' => '点击查看老师的点评信息吧！'
            ],
            'remark' => [
                'value' => ''
            ]
        ];

        $result = WeChatService::notifyUserWeixinTemplateInfo(
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            $studentWeChatInfo["open_id"],
            $_ENV["WECHAT_TEMPLATE_REVIEW_COURSE"],
            $data,
            $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/task_review?task_id=" . $task['id']
        );

        if (empty($result) || !empty($result['errcode'])) {

            ReviewCourseTaskModel::updateRecord($taskId, [
                'status' => ReviewCourseTaskModel::STATUS_SEND_FAILURE,
                'update_time' => time()
            ]);

            $code = $result['errcode'] ?? 0;
            $msg = $result['errmsg'] ?? '';

            $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
            $sms->sendReviewCompleteNotify($student['mobile'], $student['country_code']);

            return "发送成功 微信推送失败: [$code] $msg";
        }

        ReviewCourseTaskModel::updateRecord($taskId, [
            'status' => ReviewCourseTaskModel::STATUS_SEND_SUCCESS,
            'update_time' => time()
        ]);

        return '发送成功';
    }

    /**
     * 获取task对应的review
     * @param $studentId
     * @param $taskId
     * @return array
     * @throws RunTimeException
     */
    public static function getTaskReview($studentId, $taskId)
    {
        $task = ReviewCourseTaskModel::getById($taskId);
        if (empty($task)) {
            throw new RunTimeException(['record_not_found']);
        }

        if (!empty($studentId) && $studentId != $task['student_id']) {
            throw new RunTimeException(['record_not_found']);
        }

        $student = StudentModel::getById($task['student_id']);

        $report = AIPLClass::getClassReport($student['uuid'], strtotime($task['play_date']));

        $teacherId = $report['teacher_id'] ?? 0;
        $thumb = DictConstants::get(DictConstants::REVIEW_TEACHER_THUMB, $teacherId);
        $report['teacher_thumb'] = AliOSS::signUrls($thumb);

        $review = [
            'id' => $task['id'],
            'play_date' => $task['play_date'],
            'audio' => AliOSS::signUrls($task['review_audio']),
            'student_name' => $student['name'],
        ];
        $review = array_merge($review, $report);

        $resToken = AIBackendService::genStudentToken($student['id']);
        $review['token'] = $resToken;

        return $review;
    }

    /**
     * 更新点评课状态
     * @param $student
     * @param $package
     * @return null
     */
    public static function updateStudentReviewCourseStatus($student, $package)
    {
        $studentPackageType = $student['has_review_course'];
        // 更新点评课标记
        if (self::shouldUpdateReviewCourseStatus($student, $package)) {
            $studentPackageType = $package['package_type'];
            StudentModel::updateRecord($student['id'], ['has_review_course' => $package['package_type']]);
        }
        // 是否发奖
        if (self::shouldCompleteEventTask($student, $package)) {
            self::completeEventTask($student['uuid'], $package['package_type'], $package['trial_type'], $package['app_id']);
        }

        //同步用户付费状态信息到crm粒子数据中
        if ($package['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            QueueService::studentFirstPayNormalCourse($student['id']);
        }
        return $studentPackageType;
    }

    /**
     * 判断是否应该更新Student: has_review_course
     * @param $student
     * @param $package
     * @return bool
     */
    public static function shouldUpdateReviewCourseStatus($student, $package)
    {
        if ($package['package_type'] > $student['has_review_course']) {
            return true;
        } else {
            SimpleLogger::info('student has review course gt package type', ['has_review_course' => $student['has_review_course'], 'package_type' => $package['package_type']]);
            return false;
        }
    }

    /**
     * 判断是否应该完成任务及发放奖励
     * @param $student
     * @param $package
     * @return bool
     */
    public static function shouldCompleteEventTask($student, $package)
    {
        // 真人业务不发奖
        if ($package['app_id'] != PackageExtModel::APP_AI) {
            return false;
        }
        // 升级
        if ($package['package_type'] > $student['has_review_course']) {
            return true;
        } else {
            // 年包 && 首购智能陪练正式课
            if ($package['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
                // 所有年包：
                $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL, 'app_id' => PackageExtModel::APP_AI]), 'package_id');
                // 二期需求：
                // 判断用户是否是首次购买智能陪练正式课
                $hadPurchaseCount = GiftCodeModel::getCount(
                    [
                        'buyer'           => $student['id'],
                        'bill_package_id' => $packageIdArr
                    ]
                );
                if ($hadPurchaseCount <= 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 完成转介绍任务
     *
     * @param $uuid
     * @param $packageType
     * @param $trialType
     * @param $appId
     */
    public static function completeEventTask($uuid, $packageType, $trialType, $appId)
    {
        $refTaskId = null;
        //查询其介绍人
        $params       = ['uuid' => $uuid, 'field' => 'referrer_uuid'];
        $data         = ErpReferralService::getUserReferralInfo($params);
        $referralInfo = StudentModel::getRecord(['uuid' => $data[0]["referrer_uuid"]], ['has_review_course'], false);

        if ($packageType == PackageExtModel::PACKAGE_TYPE_TRIAL) {
            if (in_array($trialType, [PackageExtModel::TRIAL_TYPE_49, PackageExtModel::TRIAL_TYPE_9])) {
                // 购买49,9.9体验包完成转介绍任务
                if (in_array($referralInfo['has_review_course'], [ReviewCourseModel::REVIEW_COURSE_NO, ReviewCourseModel::REVIEW_COURSE_49])) {
                    // 若用户（推荐人）当前阶段为“已注册”或“付费体验课”
                    $refTaskId = ErpReferralService::getTrailPayTaskId();
                } elseif (in_array($referralInfo['has_review_course'], [ReviewCourseModel::REVIEW_COURSE_1980])) {
                    // 若用户（推荐人）当前阶段为“付费正式课”
                    $refTaskId = ErpReferralService::getTrailPayTaskId(1);
                }
            }
        } elseif ($packageType == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            if ($appId == PackageExtModel::APP_AI) {
                // 购买正式包完成转介绍任务
                if (in_array($referralInfo['has_review_course'], [ReviewCourseModel::REVIEW_COURSE_NO, ReviewCourseModel::REVIEW_COURSE_49])) {
                    // 若用户（推荐人）当前阶段为“已注册”或“付费体验课”
                    $refTaskId = ErpReferralService::getYearPayTaskId();
                } elseif (in_array($referralInfo['has_review_course'], [ReviewCourseModel::REVIEW_COURSE_1980])) {
                    // 若用户（推荐人）当前阶段为“付费正式课”
                    $refTaskId = ErpReferralService::getYearPayTaskId(1);
                }
            }
        }

        if (!empty($refTaskId)) {
            $erp = new Erp();
            $erp->updateTask($uuid, $refTaskId, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
        }
    }

    /**
     * 赠送时长 发送通知
     * @param $studentId
     * @param $collection
     */
    public static function giftCourseTimeAndSendNotify($studentId, $collection)
    {
        //获取学生信息
        $student = StudentModel::getById($studentId);
        // 发送奖励时长，奖励时长=开课日期-购买日期
        //  购买日 ----------- 开班前一天 --- 开班日 ------------- 结班日
        //    |                  |           |                   |
        // [2号周二   (赠送6天)  7号周日]    [8号周一  (购买14天)  21号周日]
        $giftCourseNum = ceil(($collection['teaching_start_time'] - strtotime('today')) / 86400);

        // 班课需要减少一天
        $giftCourseNum -= 1;
        if ($giftCourseNum > 0) {
            $applyType = PackageExtModel::APPLY_TYPE_AUTO;
            $generateChannel = GiftCodeModel::BUYER_TYPE_AI_REFERRAL;
            QueueService::giftDuration($student['uuid'], $applyType, $giftCourseNum, $generateChannel);
        }

        // 入班引导页面链接
        $wx = WeChatMiniPro::factory([
            'app_id' => $_ENV['STUDENT_WEIXIN_APP_ID'],
            'app_secret' => $_ENV['STUDENT_WEIXIN_APP_SECRET'],
        ]);
        $url = $wx->getShortUrl($_ENV['SMS_FOR_EXPERIENCE_CLASS_REGISTRATION'] . "?c=" . $collection['id']);
        $collection['collection_url'] = $url;

        //发送短信
        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $sms->sendCollectionCompleteNotify($student['mobile'], CommonServiceForApp::SIGN_STUDENT_APP, $collection, $student['country_code']);

        $now = time();
        $voiceCall = new VoiceCallTRService(DictConstants::get(DictConstants::VOICE_CALL_CONFIG, 'tianrun_voice_call_host'));
        $insert = [];
        $insert['receive_id'] = $student['id'];
        $insert['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $insert['receive_type'] = VoiceCallLogModel::RECEIVE_STUDENT;
        $insert['relate_schedule_id'] = $student['id'];
        $insert['create_time'] = $now;
        $insert['customer_number'] = $student['mobile'];
        $insert['call_type'] = VoiceCallLogModel::VOICE_TYPE_PURCHASE_EXPERIENCE_CLASS;

        $taskId = $voiceCall->createTask($insert);
        $insert['task_id'] = $taskId;
        $res = $voiceCall->execTask($insert, VoiceCallTRService::VOICE_TYPE_PURCHASE_EXPERIENCE_CLASS);
        if (!empty($res) && $res['result'] != 0 && $res['description'] == '超过企业并发限制') {
            usleep(200);
            $voiceCall->execTask($insert);
        }
    }

    /**
     * 更新点评课状态版本1使用（待新功能稳定，删除此方法）
     * @param $uuid
     * @param $packageType
     * @param $trialType
     * @param $appId
     * @param $package
     */
    public static function updateStudentReviewCourseStatusV1($uuid, $packageType, $trialType, $appId, $package)
    {
        $student = StudentService::getByUuid($uuid);
        $studentId = $student['id'];
        if ($student['has_review_course'] >= $packageType) {
            SimpleLogger::info('student has review course gt package type', ['has_review_course' => $student['has_review_course'], 'package_type' => $packageType]);
            return;
        } else {
            // 更新点评课标记
            $update = [
                'has_review_course' => $packageType,
            ];
            StudentModel::updateRecord($studentId, $update);
            self::completeEventTask($student['uuid'], $packageType, $trialType, $appId);
        }
        //同步用户付费状态信息到crm粒子数据中
        if ($packageType == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            QueueService::studentFirstPayNormalCourse($studentId);
            return ;
        }
        /**
         * 分配体验班级：已分配班级的学生禁止重复分班
         * 满足分配条件的班级选择规则
         * 有推荐人:
         *          (1)优先分配给推荐人所属助教的组班中的班级，不管班级启用状态，不管是否超过班容。
         *          (2)若推荐人的助教组班中的班级有多个班级，则分配最早创建的班级。
         *          (3)若推荐人的助教组班中的班级为0，则学员分班逻辑按“用户无推荐人”的分班逻辑进行。
         *          (4)学员加入推荐人的助教的班级后，占用班级当天的分配额度。
         * 没有推荐人:
         *          (1)通过启用状态,组班期时间,班级类型,授课类型,班级当前分配学生总量筛选出班级列表
         *          (2)可分配的班级按照当天进班的学生人数按照由低到高排序,取分配人数最低的班级, 如果人数相同则选择创建时间最早的班级
         */
        if (!empty($student['collection_id'])) {
            return ;
        }
        $collection = CollectionService::getCollectionByRefereeIdV1($studentId, $packageType, $trialType);
        if (empty($collection)) {
            $collection = CollectionService::getCollectionByCourseTypeV1($packageType, $trialType);
        }

        if (empty($collection)) {
            return ;
        }

        // 分配助教和班级
        $success = StudentService::allotCollectionAndAssistant($student['id'], $collection, EmployeeModel::SYSTEM_EMPLOYEE_ID, $package);
        if ($success) {
            SimpleLogger::error('student update collection and assistant error', []);
        }

        // 体验班级 赠送时长 发送通知
        if (($collection['type'] != CollectionModel::COLLECTION_TYPE_NORMAL) ||
            ($packageType != PackageExtModel::PACKAGE_TYPE_TRIAL)) {
            return ;
        }
        self::giftCourseTimeAndSendNotify($student['id'], $collection);
    }
}
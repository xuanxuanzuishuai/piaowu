<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 11:47 AM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use App\Models\ReviewCourseLogModel;
use App\Models\ReviewCourseTaskModel;
use GuzzleHttp\Exception\GuzzleException;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\PlayRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;

class ReviewCourseService
{
    /**
     * 更新点评课标记
     * has_review_course = 0没有|1课包49|2课包1980
     * 只能从低级更新到高级
     *
     * @param $studentID
     * @param $reviewCourseType
     * @param null|int $wechatcsId
     * @return null|string errorCode
     */
    public static function updateReviewCourseFlag($studentID, $reviewCourseType, $wechatcsId = null)
    {
        $student = StudentModel::getById($studentID);
        if ($student['has_review_course'] >= $reviewCourseType) {
            return null;
        }

        $update = [
            'has_review_course' => $reviewCourseType,
        ];
        if (!empty($wechatcsId)) {
            $update['wechatcs_id'] = $wechatcsId;
        }
        $affectRows = StudentModel::updateRecord($studentID, $update, false);

        if($affectRows == 0) {
            return 'update_student_fail';
        }

        return null;
    }

    /**
     * 根据订单的 packageId 获取点评课标记
     * @param $packageId
     * @return int
     */
    public static function getBillReviewCourseType($packageId)
    {
        $package49 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id');
        if ($packageId == $package49) {
            return ReviewCourseModel::REVIEW_COURSE_49;
        }

        $package1980 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'plus_package_id');
        if ($packageId == $package1980) {
            return ReviewCourseModel::REVIEW_COURSE_1980;
        }

        return ReviewCourseModel::REVIEW_COURSE_NO;
    }

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
                'value' => '小叶子智能陪练课'
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

        try {
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
        } catch (GuzzleException $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
            return false;
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

        if (empty($task['review_audio'])) {
            throw new RunTimeException(['record_not_found']);
        }

        if ($task['status'] != ReviewCourseTaskModel::STATUS_INIT) {
            throw new RunTimeException(['review_task_has_been_send']);
        }

        $student = StudentModel::getById($task['student_id']);
        if (empty($student)) {
            throw new RunTimeException(['student_not_exist']);
        }

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $sms->sendReviewCompleteNotify($student['mobile']);

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
            return "发送成功 微信推送失败: [0] 学生未绑定公众号";
        }

        $dateStr = date('Y年m月d日', strtotime($task['play_date']));

        $data = [
            'first' => [
                'value' => "老师点评通知",
            ],
            'keyword1' => [
                'value' => '小叶子智能陪练课'
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

        try {
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

                return "发送成功 微信推送失败: [$code] $msg";
            }

        } catch (GuzzleException $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);

            throw new RunTimeException(['wx_send_fail']);
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
        if (empty($task) || $studentId != $task['student_id']) {
            throw new RunTimeException(['record_not_found']);
        }

        $review = [
            'id' => $task['id'],
            'play_date' => $task['play_date'],
            'audio' => AliOSS::signUrls($task['review_audio']),
        ];

        return $review;
    }
}
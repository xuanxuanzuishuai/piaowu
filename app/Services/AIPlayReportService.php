<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:28 PM
 */

namespace App\Services;

use App\Libs\AIPLCenter;
use App\Libs\AIPLClass;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AIPlayRecordCHModel;
use App\Models\AIPlayRecordModel;
use App\Models\DayReportFabulousModel;
use App\Models\HolidaysModel;
use App\Models\PointActivityRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\ReviewCourseTaskModel;
use App\Models\StudentLearnRecordModel;
use App\Models\StudentModel;
use App\Models\StudentSignUpCourseModel;
use App\Models\StudentWeekReportModel;
use App\Models\UserQrTicketModel;
use App\Services\Queue\PushMessageTopic;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;
use App\Libs\DictConstants;
use Lcobucci\JWT\Parser;

class AIPlayReportService
{
    const signKey = "wblMloJrdkUwIxVLchlXB9Unvr68dJo";

    public static function getShareReportToken($studentId, $date)
    {
        $builder = new Builder();
        $signer = new Sha256();
        $builder->set("student_id", $studentId);
        $builder->set("date", $date);
        $builder->sign($signer, self::signKey);
        $token = $builder->getToken();
        return (string)$token;
    }

    /**
     * 解析jwt获取信息
     * @param $token
     * @return array
     * @throws RunTimeException
     */
    public static function parseShareReportToken($token)
    {
        $parse = (new Parser())->parse((string)$token);
        $signer = new Sha256();
        if (!$parse->verify($signer, self::signKey)) {
            throw new RunTimeException(['error_share_token']);
        };
        $studentId = $parse->getClaim("student_id");
        $date = $parse->getClaim("date");
        return ["student_id" => $studentId, "date" => $date];
    }

    /**
     * 学生练琴日报
     * @param $studentId
     * @param null $date
     * @return array
     * @throws RunTimeException
     */
    public static function getDayReport($studentId, $date = null)
    {
        $recommendCourseDate = [];
        if (empty($date)) {
            $date = date('Ymd');
        }

        $report = AIPlayRecordService::getDayReportData($studentId, $date);
        //获取用户的今日课程，没有课程获取推荐课包
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $todayClass = InteractiveClassroomService::studentCoursePlan($opn, $studentId, strtotime($date));
        if (empty($todayClass)) {
            $recommendCourses = InteractiveClassroomService::recommendCourse($opn, $studentId);
        } else{
            $recommendCourses = [];
        }

        foreach ($recommendCourses as $recommendCourse) {
            if ($recommendCourse['course_bind_status'] != StudentSignUpCourseModel::COURSE_BING_ERROR) {
                continue;
            }
            $recommendCourseDate[] = $recommendCourse;
        }
        $report['today_class'] = array_values($todayClass);
        $report['recommend_course'] = array_values($recommendCourseDate);
        $report["day_report_fabulous"] = self::getDayReportFabulous($studentId, $date);
        $report["share_token"] = self::getShareReportToken($studentId, $date);
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        return $report;
    }

    /**
     * 学生练琴日报(分享)
     * @param $shareToken
     * @return array
     * @throws RunTimeException
     */
    public static function getSharedDayReport($shareToken)
    {
        $recommendCourseDate = [];
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $report = AIPlayRecordService::getDayReportData($shareTokenInfo["student_id"], $shareTokenInfo["date"]);

        $channel_id = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, 'shared_day_report_channel_id');
        $TicketData = UserService::getUserQRAliOss($shareTokenInfo["student_id"], 1, $channel_id);
        $playShareAssessUrl = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'play_share_assess_url');
        $data = array(
            'ad' => 0,
            'channel_id' => $channel_id,
            'referee_id' => $TicketData['qr_ticket']
        );

        //获取用户的今日课程，没有课程获取推荐课包
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $todayClass = InteractiveClassroomService::studentCoursePlan($opn,$shareTokenInfo["student_id"], strtotime($shareTokenInfo["date"]));
        if (empty($todayClass)) $recommendCourses = InteractiveClassroomService::recommendCourse($opn, $shareTokenInfo['student_id']); else{
            $recommendCourses = [];
        }
        foreach ($recommendCourses as $recommendCourse) {
            if ($recommendCourse['course_bind_status'] != StudentSignUpCourseModel::COURSE_BING_ERROR) {
                continue;
            }
            $recommendCourseDate[] = $recommendCourse;
        }
        $report['share_url'] = $playShareAssessUrl . '?' . http_build_query($data);
        $report['qr_ticket_image'] = empty($TicketData['qr_url']) ? '' : AliOSS::signUrls($TicketData['qr_url']);
        $report["share_token"] = $shareToken;
        $report['replay_token'] = AIBackendService::genStudentToken($shareTokenInfo["student_id"]);
        $report["day_report_fabulous"] = self::getDayReportFabulous($shareTokenInfo["student_id"], $shareTokenInfo["date"]);
        $report['today_class'] = array_values($todayClass);
        $report['recommend_course'] = array_values($recommendCourseDate);
        return $report;
    }

    /**
     * 日报点赞
     * @param $shareToken
     * @param $openId
     * @return array|string[]
     * @throws RunTimeException
     */
    public static function dayReportFabulous($shareToken, $openId)
    {
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $fabulousRew = DayReportFabulousModel::getRecord(['student_id' => $shareTokenInfo['student_id'], 'open_id' => $openId, 'day_report_date' => $shareTokenInfo["date"]], ['id']);

        if (!empty($fabulousRew)) {
            throw new RunTimeException(['student_is_fabulous']);
        }

        $id = DayReportFabulousModel::insertRecord(['student_id' => $shareTokenInfo['student_id'], 'open_id' => $openId, 'create_time' => time(), 'day_report_date' => $shareTokenInfo["date"]], false);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * 日报自己给自己点赞
     * @param $openId
     * @param $studentId
     * @param $date
     * @return array|string[]
     * @throws RunTimeException
     */
    public static function dayReportOneSelfFabulous($openId, $studentId, $date)
    {
        $startTime = strtotime($date);
        $endTime = $startTime + 86399;
        $aiPlayRecord = AIPlayRecordModel::getRecord(['student_id' => $studentId, 'end_time[<>]' => [$startTime, $endTime]]);

        if (strtotime($date) > time() || empty($aiPlayRecord)) {
            throw new RunTimeException(['Please fill in the correct date']);
        }

        $fabulousRew = DayReportFabulousModel::getRecord(['student_id' => $studentId, 'open_id' => $openId, 'day_report_date' => $date], ['id']);

        if (!empty($fabulousRew)) {
            throw new RunTimeException(['student_is_fabulous']);
        }

        $id = DayReportFabulousModel::insertRecord(['student_id' => $studentId, 'open_id' => $openId, 'create_time' => time(), 'day_report_date' =>$date], false);
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * 统计日报点赞数
     * @param $studentId
     * @param $date
     * @return int|number
     */
    public static function getDayReportFabulous($studentId, $date)
    {
        $dayReportFabulous = DayReportFabulousModel::getTotalCount($studentId, $date);
        return $dayReportFabulous ?? 0;
    }

    /**
     * 获取学生练琴日历
     * @param $studentId
     * @param $year
     * @param $month
     * @return array
     */
    public static function getPlayCalendar($studentId, $year, $month)
    {
        $startTime = strtotime($year . "-" . $month);
        $endTime = strtotime('+1 month', $startTime) - 1;
        $monthSum = AIPlayRecordModel::getStudentSumByDate($studentId, $startTime, $endTime);

        $where = [
            'play_date[>=]' => date('Ymd', $startTime),
            'play_date[<=]' => date('Ymd', $endTime),
            's.id' => $studentId,
            's.has_review_course' => [ReviewCourseModel::REVIEW_COURSE_49, ReviewCourseModel::REVIEW_COURSE_1980],
            'rct.status' => [ReviewCourseTaskModel::STATUS_SEND_SUCCESS, ReviewCourseTaskModel::STATUS_SEND_FAILURE],
        ];
        list($total, $tasks) = ReviewCourseTaskModel::getTasks($where);
        if ($total > 0) {
            $tasks = array_column($tasks, 'id', 'play_date');
        }

        foreach ($monthSum as $i => $daySum) {
            $monthSum[$i]['review_task_id'] = $tasks[$daySum['play_date']] ?? 0;
        }

        return $monthSum;
    }

    /**
     * 单课成绩单
     * @param $studentId
     * @param $lessonId
     * @param null $date
     * @return array
     * @throws RunTimeException
     */
    public static function getLessonTestReport($studentId, $lessonId, $date = null)
    {
        if (empty($date)) {
            $date = date('Ymd');
        }

        $report = AIPlayRecordService::getLessonTestReportData($studentId, $lessonId, $date);
        $report["share_token"] = self::getShareReportToken($studentId, $date);
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        return $report;
    }

    /**
     * 单课成绩单(分享)
     * @param $shareToken
     * @param $lessonId
     * @return array
     * @throws RunTimeException
     */
    public static function getSharedLessonTestReport($shareToken, $lessonId)
    {
        $shareTokenInfo = AIPlayReportService::parseShareReportToken($shareToken);

        $report = AIPlayRecordService::getLessonTestReportData($shareTokenInfo["student_id"], $lessonId, $shareTokenInfo["date"]);
        $report["share_token"] = $shareToken;
        $report['replay_token'] = AIBackendService::genStudentToken($shareTokenInfo["student_id"]);
        return $report;
    }

    /**
     * 获取学生评测报告（分享
     * @param $recordId
     * @return array|mixed
     */
    public static function getAssessResult($recordId)
    {
        $report = AIPlayRecordService::getStudentAssessData($recordId);
        return $report;
    }

    /**
     * 发送日报
     * @param $dateTime
     */
    public static function sendDailyReport($dateTime)
    {
        $startTime = $dateTime;
        $endTime = $startTime + 86400;
        $date = date("Y-m-d", $startTime);

        $userInfo = AIPlayRecordModel::getPlayedStudentInfo($startTime, $endTime);

        $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/dailyPaper?date=" . $date;
        $templateId = $_ENV["WECHAT_DAY_PLAY_REPORT"];
        $data = [
            'first' => [
                'value' => "宝贝今天的练琴日报已生成，宝贝很棒哦！继续加油！",
                'color' => "#323d83"
            ],
            'keyword1' => [
                'value' => $date,
                'color' => "#323d83"
            ],
            'keyword2' => [
                'value' => "请查看详情",
                'color' => "#323d83"
            ],
            'keyword3' => [
                'value' => "请查看详情",
                'color' => "#323d83"
            ],
        ];
        $msgBody = [
            'wx_push_type' => 'template',
            'template_id' => $templateId,
            'data' => $data,
            'url' => $url,
            'open_id' => '',
        ];

        try {
            $topic = new PushMessageTopic();

        } catch (\Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', [
                '$dateTime' => $dateTime,
            ]);
            return ;
        }

        foreach ($userInfo as $info) {
            $msgBody['open_id'] = $info['open_id'];

            try {
                $topic->wxPushCommon($msgBody)->publish(rand(0, 1200));

            } catch (\Exception $e) {
                SimpleLogger::error("sendDailyReport send failure", ['info' => $info]);
                continue;
            }
        }
    }

    /**
     * @param $task_id
     * @param $date
     * @param $student_id
     * @return string
     * 获取点评报告分享链接
     */
    public static function reviewCourseReportUrl($task_id, $date, $student_id)
    {
        if (empty($task_id) || empty($date) || empty($student_id)) {
            return '';
        }
        $reviewCourseReportHost = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'review_course_report');
        $task_id = "?task_id=" . $task_id;
        $jwt = "&jwt=" . AIPlayReportService::getShareReportToken($student_id, $date) ?? '';
        return $reviewCourseReportHost . $task_id . $jwt;
    }

    /**
     * @param $date
     * @param $student_id
     * @return string
     * 获取日报分享链接
     */
    public static function dailyReportUrl($date, $student_id)
    {
        if (empty($date) || empty($student_id)) {
            return '';
        }
        $dailyReportHost = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'review_daily');
        $formatDate = "?date=" . date("Y-m-d", strtotime($date));
        $jwt = "&jwt=" . AIPlayReportService::getShareReportToken($student_id, $date) ?? '';
        return $dailyReportHost . $formatDate . $jwt;
    }

    /**
     * 学生练琴周报
     * @param $studentId
     * @param $reportId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getWeekReport($studentId, $reportId)
    {
        $studentInfo = StudentModel::getById($studentId);
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $report = StudentWeekReportModel::getRecord(
            [
                'id' => $reportId,
                'student_id' => $studentId
            ],
            ['basic_info', 'ai_comment', 'progress', 'tasks', 'is_pass', 'start_time', 'end_time'],
            false);
        if (empty($report)) {
            return [];
        }
        //获取演奏曲目的音频文件地址与课程信息
        $progressData = json_decode($report['progress'], true);
        if (is_array($progressData) && !empty($progressData)) {
            //获取课程信息
            $lessonIds = array_unique(array_column($progressData, 'lesson_id'));
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, AIPlayRecordService::DEFAULT_APP_VER);
            $res = $opn->lessonsByIds($lessonIds);
            $lessonInfo = [];
            if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
                $lessonInfo = array_column($res['data'], null, 'lesson_id');
            }
            $weekReportConfig = DictConstants::getSet(DictConstants::WEEK_REPORT_CONFIG);
            //音频文件：只获取首页的前n条数据，其余数据在详情页通过单独接口获取
            $i = 1;
            $audioLimit = $weekReportConfig['audio_limit'];
            array_walk($progressData, function (&$pv) use ($lessonInfo, $audioLimit, &$i) {
                //音频文件
                $pv['audio_url'] = '';
                if ($i <= $audioLimit) {
                    $pv['audio_url'] = AIPLCenter::userAudio($pv['record_id'])['data']['audio_url'] ?? '';
                }
                $pv['collection_name'] = $lessonInfo[$pv['lesson_id']]['collection_name'];
                $pv['lesson_name'] = $lessonInfo[$pv['lesson_id']]['lesson_name'];
                $i++;
            });
        }
        $report['progress'] = json_encode($progressData);
        $report['name'] = $studentInfo['name'];
        $report['uuid'] = $studentInfo['uuid'];
        $report['thumb'] = $studentInfo['thumb'] ? AliOSS::replaceCdnDomainForDss($studentInfo['thumb']) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
        $report["share_token"] = self::getShareReportToken($studentId, $reportId);
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        //周报分享链接
        $report['share_assess_url'] = self::makeWeekReportShareLink($studentId);
        return $report;
    }

    /**
     * 生成周报分享链接地址
     * @param $studentId
     * @return string
     */
    private static function makeWeekReportShareLink($studentId)
    {
        $weekReportAssessUrl = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'week_report_share_assess_url');
        $channelId = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, 'week_report_share_channel_id');
        $TicketData = UserService::getUserQRAliOss($studentId, UserQrTicketModel::STUDENT_TYPE, $channelId);
        $data = array(
            'ad' => 0,
            'channel_id' => $channelId,
            'referee_id' => $TicketData['qr_ticket']
        );
        return $weekReportAssessUrl . '?' . http_build_query($data);
    }

    /**
     * 练琴周报:练琴详情
     * @param $studentId
     * @param $reportId
     * @return mixed
     * @throws RunTimeException
     */
    public static function weekReportPlayDetail($studentId, $reportId)
    {
        $studentInfo = StudentModel::getById($studentId);
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $detailData = [
            'reports' => [],
            'amended_statistics' => [],
        ];
        $reportDetail = StudentWeekReportModel::getRecord(
            [
                'id' => $reportId,
                'student_id' => $studentId
            ],
            ['year', 'week'],
            false);
        if (empty($reportDetail)) {
            return $detailData;
        }
        //请求python获取数据
        $reportRequestData = AIPLClass::getWeekReport($studentInfo['uuid'], $reportDetail['year'], $reportDetail['week']);
        if (empty($reportRequestData)) {
            return $detailData;
        }
        if (!empty($reportRequestData['reports'])) {
            $lessonIds = array_column($reportRequestData['reports'], 'lesson_id');
            //获取课程信息
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, AIPlayRecordService::DEFAULT_APP_VER);
            $res = $opn->lessonsByIds($lessonIds);
            if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
                $lessonInfo = array_column($res['data'], null, 'lesson_id');
                array_walk($reportRequestData['reports'], function (&$rv, $k) use ($lessonInfo, &$detailData) {
                    $detailData['reports'][] = [
                        'collection_name'=>$lessonInfo[$rv['lesson_id']]['collection_name'],
                        'lesson_id'=>$rv['lesson_id'],
                        'lesson_name'=>$rv['lesson_name'],
                        'problem_solutions'=>$rv['problem_solutions'],
                        'review_data'=>$rv['review_data'],
                    ];
                });
            }
        }
        $detailData['amended_statistics'] = $reportRequestData['amended_statistics'];
        return $detailData;
    }

    /**
     * 练琴周报(分享)
     * @param $shareToken
     * @return array
     * @throws RunTimeException
     */
    public static function getSharedWeekReport($shareToken)
    {
        //解析token数据
        $shareTokenInfo = self::parseShareReportToken($shareToken);
        return self::getWeekReport($shareTokenInfo["student_id"], $shareTokenInfo["date"]);
    }


    /**
     * 练琴周报:练琴详情(分享)
     * @param $shareToken
     * @return mixed
     * @throws RunTimeException
     */
    public static function getShareWeekReportPlayDetail($shareToken)
    {
        //解析token数据
        $shareTokenInfo = self::parseShareReportToken($shareToken);
        return self::weekReportPlayDetail($shareTokenInfo["student_id"], $shareTokenInfo["date"]);
    }

    /**
     * 生成学生练琴周报
     * @param $studentIdList
     * @param $startTime
     * @param $endTime
     * @param $year
     * @param $week
     * @return bool
     */
    public static function makeStudentWeekReport($studentIdList, $startTime, $endTime, $year, $week)
    {
        $totalPlayData = $staticsData = $studentCompleteTasks = [];
        $time = time();
        //非怀旧模式练琴数据
        $playData = AIPlayRecordCHModel::getStudentSumByDate($studentIdList, $startTime, $endTime);
        //怀旧模式练琴数
        $goldenPicturePlayData = AIPlayRecordCHModel::getStudentSumByDateGoldenPicture($studentIdList, $startTime, $endTime);
        //互动课堂数据
        $classRoom = array_column(StudentLearnRecordModel::getStudentCompleteCount(implode(',', $studentIdList), $startTime, $endTime), null, 'student_id');
        //获取当前周平台可上课程数量
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $classCount = count(InteractiveClassroomService::erpCollection($opn));
        //周任务完成数据
        $weekReportConfig = DictConstants::getSet(DictConstants::WEEK_REPORT_CONFIG);
        $studentCompleteTasks = self::getStudentFinishTasksBetweenTime($studentIdList, $startTime, $endTime, $weekReportConfig);
        //获取周进步数据
        $lessonProgressData = self::getLessonProgressData($studentIdList, $startTime, $endTime, $weekReportConfig['diff_score'], $weekReportConfig['min_score_final']);
        //统计数据
        array_map(function ($playVal) use (&$totalPlayData) {
            //练琴天数
            $totalPlayData[$playVal['student_id']]['play_days'][$playVal['create_date']] = 1;
            //练琴时长
            $sumDuration = empty($totalPlayData[$playVal['student_id']]['play_durations']) ? 0 : (int)$totalPlayData[$playVal['student_id']]['play_durations'];
            $totalPlayData[$playVal['student_id']]['play_durations'] = $playVal['sum_duration'] + $sumDuration;
        }, array_merge($playData, $goldenPicturePlayData));
        //获取其他人数据
        $otherData = self::getOtherStudentRandPlayData($classCount);
        //获取学生本周演奏的曲目数量
        $studentPlayLessonCountData = array_column(AIPlayRecordCHModel::getStudentLessonCountBetweenTime($studentIdList, $startTime, $endTime), null, 'student_id');
        array_map(function ($studentId) use ($totalPlayData, $classRoom, &$staticsData, $otherData, $weekReportConfig, $studentCompleteTasks, $lessonProgressData, $startTime, $endTime, $week, $year, $time, $studentPlayLessonCountData) {
            //练琴天数
            $basicInfo = [];
            $playDays = count($totalPlayData[$studentId]['play_days']);
            $basicInfo['data']['days']['self'] = $playDays;
            $basicInfo['data']['days']['other'] = $otherData['play_days'];
            //平均练琴时长
            $basicInfo['data']['duration']['self'] = ceil(($totalPlayData[$studentId]['play_durations'] / 60) / $playDays);
            $basicInfo['data']['duration']['other'] = $otherData['average_duration'];
            //互动课堂
            $basicInfo['data']['class']['self'] = empty($classRoom[$studentId]['class_count']) ? 0 : $classRoom[$studentId]['class_count'];
            $basicInfo['data']['class']['other'] = $otherData['class_count'];
            //练琴曲目数，总时长
            $basicInfo['extra']['lesson_count'] = $studentPlayLessonCountData[$studentId]['lesson_count'];
            $basicInfo['extra']['sum_durations'] = $totalPlayData[$studentId]['play_durations'];

            //任务完成度
            $completePer = round($studentCompleteTasks[$studentId]['total_complete_count'] / $weekReportConfig['tasks_total_count'], 2);
            $basicInfo['data']['task']['self'] = $completePer;
            $basicInfo['data']['task']['other'] = $otherData['task'];
            //评语
            $staticsData[$studentId]['ai_comment'] = self::getAiComment($weekReportConfig, $basicInfo['data']);
            //周练琴完成任务列表
            $tasks['list'] = array_values($studentCompleteTasks[$studentId]['list']);
            $tasks['complete_per'] = $completePer;
            $tasks['total_complete_count'] = $studentCompleteTasks[$studentId]['total_complete_count'];
            //本周成绩是否及格
            $staticsData[$studentId]['is_pass'] = (int)($completePer >= $weekReportConfig['pass_line']);
            //进步曲目
            $progress = empty($lessonProgressData[$studentId]) ? (object)[] : $lessonProgressData[$studentId];
            //入库数据
            $staticsData[$studentId]['student_id'] = $studentId;
            $staticsData[$studentId]['week'] = $week;
            $staticsData[$studentId]['year'] = $year;
            $staticsData[$studentId]['create_time'] = $time;
            $staticsData[$studentId]['update_time'] = $time;
            $staticsData[$studentId]['start_time'] = $startTime;
            $staticsData[$studentId]['end_time'] = $endTime;
            $basicInfo['data'] = array_values($basicInfo['data']);
            $staticsData[$studentId]['basic_info'] = json_encode($basicInfo);
            $staticsData[$studentId]['tasks'] = json_encode($tasks);
            $staticsData[$studentId]['progress'] = json_encode($progress);
        }, $studentIdList);
        return StudentWeekReportModel::batchInsert(array_values($staticsData));
    }

    /**
     * 获取用户某段时间内进步曲目
     * @param $studentIdList
     * @param $startTime
     * @param $endTime
     * @param $diffScore
     * @param $scoreFinal
     * @return array
     */
    private static function getLessonProgressData($studentIdList, $startTime, $endTime, $diffScore, $scoreFinal)
    {
        $progressData = $lessonInfo = [];
        $playLessonData = AIPlayRecordCHModel::getStudentMaxAndMinScoreByLesson($studentIdList, $startTime, $endTime, $diffScore, $scoreFinal);
        if (empty($playLessonData)) {
            return $progressData;
        }
        //获取课程信息
        $lessonIds = array_unique(array_column($playLessonData, 'lesson_id'));
        $lessonRankTime = ['start_time' => $startTime, 'end_time' => $endTime];
        //获取课程演奏排行榜数据
        $rankList = AIPlayRecordCHModel::getLessonPlayRankList($lessonIds, $lessonRankTime);
        $allStudentId = array_unique(array_column($rankList, 'student_id'));
        $students = StudentModel::getRecords([
            'id' => $allStudentId,
            'is_join_ranking' => StudentModel::STATUS_JOIN_RANKING_ABLE
        ], ['id']);
        $studentListInfo = array_column($students, NULL, 'id');
        array_map(function ($rankVal) use (&$lessonRankData, $studentListInfo) {
            if (!empty($studentListInfo[$rankVal['student_id']])) {
                $lessonRankData[$rankVal['lesson_id']][] = $rankVal;
            }
        }, $rankList);
        array_map(function ($lid) use ($lessonRankData) {
            if (empty(AIPlayRecordCHModel::checkWeekLessonCacheExists($lid))) {
                if (empty($lessonRankData[$lid])) {
                    //此曲目没有排行榜数据进行占位
                    $cacheData = ['0' => 0];
                } else {
                    $cacheData = array_column($lessonRankData[$lid], 'score', 'student_id');
                }
                AIPlayRecordCHModel::setWeekLessonRankCache($lid, $cacheData);
            }
        }, $lessonIds);
        //获取每个曲目得分第一名的record_id
        $studentMaxScoreData = AIPlayRecordCHModel::getLessonMaxScoreRecordId($studentIdList, $lessonIds, $lessonRankTime);
        array_map(function ($rankVal) use (&$studentLessonRankData) {
            $studentLessonRankData[$rankVal['student_id']][$rankVal['lesson_id']] = $rankVal;
        }, $studentMaxScoreData);
        array_map(function ($lv) use (&$progressData, $studentLessonRankData) {
            $pdv['is_in_rank'] = is_null(AIPlayRecordCHModel::getWeekLessonRankCache($lv['lesson_id'], $lv['student_id'])) ? false : true;
            $pdv['record_id'] = $studentLessonRankData[$lv['student_id']][$lv['lesson_id']]['record_id'];
            $pdv['score_diff'] = (string)round($lv['score_diff'], 1);
            $pdv['min_score'] = (string)round($lv['min_score'], 1);
            $pdv['max_score'] = (string)round($lv['max_score'], 1);
            $pdv['lesson_id'] = $lv['lesson_id'];
            $progressData[$lv['student_id']][] = $pdv;
        }, $playLessonData);
        return $progressData;
    }

    /**
     * 获取周任务完成数据
     * @param $studentIdList
     * @param $startTime
     * @param $endTime
     * @param $weekReportConfig
     * @return array
     */
    private static function getStudentFinishTasksBetweenTime($studentIdList, $startTime, $endTime, $weekReportConfig)
    {
        $taskData = [];
        $tasksList = [
            $weekReportConfig['play_piano_30_minutes'],
            $weekReportConfig['evaluation_the_whole_song'],
            $weekReportConfig['sound_base_questions'],
            $weekReportConfig['watch_demo_video'],
            $weekReportConfig['difficult_points'],
        ];
        $tasks = PointActivityRecordModel::getStudentFinishTasksBetweenTime($studentIdList, $startTime, $endTime, $tasksList);
        //初始化任务列表
        foreach ($tasksList as $tid) {
            $taskData['total_complete_count'] = 0;
            $taskData['list'][$tid] = [
                'id' => $tid,
                'name' => $weekReportConfig[$tid],
                'complete_count' => 0,
            ];
        }
        $studentTaskList = array_fill_keys($studentIdList, $taskData);
        array_map(function ($tVal) use (&$studentTaskList, &$totalCompleteTasksCount, $weekReportConfig) {
            $studentTaskList[$tVal['student_id']]['list'][$tVal['task_id']] = [
                'id' => $tVal['task_id'],
                'name' => $weekReportConfig[$tVal['task_id']],
                'complete_count' => $tVal['cm'],
            ];
            $studentTaskList[$tVal['student_id']]['total_complete_count'] += $tVal['cm'];
        }, $tasks);
        return $studentTaskList;
    }

    /**
     * 获取对比数据
     * @param $classCount
     * @return array
     */
    private static function getOtherStudentRandPlayData($classCount)
    {
        $randData = self::weekReportRandData(time());
        $otherStudentRandData = [
            'play_days' => self::randomFloat($randData['days']['min'], $randData['days']['max']),
            'average_duration' => mt_rand($randData['duration']['min'], $randData['duration']['max']),
            'class_count' => self::randomFloat($classCount * $randData['class']['min'], $classCount * $randData['class']['min']),
            'task' => mt_rand($randData['task']['min'], $randData['task']['max']) / 100,
        ];
        return $otherStudentRandData;
    }


    /**
     * 获取评语
     * @param $weekReportConfig
     * @param $studentPlayData
     * @return string
     */
    private static function getAiComment($weekReportConfig, $studentPlayData)
    {
        $playDaysStatus = $averageDurationStatus = $classCountStatus = $taskStatus = false;
        $taskList['transcend'] = $taskList['disparity'] = [];
        if ($studentPlayData['days']['self'] >= $studentPlayData['days']['other']) {
            $playDaysStatus = true;
            array_push($taskList['transcend'], $weekReportConfig['compare_play_days_name']);
        } else {
            array_push($taskList['disparity'], $weekReportConfig['compare_play_days_name']);
        }
        if ($studentPlayData['duration']['self'] >= $studentPlayData['duration']['other']) {
            $averageDurationStatus = true;
            array_push($taskList['transcend'], $weekReportConfig['compare_average_duration_name']);
        } else {
            array_push($taskList['disparity'], $weekReportConfig['compare_average_duration_name']);
        }
        if ($studentPlayData['class']['self'] >= $studentPlayData['class']['other']) {
            $classCountStatus = true;
            array_push($taskList['transcend'], $weekReportConfig['compare_class_name']);
        } else {
            array_push($taskList['disparity'], $weekReportConfig['compare_class_name']);
        }
        if ($studentPlayData['task']['self'] >= $studentPlayData['task']['other']) {
            $taskStatus = true;
            array_push($taskList['transcend'], $weekReportConfig['compare_task_name']);
        } else {
            array_push($taskList['disparity'], $weekReportConfig['compare_task_name']);
        }
        if (empty($studentPlayData['days']['self'])) {
            $aiComment = $weekReportConfig['ai_comment_default'];
        } elseif (($playDaysStatus == true) &&
            ($averageDurationStatus == true) &&
            ($classCountStatus == true) &&
            ($taskStatus == true)) {
            $aiComment = $weekReportConfig['ai_comment_perfect'];
        } elseif (($playDaysStatus == false) &&
            ($averageDurationStatus == false) &&
            ($classCountStatus == false) &&
            ($taskStatus == false)) {
            $aiComment = $weekReportConfig['ai_comment_bad'];
        } else {
            $aiComment = str_replace(["good", "bad"], [implode('、', $taskList['transcend']), implode('、', $taskList['disparity'])], $weekReportConfig['ai_comment_middle']);
        }
        return $aiComment;
    }

    /**
     * 获取学生练琴周报
     * @param $studentId
     * @param $year
     * @param $month
     * @return array
     */
    public static function getCalendarWeekReport($studentId, $year, $month)
    {
        $startTime = strtotime($year . "-" . $month);
        $endTime = strtotime('+1 month', $startTime) - 1;
        $data = StudentWeekReportModel::getRecords(
            [
                'student_id' => $studentId,
                'create_time[>=]' => $startTime,
                'create_time[<=]' => $endTime],
            ['id', 'create_time'], false);
        $weekReportData = [];
        if (empty($data)) {
            return $weekReportData;
        }
        array_map(function ($wv) use (&$weekReportData) {
            $weekReportData[] = [
                "play_date" => date("Ymd", $wv['create_time']),
                "report_id" => $wv['id'],
            ];
        }, $data);
        return $weekReportData;
    }

    /**
     * 生成随机浮点数
     * @param int $min
     * @param int $max
     * @param int $float
     * @return float|int
     */
    public static function randomFloat($min = 0, $max = 1, $float = 1)
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $float);
    }

    /**
     * 周报中其他同学的数据由后端在范围内抓取显示一个随机数
     * @param int $time
     * @return array
     */
    public static function weekReportRandData($time)
    {
        /**
         * 规则：
         * 1。普通时间段
         *      练琴天数：4.5-5.5
         *      练琴时长：35-45
         *      互动课堂：当前周平台可上课程的85%-95%之间（按照平台可报名的套课数，每套课每周可解锁课程数，求和得到）
         *      任务完成度：75-85
         *
         * 2。特殊时间段:十一假期（10.1-10.7）、寒假（除夕前2周-除夕后2周）、暑假（7.1-8.31）
         *      练琴天数：5.0-6.0
         *      练琴时长：45-60
         *      互动课堂：当前周平台可上课程的85%-95%之间（按照平台可报名的套课数，每套课每周可解锁课程数，求和得到）
         *      任务完成度：78-88
         */
        $randData = json_decode(DictConstants::get(DictConstants::WEEK_REPORT_CONFIG, 'rand_data'), true);
        //暑假
        $july = strtotime("July 1st");
        $august = strtotime("August 31st 23:59:59");
        //十一假期
        $tenOne = strtotime("October 1st");
        $tenSeven = strtotime("October 7th 23:59:59");
        //除夕
        $holidays = HolidaysModel::getRecord(['year' => date("Y", $time), 'type' => HolidaysModel::NEW_YEAR_EVE], ['start_time', 'end_time'], false);
        $cxBeforeTwoWeek = strtotime("-2 week", $holidays['start_time']);
        $cxAfterTwoWeek = strtotime("+2 week", $holidays['end_time']);
        if ((($time >= $july) && ($time <= $august)) ||
            (($time >= $tenOne) && ($time <= $tenSeven)) ||
            ($time >= $cxBeforeTwoWeek) && ($time <= $cxAfterTwoWeek)) {
            return $randData['va'];
        } else {
            return $randData['nor'];
        }
    }

    /**
     * 发送周报
     * @return bool
     */
    public static function sendWeekReport()
    {
        //获取上周开始结束时间
        list($startTime, $endTime, $year, $week) = Util::getDateWeekStartEndTime(strtotime("-1 day"));
        $reportData = StudentWeekReportModel::getPushMessageData($year, $week);
        $templateId = $_ENV["WECHAT_DAY_PLAY_REPORT"];
        try {
            $topic = new PushMessageTopic();
        } catch (\Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', []);
            return false;
        }
        foreach ($reportData as $value) {
            $info = json_decode($value['info'], true);
            $data = [
                'first' => [
                    'value' => "宝贝的练琴周报已生成，宝贝表现很棒哦！继续加油吧！",
                    'color' => "#323d83"
                ],
                'keyword1' => [
                    'value' => date("Y年m月d日", $value['start_time']) . '-' . date("Y年m月d日", $value['end_time']),
                    'color' => "#323d83"
                ],
                'keyword2' => [
                    'value' => "共练习曲目" . $info['lesson_count'] . "首",
                    'color' => "#323d83"
                ],
                'keyword3' => [
                    'value' => "共练琴" . ceil($info['sum_durations'] / 60) . "分钟",
                    'color' => "#323d83"
                ],
            ];
            $msgBody = [
                'wx_push_type' => 'template',
                'template_id' => $templateId,
                'data' => $data,
                'url' => $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/weekPaper?id=" . $value["id"],
                'open_id' => $value["open_id"],
            ];
            try {
                $topic->wxPushCommon($msgBody)->publish(mt_rand(0, 1800));
            } catch (\Exception $e) {
                SimpleLogger::error("sendWeekReport send failure", ['data' => $value]);
                continue;
            }
        }
        return true;
    }
}
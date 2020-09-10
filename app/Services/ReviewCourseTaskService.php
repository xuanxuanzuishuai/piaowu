<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:08 PM
 */

namespace App\Services;


use App\Libs\AIPLClass;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AIPlayRecordModel;
use App\Models\EmployeeModel;
use App\Models\PlayClassRecordModel;
use App\Models\PlayRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\ReviewCourseTaskModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;

class ReviewCourseTaskService
{
    /**
     * 生成指定日期的点评任务
     * 生成点评任务时不区分用户是否付费，在发送时过滤非付费用户
     * @param $reviewDate
     * @return bool|null
     */
    public static function createDailyTasks($reviewDate)
    {
        $existStudentIds = ReviewCourseTaskModel::existStudents($reviewDate);

        $reviewDateTS = strtotime($reviewDate);
        $playDateTS = $reviewDateTS - 86400;
        $playDate = date('Ymd', $playDateTS);

        $now = time();

        $playSum = AIPlayRecordModel::studentDailySum($playDateTS, $reviewDateTS - 1);

        $newTasks = [];
        $partCount = 0;
        $error = 0;
        foreach ($playSum as $i => $data) {
            // 当日已经生成过的学生不能重复生成
            if (in_array($data['student_id'], $existStudentIds)) {
                continue;
            }

            $newTasks[] = [
                'student_id' => $data['student_id'],
                'review_date' => $reviewDate,
                'play_date' => $playDate,
                'sum_duration' => $data['sum_duration'],
                'reviewer_id' => 0,
                'create_time' => $now,
                'status' => ReviewCourseTaskModel::STATUS_INIT,
                'update_time' => 0,
                'review_audio' => '',
                'review_audio_update_time' => 0,
            ];
            $partCount++;
            if ($partCount >= ReviewCourseTaskModel::EACH_LIMIT) {
                $success = self::insertTasks($newTasks);
                if (!$success) {
                    $error++;
                }

                $newTasks = [];
                $partCount = 0;
            }
        }
        if ($partCount > 0) {
            $success = self::insertTasks($newTasks);
            if (!$success) {
                $error++;
            }
        }

        return $error <= 0;
    }

    public static function insertTasks($newTasks)
    {
        $success = ReviewCourseTaskModel::batchInsert($newTasks);
        if (!$success) {
            SimpleLogger::error("[createDailyTasks] batchInsert error", [
                '$partCount' => count($newTasks),
                '$studentId' => array_column($newTasks, 'student_id'),
            ]);
            return false;
        }
        return true;
    }

    /**
     * 获取点评老师id
     * @return array
     */
    public static function getReviewers()
    {
        $reviewerSetting = DictConstants::get(DictConstants::REVIEW_COURSE_CONFIG, 'reviewer_ids');
        $reviewerIds = explode(',', $reviewerSetting);
        return $reviewerIds;
    }

    /**
     * task 列表
     * @param $params
     * @return array
     */
    public static function getTasks($params)
    {
        $where = [];

        if (!empty($params['reviewer_id'])) {
            $where['e.id'] = $params['reviewer_id'];
        }

        if (!empty($params['review_after'])) {
            $where['review_date[>=]'] = $params['review_after'];
        }
        if (!empty($params['review_before'])) {
            $where['review_date[<=]'] = $params['review_before'];
        }

        if (!empty($params['play_after'])) {
            $where['play_date[>=]'] = $params['play_after'];
        }
        if (!empty($params['play_before'])) {
            $where['play_date[<=]'] = $params['play_before'];
        }

        if (!empty($params['student_id'])) {
            $where['s.id'] = $params['student_id'];
        }

        if (!empty($params['student_mobile'])) {
            $where['s.mobile'] = $params['student_mobile'];
        }

        if (!empty($params['student_name'])) {
            $where['s.name[~]'] = Util::sqlLike($params['student_name']);
        }

        if (!empty($params['student_course_type'])) {
            $where['s.has_review_course'] = $params['student_course_type'];
        } else {
            $where['s.has_review_course'] = [ReviewCourseModel::REVIEW_COURSE_49, ReviewCourseModel::REVIEW_COURSE_1980];
        }

        if (isset($params['status'])) {
            $where['rct.status'] = $params['status'];
        }

        if (isset($params['play_date_order']) && !empty($params['play_date_order'])) {
            $where['ORDER'] = ["play_date" => $params['play_date_order']];
        }

        list($page, $count) = Util::formatPageCount($params);
        $where['LIMIT'] = [($page - 1) * $count, $count];

        $result = ReviewCourseTaskModel::getTasks($where);

        return $result;
    }

    /**
     * 获取点评课相关配置
     * @return array
     */
    public static function getConfig()
    {
        $courseTypes = [
            ReviewCourseModel::REVIEW_COURSE_49 => '49课包',
            ReviewCourseModel::REVIEW_COURSE_1980 => '1980课包'
        ];

        $statusDesc = [
            '0' => '未发送',
            '1' => '发送成功',
            '2' => '发送失败'
        ];

        $reviewerIds = self::getReviewers();
        $reviewers = EmployeeModel::getRecords(['id' => $reviewerIds], ['id', 'name'], false);
        $reviewers = array_column($reviewers, 'name', 'id');

        return [
            'student_course_types' => $courseTypes,
            'reviewers' => $reviewers,
            'status_desc' => $statusDesc
        ];
    }

    /**
     * 获取点评任务对应的演奏详情
     * @param $taskId
     * @return array
     * @throws RunTimeException
     */
    public static function getPlayDetailByReviewTask($taskId)
    {
        $review = ReviewCourseTaskModel::getById($taskId);
        if (empty($review)) {
            throw new RunTimeException(['record_not_found']);
        }

        $student = StudentModel::getById($review['student_id']);
        if (empty($student)) {
            throw new RunTimeException(['student_not_found']);
        }

        $reviewData = [
            'id' => $review['id'],
            'play_date' => $review['play_date'],
            'review_date' => $review['review_date'],
            'review_status' => $review['status'],
            'review_audio' => $review['review_audio'] ? AliOSS::signUrls($review['review_audio'], "", "", "", true) : '',
            'review_audio_update_time' => $review['review_audio_update_time'],
            'reviewer_id' => $review['reviewer_id'],
        ];

        $reviewData['review_text'] = self::getReviewText($review['student_id'], $review['play_date']);

        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($student['id'],
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);

        $studentData = [
            'id' => $student['id'],
            'name' => $student['name'],
            'mobile' => $student['mobile'],
            'wx_bind' => empty($studentWeChatInfo) ? 0 : 1,
        ];

        $startTime = strtotime($review['play_date']);
        $endTime = $startTime + 86399;

        $detail = [];
        $recordSum = PlayRecordModel::getStudentPlaySum($student['id'], $startTime, $endTime);
        $detail['lesson_count'] = $recordSum['lesson_count'];
        $detail['total_duration'] = $recordSum['sum_duration'];

        $classRecordSum = PlayClassRecordModel::getStudentPlaySumByLesson($student['id'], $startTime, $endTime);
        if (!empty($classRecordSum)) {
            $detail['class_lesson_count'] = count($classRecordSum);
            $detail['class_total_duration'] = array_sum(array_column($classRecordSum, 'sum_duration'));


            $lessonIds = array_column($classRecordSum, 'lesson_id');

            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 'test', 0, 1);
            $resp = $opn->lessonsByIds($lessonIds, 0, []);
            if ($resp['code'] == 0) {
                $lessonsInfo = $resp['data'];
                $lessonsInfo = array_combine(array_column($lessonsInfo, 'id'), $lessonsInfo);
            }

            if (empty($lessonsInfo)) { $lessonsInfo = []; }
            foreach ($classRecordSum as $i => $lessonSum) {
                $info = $lessonsInfo[$lessonSum['lesson_id']] ?? null;
                if (!empty($info)) {
                    $classRecordSum[$i]['lesson_name'] = $info['lesson_name'] ?? '-';
                    $classRecordSum[$i]['collection_name'] = $info['collection_name'] ?? '-';
                }
            }

            $detail['class_lesson'] = $classRecordSum;
        }

        // 后台打开练琴日报页面需要的token
        $shareToken = AIPlayReportService::getShareReportToken($student['id'], $review['play_date']);

        return [
            'student' => $studentData,
            'review' => $reviewData,
            'detail' => $detail,
            'share_token' => $shareToken,
        ];
    }

    /**
     * 上传点评语音
     * @param $taskId
     * @param $file
     * @return void
     * @throws RunTimeException
     */
    public static function uploadReviewAudio($taskId, $file)
    {
        if (empty($taskId) || empty($file)) {
            throw new RunTimeException(['update_failure']);
        }

        $ret = ReviewCourseTaskModel::updateRecord($taskId, [
            'review_audio' => $file,
            'review_audio_update_time' => time()
        ]);

        if ($ret < 1) {
            throw new RunTimeException(['update_failure']);
        }
    }

    /**
     * 获取点评话术
     * @param $studentId
     * @param $playDate
     * @return string
     */
    public static function getReviewText($studentId, $playDate)
    {
        $student = StudentModel::getById($studentId);
        $time = strtotime($playDate);
        if (empty($student) || empty($time)) {
            SimpleLogger::error("[getReviewText] invalid params", [
                '$studentId' => $studentId,
                '$student' => $student,
                '$playDate' => $playDate,
                '$time' => $time
            ]);
            return "获取点评话术失败";
        }

        $text = AIPLClass::getReviewText($student['uuid'], $time);

        if (empty($text)) {
            return "点评话术未生成";
        }

        return $text;
    }
}
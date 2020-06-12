<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:08 PM
 */

namespace App\Services;


use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\ReferralActivityModel;
use App\Models\UserQrTicketModel;
use App\Models\StudentModel;
use App\Models\CollectionModel;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\NewSMS;
use App\Libs\UserCenter;
use App\Models\MessageRecordModel;
use App\Models\PosterModel;
use App\Models\SharePosterModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatConfigModel;
use App\Services\Queue\QueueService;
use App\Libs\SimpleLogger;

class ReferralActivityService
{
    /**
     * 获取活动信息
     * @param $studentId
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getReferralActivityTipInfo($studentId)
    {
        //获取学生当前状态
        $studentInfo = StudentService::studentStatusCheck($studentId);
        $data = [];
        if (empty($studentInfo)) {
            return $data;
        }
        $data['student_status'] = $studentInfo['student_status'];
        if ($studentInfo['student_status'] == StudentModel::STATUS_BUY_TEST_COURSE) {
            //付费体验课
            if ($studentInfo['student_info']['collection_id']) {
                $wechatQr = CollectionModel::getById($studentInfo['student_info']['collection_id'])['wechat_qr'];
            } else {
                $wechatQr = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC], ['wechat_qr'], false)['wechat_qr'];
            }
            $data['oss_wechat_qr'] = AliOSS::signUrls($wechatQr);
        } elseif ($studentInfo['student_status'] == StudentModel::STATUS_BUY_NORMAL_COURSE) {
            //付费正式课
            $activityInfo = self::getStudentActivityPoster($studentId);
            $data['activity_info'] = $activityInfo;
            if (!empty($activityInfo)) {
                //获取当前活动学生最新上传记录
                $data['activity_info']['upload_record'] = [];
                $uploadRecord = SharePosterService::getLastUploadRecord($studentId, $data['activity_info']['id'], ['reason', 'remark', 'status', 'img_url']);
                if (!empty($uploadRecord)) {
                    $data['activity_info']['upload_record']["status"] = $uploadRecord["status"];
                    $data['activity_info']['upload_record']["status_name"] = $uploadRecord["status_name"];
                    $data['activity_info']['upload_record']["img_oss_url"] = $uploadRecord["img_oss_url"];
                    $data['activity_info']['upload_record']["reason_str"] = $uploadRecord["reason_str"];
                }
                unset($data['activity_info']['poster_url']);
            }
        }
        //返回数据
        return $data;
    }

    /**
     * 获取当前有效活动分享海报
     * @param $studentId
     * @return array|mixed
     */
    public static function getStudentActivityPoster($studentId)
    {
        //获取当前有效的活动:如果存在多个则按创建时间倒叙去第一个
        $time = time();
        $data = [];
        $activityWhere = [
            'status' => ReferralActivityModel::STATUS_ENABLE,
            'start_time[<=]' => $time,
            'end_time[>=]' => $time,
            'ORDER' => ['create_time' => 'DESC'],
        ];
        $activityInfo = ReferralActivityModel::getRecord($activityWhere, ['id', 'end_time', 'start_time', 'name', 'guide_word', 'share_word', 'poster_url'], false);
        if (empty($activityInfo)) {
            return $data;
        }
        //生成带二维码的分享海报
        $settings = ReferralActivityModel::$studentWXActivityPosterConfig;
        $posterImgFile = UserService::addQrWaterMarkAliOss(
            $studentId,
            $activityInfo['poster_url'],
            UserQrTicketModel::STUDENT_TYPE,
            $settings['poster_width'],
            $settings['poster_height'],
            $settings['qr_width'],
            $settings['qr_height'],
            $settings['qr_x'],
            $settings['qr_y']);
        if (empty($posterImgFile)) {
            return $data;
        }
        $activityInfo['poster_oss_url'] = $posterImgFile;
        $formatActivityInfo = self::formatData([$activityInfo]);
        return $formatActivityInfo[0];
    }

    /**
     * 格式化信息
     * @param $data
     * @return mixed
     */
    public static function formatData($data)
    {
        foreach ($data as $dk => &$dv) {
            $dv['start_time'] = date('Y-m-d', $dv['start_time']);
            $dv['end_time'] = date('Y-m-d', $dv['end_time']);
            $dv['guide_word'] = Util::textDecode($dv['guide_word']);
            $dv['share_word'] = Util::textDecode($dv['share_word']);
        }
        return $data;
    }

    /**
     * 检测活动是否有效
     * @param $activityId
     * @return array
     */
    public static function checkActivityIsEnable($activityId)
    {
        $time = time();
        $activityWhere = [
            'id' => $activityId,
            'status' => ReferralActivityModel::STATUS_ENABLE,
            'start_time[<=]' => $time,
            'end_time[>=]' => $time
        ];
        $activityInfo = ReferralActivityModel::getRecord($activityWhere, ['id', 'event_id', 'task_id'], false);
        return $activityInfo;

    }

    /**
     * 新增转介绍活动
     * @param $params
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function addActivity($params)
    {
        $params['start_time'] = strtotime($params['start_time']);
        $params['end_time'] = strtotime($params['end_time']);
        if ($params['start_time'] >= $params['end_time'] || $params['end_time'] <= time()) {
            throw new RunTimeException(['end_time_error']);
        }

        $erp = new Erp();
        $events = $erp->eventTaskList($params['event_id']);
        if (empty($events) || $events['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        $event = $events['data'][0];

        if ($params['start_time'] < $event['start_time'] || $params['end_time'] > $event['end_time']) {
            throw new RunTimeException(['time_not_in_erp_event_time']);
        }

        if (empty($event['tasks'])) {
            throw  new RunTimeException(['no_event_tasks']);
        }
        // 获取最后一个任务，看看该任务是否已经参加活动
        $taskIds = array_column($event['tasks'], 'id');
        $taskId = array_pop($taskIds);

        $activities = ReferralActivityModel::getRecord(['task_id' => $taskId]);
        // 该任务已经参加活动，要复制一个新的任务出来，和之前的task相同
        if (!empty($activities)) {
            // 查询该事件是否可以复制新的任务
            $settings = json_decode($event['settings'], 1);
            if (empty($settings['can_copy_task'])) {
                throw new RunTimeException(['event_task_can_not_copy']);
            }

            $task = $erp->copyTask($taskId, $params['start_time'], $params['end_time'], $params['name']);
            if (empty($task) || $task['code'] != Valid::CODE_SUCCESS) {
                $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
                throw new RunTimeException([$errorCode]);
            }
            $taskId = $task['data'];
        }

        $activityId = ReferralActivityModel::insert($params, $taskId);
        return $activityId;
    }

    /**
     * 修改转介绍活动
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function modify($params)
    {
        // 结束日期必须在开始日期、当前日期之后
        $params['start_time'] = strtotime($params['start_time']);
        $params['end_time'] = strtotime($params['end_time']);
        if ($params['start_time'] >= $params['end_time'] || $params['end_time'] <= time()) {
            throw new RunTimeException(['end_time_error']);
        }

        $activity = ReferralActivityModel::getRecord(['id' => $params['activity_id']]);
        if (empty($activity)) {
            throw new RunTimeException(['not_found_activity']);
        }

        if ($activity['status'] == ReferralActivityModel::STATUS_ENABLE) {
            // 此活动与当前已启用活动有时间冲突，不可启用
            $conflict = ReferralActivityModel::checkTimeConflict($params['start_time'], $params['end_time'], $activity['event_id'], $params['activity_id']);
            if ($conflict) {
                throw new RunTimeException(['activity_time_conflict']);
            }
        }

        $erp = new Erp();
        $events = $erp->eventTaskList($activity['event_id']);
        if (empty($events) || $events['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        $event = $events['data'][0];

        if ($params['start_time'] < $event['start_time'] || $params['end_time'] > $event['end_time']) {
            throw new RunTimeException(['time_not_in_erp_event_time']);
        }

        $task = $erp->modifyTask($activity['task_id'], $params['start_time'], $params['end_time'], $params['name']);
        if (empty($task) || $task['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $result = ReferralActivityModel::modify($params, $params['activity_id']);
        if (empty($result)) {
            return false;
        }
        return true;
    }

    /**
     * 启用、禁用活动
     * @param $activityId
     * @param $status
     * @return bool
     * @throws RunTimeException
     */
    public static function updateStatus($activityId, $status)
    {
        $activity = ReferralActivityModel::getRecord(['id' => $activityId]);
        if (empty($activity)) {
            throw new RunTimeException(['not_found_activity']);
        }

        $now = time();

        // 启用
        if ($status == ReferralActivityModel::STATUS_ENABLE) {
            if ($activity['end_time'] < $now) {
                throw new RunTimeException(['activity_already_over']);
            }

            // 此活动与当前已启用活动有时间冲突，不可启用
            $conflict = ReferralActivityModel::checkTimeConflict($activity['start_time'], $activity['end_time'], $activity['event_id']);
            if ($conflict) {
                throw new RunTimeException(['activity_time_conflict']);
            }
        }

        $erp = new Erp();
        $task = $erp->modifyTask($activity['task_id'], 0, 0, '', $status);
        if (empty($task) || $task['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }


        $result = ReferralActivityModel::updateRecord($activityId, ['status' => $status, 'update_time' => $now], false);
        if (empty($result)) {
            return false;
        }
        return true;
    }

    /**
     * 活动列表
     * @param $params
     * @return array
     */
    public static function activities($params)
    {
        list($activities, $totalCount) = ReferralActivityModel::list($params);

        if (!empty($activities)) {
            foreach ($activities as &$activity) {
                $activity['poster_url'] = AliOSS::signUrls($activity['poster_url']);
                $activity['start_time'] = date('Y-m-d', $activity['start_time']);
                $activity['end_time'] = date('Y-m-d', $activity['end_time']);
                $activity['act_time_status'] = $activity['activity_time_status'];
                $activity['activity_time_status'] = DictService::getKeyValue('activity_time_status', $activity['activity_time_status']);
                $activity['create_time'] = date('Y-m-d H:i', $activity['create_time']);
                $activity['activity_status'] = DictService::getKeyValue('activity_status', $activity['status']);
            }
        }

        return [$activities, $totalCount];
    }

    /**
     * 活动处于“进行中”且“已启用”状态
     * @param $activityId
     * @return bool
     */
    public static function checkActivityStatus($activityId)
    {
        $now = time();
        $activity = ReferralActivityModel::getRecord(['id' => $activityId]);
        if ($activity['status'] != ReferralActivityModel::STATUS_ENABLE
            || $activity['start_time'] > $now
            || $activity['end_time'] < $now) {
            return false;
        }
        return true;
    }

    /**
     * 当前阶段为付费正式课且未参加当前活动的学员
     * @param $activityId
     * @return array
     * @throws RunTimeException
     */
    public static function getPaidAndNotAttendStudents($activityId)
    {
        $attendStudentIds = SharePosterModel::getRecords(['activity_id' => $activityId], 'student_id');

        $where = [
            'has_review_course' => StudentModel::CRM_AI_LEADS_STATUS_BUY_NORMAL_COURSE,
            'status' => StudentModel::STATUS_NORMAL
        ];
        if (!empty($attendStudentIds)) {
            $where['id[!]'] = array_unique($attendStudentIds);
        }
        $students = StudentModel::getRecords($where, ['id', 'mobile']);
        if (empty($students)) {
            throw new RunTimeException(['no_students']);
        }

        return $students;
    }

    /**
     * 发送参与活动短信
     * @param $activityId
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function sendActivitySMS($activityId, $employeeId)
    {
        // 若活动处于“进行中”且“已启用”状态，则短信提醒的【发送】功能可用
        $check = self::checkActivityStatus($activityId);
        if (!$check) {
            throw new RunTimeException(['send_sms_activity_status_error']);
        }

        $activity = ReferralActivityModel::getRecord(['id' => $activityId]);
        $startTime = date('m月d日', $activity['start_time']);

        // 当前阶段为付费正式课且未参加当前活动的学员
        $students = self::getPaidAndNotAttendStudents($activityId);

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));

        $sign = CommonServiceForApp::SIGN_STUDENT_APP;
        $i = 0;
        $mobiles = [];
        $successNum = 0;

        foreach ($students as $student) {
            $i++;
            $mobiles[] = $student['mobile'];

            if ($i >= 1000) {
                $result = $sms->sendAttendActSMS(implode(',', $mobiles), $sign, $startTime);
                $i = 0;
                $mobiles = [];
                if ($result) {
                    $successNum += count($mobiles);
                }
            }
        }

        // 剩余数量小于1000
        if (!empty($mobiles)) {
            $result = $sms->sendAttendActSMS(implode(',', $mobiles), $sign, $startTime);
            if ($result) {
                $successNum += count($mobiles);
            }
        }


        // 发短信记录
        $failNum = count($students) - $successNum;
        MessageRecordService::add(MessageRecordModel::MSG_TYPE_SMS, $activityId, $successNum, $failNum, $employeeId, time());

        return true;
    }

    /**
     * 查询符合条件的微信用户，发送活动通知
     * @param $activityId
     * @param $employeeId
     * @param $guideWord
     * @param $shareWord
     * @param $posterUrl
     * @return bool
     * @throws RunTimeException
     */
    public static function sendWeixinMessage($activityId, $employeeId, $guideWord, $shareWord, $posterUrl)
    {
        if (!empty($guideWord) || !empty($shareWord) || !empty($posterUrl)) {

            // 若活动处于“进行中”且“已启用”状态，则客服消息提醒的【发送】功能可用
            $check = self::checkActivityStatus($activityId);
            if (!$check) {
                throw new RunTimeException(['send_weixin_activity_status_error']);
            }

            // 当前阶段为付费正式课且未参加当前活动的学员
            $students = self::getPaidAndNotAttendStudents($activityId);
            $studentIds = array_column($students, 'id');

            $boundUsers = UserWeixinModel::getBoundUserIds($studentIds, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);

            $i = 0;
            $users = [];
            $now = time();
            foreach ($boundUsers as $student) {
                $i++;
                $users[] = $student;

                if ($i >= 10) {
                    // 10个一组，放到nsq队列中一起处理
                    QueueService::pushWX($users, $guideWord, $shareWord, $posterUrl, $now, $activityId, $employeeId);

                    $i = 0;
                    $users = [];
                }
            }

            // 剩余数量小于10个
            if (!empty($users)) {
                QueueService::pushWX($users, $guideWord, $shareWord, $posterUrl, $now, $activityId, $employeeId);
            }

            return true;
        }

        throw new RunTimeException(['at_least_send_one_message']);
    }

    /**
     *
     * @param $msgBody
     */
    public static function pushWXMsg($msgBody)
    {
        $students = $msgBody['students'];
        $guideWord = $msgBody['guide_word'];
        $shareWord = $msgBody['share_word'];
        $posterUrl = $msgBody['poster_url'];
        $activityId = $msgBody['activity_id'];
        $employeeId = $msgBody['employee_id'];
        $pushTime = $msgBody['push_wx_time'];


        $successNum = 0;
        $failNum = 0;
        foreach ($students as $student) {
            $result = self::sendWeixinTextAndImage($student['user_id'], $student['open_id'], $guideWord, $shareWord, $posterUrl);
            if ($result) {
                $successNum += 1;
            } else {
                $failNum += 1;
            }
        }

        // 发微信的记录
        $record = MessageRecordService::getMsgRecord($activityId, $employeeId, $pushTime);
        if (empty($record)) {
            MessageRecordService::add(MessageRecordModel::MSG_TYPE_WEIXIN, $activityId, $successNum, $failNum, $employeeId, $pushTime);
        } else {
            MessageRecordService::updateMsgRecord($record['id'], [
                'success_num' => $record['success_num'] + $successNum,
                'fail_num' => $record['fail_num'] + $failNum,
                'update_time' => time()
            ]);
        }
    }

    /**
     * 微信发送活动通知
     * @param $userId
     * @param $openId
     * @param $guideWord
     * @param $shareWord
     * @param $posterUrl
     * @return bool
     */
    public static function sendWeixinTextAndImage($userId, $openId, $guideWord, $shareWord, $posterUrl)
    {


        $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $userType = WeChatService::USER_TYPE_STUDENT;

        // 自定义引导语
        $res1 = false;
        if (!empty($guideWord)) {
            $res1 = WeChatService::notifyUserWeixinTextInfo($appId, $userType, $openId, Util::textDecode($guideWord));
            $res1 = !empty($res1) && $res1['errcode'] == 0 ? true : false;
        }

        // 自定义分享语
        $res2 = false;
        if (!empty($shareWord)) {
            $res2 = WeChatService::notifyUserWeixinTextInfo($appId, $userType, $openId, Util::textDecode($shareWord));
            $res2 = !empty($res2) && $res2['errcode'] == 0 ? true : false;
        }

        // 海报
        $res3 = false;
        if (!empty($posterUrl)) {
            $settings = PosterModel::$settingConfig[PosterModel::APPLY_TYPE_STUDENT_WECHAT];

            //生成二维码海报
            $posterImgFile = UserService::generateQRPosterAliOss($userId, $posterUrl, UserQrTicketModel::STUDENT_TYPE,
                $settings['poster_width'], $settings['poster_height'], $settings['qr_width'], $settings['qr_height'], $settings['qr_x'], $settings['qr_y']);
            if (!empty($posterImgFile)) {
                //上传到微信服务器
                $data = WeChatService::uploadImg($posterImgFile, $appId, $userType);
                if (!empty($data['media_id'])) {
                    $res3 = WeChatService::toNotifyUserWeixinCustomerInfoForImage($appId, $userType, $openId, $data['media_id']);
                    $res3 = !empty($res3) && $res3['errcode'] == 0 ? true : false;
                }
            }
        }

        if ($res1 || $res2 || $res3) {
            return true;
        }
        return false;
    }

    /**
     * 活动详情
     * @param $activityId
     * @return array
     * @throws RunTimeException
     */
    public static function getActivityDetail($activityId)
    {
        $activity = ReferralActivityModel::getRecord(['id' => $activityId]);

        $erp = new Erp();
        $events = $erp->eventTaskList($activity['event_id']);
        if (empty($events) || $events['code'] != Valid::CODE_SUCCESS) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        $event = $events['data'][0];

        $activity['poster_link'] = AliOSS::signUrls($activity['poster_url']);
        $activity['start_time'] = date('Y-m-d', $activity['start_time']);
        $activity['end_time'] = date('Y-m-d', $activity['end_time']);
        $activity['create_time'] = date('Y-m-d H:i', $activity['create_time']);
        $activity['activity_status'] = DictService::getKeyValue('activity_status', $activity['status']);
        $activity['event_name'] = $event['name'];

        return $activity;
    }


    /**
     * 返现活动参加资格检测学生练琴时长
     * @param $studentId
     * @return mixed
     * @throws RunTimeException
     */
    public static function returnCashActivityPlayRecordCheck($studentId)
    {
        //检测学生练琴时长数据
        $studentInfo = StudentModel::getById($studentId);
        //查询学生所属班级参与的活动信息
        if (empty($studentInfo['collection_id'])) {
            throw new RunTimeException(['student_collection_is_empty']);
        }
        $time = time();
        $collectionInfo = CollectionService::getCollectionJoinEventInfo($studentInfo['collection_id']);
        if (empty($collectionInfo['info']) || empty($collectionInfo['task_condition']) || ($collectionInfo['info']['teaching_end_time'] > $time)) {
            throw new RunTimeException(['collection_id_error']);
        }
        $studentInfo['collection'] = $collectionInfo;
        //获取学生开班期内练琴数据，按天分组
        $playRecord = AIPlayRecordModel::getStudentSumByDate($studentId, $collectionInfo['info']['teaching_start_time'], $collectionInfo['info']['teaching_end_time']);
        if (empty($playRecord)) {
            throw new RunTimeException(['student_play_record_un_standard']);
        }
        //过滤符合事件活动条件的练琴数据
        $upToStandardCount = 0;
        array_map(function ($pv) use ($collectionInfo, &$upToStandardCount) {
            if ($pv['sum_duration'] >= $collectionInfo['task_condition']['per_day_min_play_time']) {
                $upToStandardCount++;
            }
        }, $playRecord);
        if ($upToStandardCount < $collectionInfo['task_condition']['total_qualified_day']) {
            throw new RunTimeException(['student_play_record_un_standard']);
        }
        return $studentInfo;
    }


    /**
     * 获取上传截图领返现活动信息
     * @param $studentId
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function returnCashActivityTipInfo($studentId)
    {
        //结果变量
        $data = [
            'activity_info' => [],
            'student_status' => '',
        ];
        //绑定状态
        $studentStatus = StudentService::studentStatusCheck($studentId);
        $data['student_status'] = $studentStatus['student_status'];
        //练琴时长
        try {
            $studentInfo = self::returnCashActivityPlayRecordCheck($studentId);
        } catch (RunTimeException $e) {
            SimpleLogger::error('return cash activity qualification check error', ['student_id' => $studentId, 'error' => $e->getWebErrorData()]);
            return $data;
        }
        //获取当前活动学生最新上传记录
        $data['activity_info']['upload_record'] = [];
        $uploadRecord = SharePosterService::getLastUploadRecord($studentId, $studentInfo['collection_id'], ['reason', 'remark', 'status', 'img_url']);
        if (!empty($uploadRecord)) {
            $data['activity_info']['upload_record']["status"] = $uploadRecord["status"];
            $data['activity_info']['upload_record']["status_name"] = $uploadRecord["status_name"];
            $data['activity_info']['upload_record']["img_oss_url"] = $uploadRecord["img_oss_url"];
            $data['activity_info']['upload_record']["reason_str"] = $uploadRecord["reason_str"];
        }
        //生成带二维码的分享海报
        $posterInfo = PosterModel::getRecord(
            [
                'status' => PosterModel::STATUS_PUBLISH,
                'poster_type' => PosterModel::POSTER_TYPE_WECHAT_STANDARD,
                'apply_type' => PosterModel::APPLY_TYPE_STUDENT_WECHAT
            ],
            ['content2', 'url'],
            false
        );
        if (empty($posterInfo)) {
            throw new RunTimeException(['wechat_poster_not_exists']);
        }
        $settings = PosterModel::$settingConfig[1];
        $posterImgFile = UserService::addQrWaterMarkAliOss(
            $studentId,
            $posterInfo['url'],
            UserQrTicketModel::STUDENT_TYPE,
            $settings['poster_width'],
            $settings['poster_height'],
            $settings['qr_width'],
            $settings['qr_height'],
            $settings['qr_x'],
            $settings['qr_y']);
        $data['activity_info']['poster_oss_url'] = $posterImgFile;
        $data['activity_info']['share_word'] = Util::textDecode($posterInfo['content2']);
        $data['activity_info']['valid_end_time'] = $studentInfo['collection']['info']['teaching_end_time'] + Util::TIMESTAMP_ONEDAY * $studentInfo['collection']['task_condition']['valid_time_range_day'];
        //返回数据
        return $data;
    }

    /**
     * 推送返现活动微信模板消息
     * @param $msgBody
     * @return bool
     */
    public static function pushWXCashActivityTemplateMsg($msgBody)
    {
        $batchInsertData = [];
        foreach ($msgBody as $user) {
            // 发送模版消息
            $result = WeChatService::notifyUserCustomizeMessage(
                $user['mobile'],
                WeChatConfigModel::RETURN_CASH_ACTIVITY_TEMPLATE_ID,
                [
                    'url' => DictConstants::get(DictConstants::COMMUNITY_CONFIG, 'COMMUNITY_UPLOAD_POSTER_URL')
                ]
            );
            $logData = [
                'mobile' => $user['mobile'],
                'activity_id' => $user['activity_id'],
                'activity_type' => $user['activity_type'],
            ];
            if ($result) {
                $user['success_num']++;
                SimpleLogger::info('push wx cash activity template msg success', $logData);
            } else {
                $user['fail_num']++;
                SimpleLogger::info('push wx cash activity template msg fail', $logData);
            }
            unset($user['mobile']);
            $batchInsertData[] = $user;
        }
        //消息发送记录
        return MessageRecordModel::batchInsert($batchInsertData, false);
    }
}
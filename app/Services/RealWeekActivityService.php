<?php


namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageRecordModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\TemplatePosterModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\QueueService;

class RealWeekActivityService
{

    const WEEK_ACTIVITY_TYPE = 1; //周周活动类型
    const MONTH_ACTIVITY_TYPE = 2; //月月活动类型

    /**
     * 添加周周领奖活动
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function add($data, $employeeId)
    {
        $checkAllowAdd = self::checkAllowAdd($data, self::WEEK_ACTIVITY_TYPE);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'app_id' => Constants::SMART_APP_ID,
            'create_time' => $time,
        ];
        $weekActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'event_id' => $data['event_id'] ?? 0,
            'guide_word' => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'create_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt' => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img' => $data['poster_make_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
        ];

        $activityExtData = [
            'activity_id' => 0,
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $activityId = OperationActivityModel::insertRecord($activityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert operation_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存周周领奖配置信息
        $weekActivityData['activity_id'] = $activityId;
        $weekActivityId = RealWeekActivityModel::insertRecord($weekActivityData);
        if (empty($weekActivityId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert week_activity fail", ['data' => $weekActivityData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存海报关联关系
        $posterArray = array_merge($data['personality_poster'] ?? [], $data['poster'] ?? []);
        $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $posterArray);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add week activity fail"]);
        }
        $db->commit();
        return $activityId;
    }

    /**
     * 检查是否允许添加 - 检查添加必要的参数
     * @param $data
     * @return bool
     */
    public static function checkAllowAdd($data, $type = self::MONTH_ACTIVITY_TYPE)
    {
        // 海报不能为空
        if (empty($data['poster']) && $type == self::MONTH_ACTIVITY_TYPE) {
            return 'poster_is_required';
        }

        // 开始时间不能大于等于结束时间
        $startTime = Util::getDayFirstSecondUnix($data['start_time']);
        $endTime = Util::getDayLastSecondUnix($data['end_time']);
        if ($startTime >= $endTime) {
            return 'start_time_eq_end_time';
        }

        // 检查奖励规则 - 不能为空， 去掉html标签以及emoji表情后不能大于1000个字符
        if (empty($data['award_rule'])) {
            return 'award_rule_is_required';
        }

        return '';
    }

    /**
     * 获取周周领奖活动列表和总数
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function searchList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        if (!empty($params['start_time_s'])) {
            $params['start_time_s'] = strtotime($params['start_time_s']);
        }
        if (!empty($params['start_time_e'])) {
            $params['start_time_e'] = strtotime($params['start_time_e']);
        }
        list($list, $total) = RealWeekActivityModel::searchList($params, $limitOffset);

        // 获取备注
        $activityIds = array_column($list, 'activity_id');
        if (!empty($activityIds)) {
            $activityExtList = ActivityExtModel::getRecords(['activity_id' => $activityIds]);
            $activityExtArr = array_column($activityExtList, null, 'activity_id');
        }

        $returnData = ['total_count' => $total, 'list' => []];
        foreach ($list as $item) {
            $extInfo = $activityExtArr[$item['activity_id']] ?? [];
            $returnData['list'][] = self::formatActivityInfo($item, $extInfo);
        }
        return $returnData;
    }

    /**
     * 格式化数据
     * @param $activityInfo
     * @param $extInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo, $extInfo)
    {
        // 处理海报
        if (!empty($activityInfo['poster']) && is_array($activityInfo['poster'])) {
            foreach ($activityInfo['poster'] as $k => $p) {
                $activityInfo['poster'][$k]['poster_url'] = AliOSS::replaceCdnDomainForDss($p['poster_path']);
                $activityInfo['poster'][$k]['example_url'] = !empty($p['example_path']) ? AliOSS::replaceCdnDomainForDss($p['example_path']) : '';
            }
        }

        $info = self::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['share_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['share_button_img']);
        $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['upload_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['upload_button_img']);
        $info['strategy_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['strategy_img']);
        $info['guide_word'] = Util::textDecode($activityInfo['guide_word']);
        $info['share_word'] = Util::textDecode($activityInfo['share_word']);
        $info['personality_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        $info['poster_prompt'] = Util::textDecode($activityInfo['poster_prompt']);
        $info['poster_make_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['poster_make_button_img']);
        $info['share_poster_prompt'] = Util::textDecode($activityInfo['share_poster_prompt']);
        $info['retention_copy'] = Util::textDecode($activityInfo['retention_copy']);

        if (empty($info['remark'])) {
            $info['remark'] = $extInfo['remark'] ?? '';
        }
        if (!empty($info['award_rule'])) {
            $awardRule = $info['award_rule'];
        } else {
            $awardRule = $extInfo['award_rule'] ?? '';
        }
        $info['award_rule'] = Util::textDecode($awardRule);
        return $info;
    }

    /**
     * 获取活动开始文字
     * @param $activityInfo
     * @param $time
     * @return array
     */
    public static function formatActivityTimeStatus($activityInfo, $time = 0)
    {
        if (empty($time)) {
            $time = time();
        }
        if ($activityInfo['start_time'] <= $time && $activityInfo['end_time'] >= $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_ONGOING;
        } elseif ($activityInfo['start_time'] > $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_PENDING;
        } elseif ($activityInfo['end_time'] < $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_FINISHED;
        }
        $activityInfo['activity_status_zh'] = DictService::getKeyValue('activity_time_status', $activityInfo['activity_time_status']);
        return $activityInfo;
    }

    /**
     * 获取海报详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = RealWeekActivityModel::getDetailByActivityId($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 获取活动海报
        $activityInfo['poster'] = PosterService::getActivityPosterList($activityInfo);
        $activityInfo = self::formatActivityInfo($activityInfo, []);
        $poster = [];
        $personality_poster = [];
        foreach ($activityInfo['poster'] as $val) {
            if ($val['type'] == TemplatePosterModel::STANDARD_POSTER) {
                $poster[] = $val;
            } elseif ($val['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $personality_poster[] = $val;
            }
        }
        $activityInfo['poster'] = $poster;
        $activityInfo['personality_poster'] = $personality_poster;
        return $activityInfo;
    }

    /**
     * 修改周周领奖活动
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function edit($data, $employeeId)
    {
        $checkAllowAdd = self::checkAllowAdd($data, self::WEEK_ACTIVITY_TYPE);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        // 检查是否存在
        if (empty($data['activity_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityId = intval($data['activity_id']);
        $weekActivityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($weekActivityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }

        // 判断海报是否有变化，没有变化不操作
        $posterArray = array_merge($data['personality_poster'] ?? [], $data['poster'] ?? []);
        $isDelPoster = ActivityPosterModel::diffPosterChange($activityId, $posterArray);
        // 开始处理更新数据
        $time = time();
        // 检查是否有海报
        $activityData = [
            'name' => $data['name'] ?? '',
            'update_time' => $time,
        ];
        $weekActivityData = [
            'activity_id' => $activityId,
            'name' => $activityData['name'],
            'guide_word' => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'update_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt' => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img' => $data['poster_make_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
        ];

        $activityExtData = [
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        $res = OperationActivityModel::batchUpdateRecord($activityData, ['id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖配置信息
        $res = RealWeekActivityModel::batchUpdateRecord($weekActivityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update week_activity fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update activity_ext fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 当海报有变化时删除原有的海报
        if ($isDelPoster) {
            // 删除海报关联关系
            $res = ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add is del activity_poster fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
            // 写入新的活动与海报的关系
            $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $posterArray);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["add week activity fail"]);
            }
        }

        $db->commit();

        // 删除缓存
        ActivityService::delActivityCache(
            $activityId,
            [
                ActivityPosterModel::KEY_ACTIVITY_POSTER,
                ActivityExtModel::KEY_ACTIVITY_EXT,
                OperationActivityModel::KEY_CURRENT_ACTIVE,
            ],
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type' => TemplatePosterModel::STANDARD_POSTER,   // 周周领奖 - 标准海报
            ]
        );
        return true;
    }

    /**
     * 更改周周有奖的状态
     * @param $activityId
     * @param $enableStatus
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_OFF, OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $startActivity = RealWeekActivityModel::checkTimeConflict($activityInfo['start_time'], $activityInfo['end_time'], $activityInfo['event_id']);
            if (!empty($startActivity)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($startActivity, 'activity_id')]);
            }
        }

        // 修改启用状态
        $res = RealWeekActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }

        // 删除缓存
        ActivityService::delActivityCache(
            $activityId,
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE,
            ],
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type' => TemplatePosterModel::STANDARD_POSTER,   // 周周领奖 - 标准海报
            ]
        );

        return true;
    }

    /**
     * 活动处于“进行中”且“已启用”状态
     * @param $activityInfo
     * @return bool
     */
    public static function checkActivityStatus($activityInfo)
    {
        $now = time();
        if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON
            || $activityInfo['start_time'] > $now
            || $activityInfo['end_time'] < $now) {
            return false;
        }
        return true;
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
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 若活动处于“进行中”且“已启用”状态，则短信提醒的【发送】功能可用
        $check = self::checkActivityStatus($activityInfo);
        if (!$check) {
            throw new RunTimeException(['send_sms_activity_status_error']);
        }
        $startTime = date('m月d日', $activityInfo['start_time']);
        // 当前阶段为付费正式课且未参加当前活动的学员
        $students = self::getPaidAndNotAttendStudents($activityId);
        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $sign = CommonServiceForApp::SIGN_STUDENT_APP;
        $i = 0;
        $mobiles = [];
        $successNum = 0;

        foreach ($students as $student) {
            $i ++;
            if ($student['country_code'] == NewSMS::DEFAULT_COUNTRY_CODE) {
                $mobiles[] = $student['mobile'];
            }

            if ($i >= 1000) {
                $result = $sms->sendAttendActSMS($mobiles, $sign, $startTime);
                if ($result) {
                    $successNum += count($mobiles);
                }
                $i = 0;
                $mobiles = [];
            }
        }

        // 剩余数量小于1000
        if (!empty($mobiles)) {
            $result = $sms->sendAttendActSMS($mobiles, $sign, $startTime);
            if ($result) {
                $successNum += count($mobiles);
            }
        }

        // 发短信记录
        $failNum = count($students) - $successNum;
        MessageRecordService::add(MessageRecordModel::MSG_TYPE_SMS, $activityId, $successNum, $failNum, $employeeId, time(), MessageRecordModel::ACTIVITY_TYPE_AWARD);

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
        if (empty($guideWord) && empty($shareWord) && empty($posterUrl)) {
            throw new RunTimeException(['no_send_message']);
        }
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 若活动处于“进行中”且“已启用”状态，则客服消息提醒的【发送】功能可用
        $check = self::checkActivityStatus($activityInfo);
        if (!$check) {
            throw new RunTimeException(['send_weixin_activity_status_error']);
        }

        // 当前阶段为付费正式课且未参加当前活动的学员
        $boundUsers = self::getPaidAndNotAttendStudents($activityId);
        if (empty($boundUsers)) {
            throw new RunTimeException(['no_students']);
        }

        // 写入发送信息的log ,然后把logId放入到队列
        $manualData = [
            'push_type' => MessagePushRulesModel::PUSH_TYPE_CUSTOMER,
            'content_1' => $guideWord,
            'content_2' => $shareWord,
            'image' => $posterUrl,
        ];
        $logId = MessageService::saveSendLog($manualData);
        $uuidArr = [];
        $userTotal = count($boundUsers);
        $lastNum = $userTotal - 1;
        for ($i = 0; $i < $userTotal; $i++) {
            $uuidArr[] = $boundUsers[$i]['uuid'];
            if (count($uuidArr) >= 1000 || $i == $lastNum) {
                QueueService::manualBatchPushRuleWxByUuid(
                    $logId,
                    $uuidArr,
                    $employeeId
                );
                $uuidArr = [];
            }
        }
        return true;
    }


    /**
     * 当前阶段为付费正式课且未参加当前活动的学员手机号和uuid
     * @param $activityId
     * @return array
     */
    public static function getPaidAndNotAttendStudents($activityId)
    {
        // 获取所有参数活动的学生id
        $inActivityStudent = RealSharePosterModel::getRecords(['activity_id' => $activityId], ['student_id']);
        $inActivityStudentId = [];
        if (!empty($inActivityStudent)) {
            $inActivityStudentId = array_column($inActivityStudent, 'student_id', 'student_id');
        }
        $noJoinActivityStudent = [];
        // 获取所有年卡用户 - 每次处理10w条
        $lastId = 0;
        while (true) {
            $tmpWhere = ['has_review_course' => DssStudentModel::REVIEW_COURSE_1980, 'status' => DssStudentModel::STATUS_NORMAL, 'ORDER' => ['id' => 'ASC'], 'LIMIT' => 50000, 'id[>]' => $lastId];
            $studentList = DssStudentModel::getRecords($tmpWhere, ['id', 'mobile', 'country_code', 'uuid']);
            // 没有数据跳出
            if (empty($studentList)) {
                break;
            }
            foreach ($studentList as $_info) {
                // 记录处理的最后一个id
                $lastId = $_info['id'];
                // 这个学生已经参加过活动
                if (isset($inActivityStudentId[$_info['id']])) {
                    continue;
                }
                $noJoinActivityStudent[] = $_info;
            }
            // 删除变量
            unset($studentList);
        }
        return $noJoinActivityStudent;
    }

    /**
     * 截图-活动列表
     * @param $params
     * @return array
     */
    public static function getSelectList($params)
    {
        list($page, $limit) = Util::formatPageCount($params);
        $limitOffset = [($page - 1) * $limit, $limit];
        list($list, $total) = RealWeekActivityModel::searchList($params, $limitOffset);
        if (empty($total)) {
            return [$list, $total];
        }
        foreach ($list as &$item) {
            $item['id'] = $item['activity_id'];
            $item = self::formatActivityTimeStatus($item);
        }
        return [$list, $total];
    }
}

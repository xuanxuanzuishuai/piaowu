<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-09-02 10:56:15
 * Time: 7:07 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\MessagePushRulesModel;
use App\Models\OperationActivityModel;
use App\Models\QrInfoOpCHModel;
use App\Models\RealSharePosterAwardModel;
use App\Models\RealSharePosterModel;
use App\Models\RealStudentCanJoinActivityModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\RealWeekActivityModel;
use App\Models\WeChatConfigModel;
use App\Services\Queue\QueueService;
use App\Services\SyncTableData\RealUpdateStudentCanJoinActivityService;

class RealSharePosterService
{
    const KEY_POSTER_VERIFY_LOCK = 'REAL_POSTER_VERIFY_LOCK';

    /**
     * 审核失败原因解析
     * @param $reason
     * @param $dict
     * @return string
     */
    public static function reasonToStr($reason, $dict = [])
    {
        if (is_string($reason)) {
            $reason = explode(',', $reason);
        }
        if (empty($reason)) {
            return '';
        }
        if (empty($dict)) {
            $dict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
        }
        $str = [];
        foreach ($reason as $item) {
            $str[] = $dict[$item] ?? $item;
        }
        return implode('/', $str);
    }

    /**
     * 上传截图列表
     * @param $params
     * @return array
     */
    public static function sharePosterList($params)
    {
        list($posters, $totalCount) = RealSharePosterModel::getPosterList($params);
        if (!empty($posters)) {
            $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
            $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
            foreach ($posters as &$poster) {
                $poster['mobile'] = Util::hideUserMobile($poster['mobile']);
                $poster['img_url'] = AliOSS::replaceCdnDomainForDss($poster['img_url']);
                $poster['status_name'] = $statusDict[$poster['poster_status']] ?? $posters['poster_status'];
                $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
                $poster['check_time'] = !empty($poster['check_time']) ? date('Y-m-d H:i', $poster['check_time']) : '';
                $reason_str = self::reasonToStr(explode(',', $poster['reason']), $reasonDict);
                if (!empty($poster['remark'])) {
                    !empty($reason_str) ? $reason_str .= '/'.$poster['remark'] : $reason_str .= $poster['remark'];
                }
                $poster['reason_str'] = $reason_str;
                if ($poster['operator_id'] == EmployeeModel::SYSTEM_EMPLOYEE_ID) {
                    $poster['operator_name'] = EmployeeModel::SYSTEM_EMPLOYEE_NAME;
                }
            }
        }
        return [$posters, $totalCount];
    }

    /**
     * 上传截图审核历史记录
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function sharePosterHistory($params, $page, $count)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        // 获取参与的活动列表
        $joinActivityIds = RealSharePosterModel::getStudentJoinActivityList($params['student_id']);
        if (empty($joinActivityIds)) {
            return $returnData;
        }
        $returnData['total_count'] = count($joinActivityIds);
        $activityIdOffset = array_slice($joinActivityIds, ($page - 1) * $count, $count);
        if (empty($activityIdOffset)) {
            return $returnData;
        }
        // 获取活动基础数据
        $activityBaseInfo = array_column(RealWeekActivityModel::getActivityAndTaskData($activityIdOffset), null, 'activity_id');
        // 获取用户参与指定活动的上传截图记录
        $joinRecord = RealSharePosterModel::getSharePosterHistoryGroupActivityIdAndTaskNum($params['student_id'], $activityIdOffset);
        $joinRecordFormat = $joinVerifyData = [];
        foreach ($joinRecord as $jk => $jv) {
            $joinRecordFormat[$jv['activity_id'] . '_' . (empty($jv['task_num']) ? 1 : $jv['task_num'])] = $jv;
            $joinVerifyData[$jv['activity_id']][$jv['verify_status']] += 1;
        }
        $dictData = RealDictConstants::getTypesMap([RealDictConstants::REAL_ACTIVITY_CONFIG['type'],DictConstants::ACTIVITY_ENABLE_STATUS['type']]);
        foreach ($activityBaseInfo as $ak => $av) {
            $taskNumCount = empty($av['task_num_count']) ? '1' : $av['task_num_count'];
            $tmpFormatData = [
                'activity_id' => $av['activity_id'],
                'task_num_count' => $taskNumCount,
                'award_prize_type' => $av['award_prize_type'],
                'delay_second' => empty($av['delay_second']) ? 0 : ($dictData[RealDictConstants::REAL_ACTIVITY_CONFIG['type']]['send_award_base_delay_second']['value'] + $av['delay_second']) / Util::TIMESTAMP_ONEDAY,
                'activity_name' => RealWeekActivityService::formatWeekActivityName($av),
                'activity_status_zh' => ($av['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE)
                    ? $dictData[DictConstants::ACTIVITY_ENABLE_STATUS['type']][OperationActivityModel::ENABLE_STATUS_DISABLE]['value'] : RealWeekActivityService::formatActivityTimeStatus($av)['activity_status_zh'],
                'success' => (int)$joinVerifyData[$av['activity_id']][RealSharePosterModel::VERIFY_STATUS_QUALIFIED],
                'fail' => (int)$joinVerifyData[$av['activity_id']][RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED],
                'wait' => (int)$joinVerifyData[$av['activity_id']][RealSharePosterModel::VERIFY_STATUS_WAIT],
                'task_list' => []
            ];
            //单次分享任务
            if (empty($av['task_data'])) {
                $tmpJoinRecordFormatKey = $av['activity_id'] . '_' . $taskNumCount;
                $tmpFormatAwardData = self::formatSharePosterAwardStatus($av['activity_id'], $joinRecordFormat[$tmpJoinRecordFormatKey]);
                $tmpFormatData['task_list'][] = [
                    'task_num' => $taskNumCount,
                    'award_type' => empty($joinRecordFormat[$tmpJoinRecordFormatKey]['award_type']) ? '' : $joinRecordFormat[$tmpJoinRecordFormatKey]['award_type'],
                    'verify_status' => (int)$joinRecordFormat[$tmpJoinRecordFormatKey]['verify_status'],
                    'award_amount' => $tmpFormatAwardData['award_amount'],
                    'award_status' => $tmpFormatAwardData['award_status'],
                    'award_status_zh' => $tmpFormatAwardData['award_status_zh'],
                ];
            } else {
                //多次分享任务
                list($tmpTask, $tmpTaskNumCount) = self::filterSpecialActivityTaskData(explode(',', $av['task_data']), $av['activity_id'], $taskNumCount);
                $tmpFormatData['task_list'] = array_map(function ($tmv) use ($joinRecordFormat, $av) {
                    list($tmpTaskNode['task_num'],
                        $tmpTaskNode['award_amount'],
                        $tmpTaskNode['award_type'],) = explode('-', $tmv);
                    $tmpTaskNode['verify_status'] = (int)$joinRecordFormat[$av['activity_id'] . '_' . $tmpTaskNode['task_num']]['verify_status'];
                    $tmpTaskNode['award_status'] = $joinRecordFormat[$av['activity_id'] . '_' . $tmpTaskNode['task_num']]['award_status'];
                    $tmpFormatAwardData = self::formatSharePosterAwardStatus($av['activity_id'], $tmpTaskNode);
                    $tmpTaskNode['award_amount'] = $tmpFormatAwardData['award_amount'];
                    $tmpTaskNode['award_status'] = $tmpFormatAwardData['award_status'];
                    $tmpTaskNode['award_status_zh'] = $tmpFormatAwardData['award_status_zh'];
                    return $tmpTaskNode;
                }, $tmpTask);
                $tmpFormatData ['task_num_count'] = $tmpTaskNumCount;
            }
            $returnData['list'][] = $tmpFormatData;
        }
        return $returnData;
    }

    /**
     * 过滤活动的任务列表数据
     * @param $taskListData
     * @param $activityId
     * @param $taskNumCount
     * @return mixed
     */
    private static function filterSpecialActivityTaskData($taskListData, $activityId, $taskNumCount)
    {
        //活动设置为1.多次分享任务 2.奖励延时发放,但是手动提前发放奖励的活动ID,分享任务使用第一个任务
        $specialDictActivityIds = explode(',', RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, '2000_send_award_activity_id'));
        if (in_array($activityId, $specialDictActivityIds)) {
            return [[$taskListData[0]], 1];
        }
        return [$taskListData, $taskNumCount];
    }


    /**
     * 格式化处理截图上传奖励发放状态
     * @param $activityId
     * @param $joinRecord
     * @return array
     */
    private static function formatSharePosterAwardStatus($activityId, $joinRecord)
    {
        $data = [
            'award_amount' => empty($joinRecord['award_amount']) ? '--' : $joinRecord['award_amount'],
            'award_status' => RealUserAwardMagicStoneModel::STATUS_NOT_OWN,
            'award_status_zh' => RealUserAwardMagicStoneModel::STATUS_ZH[RealUserAwardMagicStoneModel::STATUS_NOT_OWN],
        ];
        //临时指定的多次分享任务的活动
        $activityDictData = RealDictConstants::getTypesMap([RealDictConstants::REAL_XYZOP_1321_CONFIG['type'], RealDictConstants::REAL_ACTIVITY_CONFIG['type']]);
        $specialDictActivityIds1321 = explode(',', $activityDictData[RealDictConstants::REAL_XYZOP_1321_CONFIG['type']]['real_xyzop_1321_activity_ids']['value']);
        $specialDictActivityIds2000 = explode(',', $activityDictData[RealDictConstants::REAL_ACTIVITY_CONFIG['type']]['2000_send_award_activity_id']['value']);
        if (in_array($activityId, $specialDictActivityIds1321) && ($joinRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            $data['award_amount'] = "--";
            $data['award_status'] = RealUserAwardMagicStoneModel::STATUS_GIVE;
            $data['award_status_zh'] = "人工发放";
        } elseif (in_array($activityId, $specialDictActivityIds2000)) {
            $data['award_amount'] = "--";
            $data['award_status'] = RealUserAwardMagicStoneModel::STATUS_GIVE;
            $data['award_status_zh'] = "人工发放";
        } elseif (!is_null($joinRecord['award_status'])) {
            $data['award_status'] = $joinRecord['award_status'];
            $data['award_status_zh'] = RealUserAwardMagicStoneModel::STATUS_ZH[$data['award_status']];
        } elseif (is_null($joinRecord['award_status']) && ($joinRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            $data['award_status'] = RealUserAwardMagicStoneModel::STATUS_WAITING;
            $data['award_status_zh'] = RealUserAwardMagicStoneModel::STATUS_ZH[RealUserAwardMagicStoneModel::STATUS_WAITING];
        }
        return $data;
    }

    /**
     * 审核通过、发放奖励
     * @param $id
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function approvalPoster($id, $params = [])
    {
        $type = RealSharePosterModel::TYPE_WEEK_UPLOAD;
        $posters = RealSharePosterModel::getPostersByIds($id, $type);
        if (count($posters) != count($id)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $now = time();
        $updateData = [
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => $now,
            'verify_user'   => $params['employee_id'] ?? 0,
            'remark'        => $params['remark'] ?? '',
            'update_time'   => $now,
        ];
        //获取活动信息
        $activityData = array_column(RealWeekActivityModel::getRecords(['activity_id'=> array_column($posters,'activity_id')],['activity_id','award_prize_type']),null,'activity_id');
        $sendAwardQueueData = $sendWxMessageQueueData = [];

        //特殊活动ID：延时任务奖励使用第一个分享任务配置的奖励并且奖励已经发放的活动ID
        $specialDictActivityIds = explode(',', RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, '2000_send_award_activity_id'));
        //处理数据
        $redis = RedisDB::getConn();
        foreach ($posters as $key => $poster) {
            // 审核数据操作锁，解决并发导致的重复审核和发奖
            $lockKey = self::KEY_POSTER_VERIFY_LOCK . $poster['id'];
            $lock = $redis->set($lockKey, $poster['id'], 'EX', 120, 'NX');
            if (empty($lock)) {
                continue;
            }
            $where = [
                'id' => $poster['id'],
                'verify_status' => $poster['poster_status']
            ];
            $update = RealSharePosterModel::batchUpdateRecord($updateData, $where);
            // 更新失败，不处理
            if (empty($update)) {
                SimpleLogger::info("approvalPoster_update_status_faile", [$updateData, $where]);
                continue;
            }
            //真人产品激活
            QueueService::autoActivate(['student_uuid' => $poster['uuid'], 'passed_time' => time(),'app_id' => Constants::REAL_APP_ID]);
            //查询当前活动已完成次数
            $checkSuccessNumbers = self::getSharePosterVerifySuccessCountData($poster['student_id'], $poster['activity_id']);
            //区分奖励发放方式
            if (($activityData[$poster['activity_id']]['award_prize_type'] == OperationActivityModel::AWARD_PRIZE_TYPE_IN_TIME)
                || in_array($poster['activity_id'], $specialDictActivityIds)) {
                $sendAwardQueueData[] = [
                    'app_id' => Constants::REAL_APP_ID,
                    'student_id' => $poster['student_id'],
                    'activity_id' => $poster['activity_id'],
                    'act_status' => RealUserAwardMagicStoneModel::STATUS_GIVE,
                    'defer_second' => $checkSuccessNumbers * 10,
                    "check_success_numbers" => $checkSuccessNumbers,
                ];
            }
            // 发送消息
            $sendWxMessageQueueData[] = [
                "share_poster_id" => $poster['id'],
                "check_success_numbers" => $checkSuccessNumbers,
                'verify_status'=>RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
                ];

            // 修改参与进度
            $lastUploadRecord = RealSharePosterModel::getRecord([
                'student_id' => $poster['student_id'],
                'activity_id' => $poster['activity_id'],
                'last_upload_time[>]' => $poster['last_upload_time'],
            ]);
            RealUpdateStudentCanJoinActivityService::updateLastVerifyStatus($poster['uuid'], $poster['activity_id'], RealSharePosterModel::VERIFY_STATUS_QUALIFIED, $lastUploadRecord);
        }
        //批量投递消费消费队列
        QueueService::addRealUserPosterAward($sendAwardQueueData);
        QueueService::realSendPosterAwardMessage($sendWxMessageQueueData);
        return true;
    }

    /**
     * 截图审核-发奖-消费者
     * @param $data
     * @return bool
     */
    public static function addUserAward($data)
    {
        if (empty($data)) {
            return false;
        }
        //发放奖励接口
        try {
            RealUserAwardMagicStoneService::sendUserMagicStoneAward($data);
        } catch (RunTimeException $e) {
            SimpleLogger::info('RealSharePosterService_addUserAward', ['data' => $data, 'err_msg' => $e->getMessage()]);
            Util::errorCapture("RealSharePosterService_addUserAward_error", [$data, $e->getMessage()]);

        }
        return true;
    }

    /**
     * 审核不通过
     * @param $posterId
     * @param array $params
     * @param int $status
     * @return bool
     * @throws RunTimeException
     */
    public static function refusedPoster($posterId, $params = [], $status = RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED)
    {
        $reason = $params['reason'] ?? '';
        $remark = $params['remark'] ?? '';
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }

        $type = RealSharePosterModel::TYPE_WEEK_UPLOAD;
        $poster = RealSharePosterModel::getPostersByIds([$posterId], $type);
        $poster = $poster[0] ?? [];
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $time   = time();
        $update = RealSharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'verify_time' => $time,
            'verify_user' => $params['employee_id'],
            'verify_reason' => implode(',', $reason),
            'update_time' => $time,
            'remark' => $remark,
        ]);
        // 审核不通过, 发送模版消息
        if ($update > 0 && ($status != RealSharePosterModel::VERIFY_STATUS_WAIT)) {
            QueueService::realSendPosterAwardMessage([
                [
                    "share_poster_id" => $posterId,
                    "check_success_numbers" => 0,
                    'verify_status' => $status
                ]
            ]);
        }
        // 更新用户活动最后一次参与状态
        $lastUploadRecord = RealSharePosterModel::getRecord([
            'student_id' => $poster['student_id'],
            'activity_id' => $poster['activity_id'],
            'last_upload_time[>]' => $poster['last_upload_time'],
        ]);
        RealUpdateStudentCanJoinActivityService::updateLastVerifyStatus($poster['uuid'], $poster['activity_id'], $status, $lastUploadRecord);
        return $update > 0;
    }

    /**
     * 真人 - 截图审核详情
     * @param $id
     * @return array
     */
    public static function realSharePosterDetail($id)
    {
        $returnData = [];
        if (empty($id)) {
            return $returnData;
        }
        $sharePosterInfo = RealSharePosterModel::getRecord(['id' => $id]);
        if (empty($sharePosterInfo)) {
            return $returnData;
        }

        $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
        $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);

        $returnData['can_upload'] = Constants::STATUS_FALSE;
        $returnData['activity_id'] = $sharePosterInfo['activity_id'];
        $returnData['verify_status'] = $sharePosterInfo['verify_status'];
        $returnData['status_name'] = $statusDict[$sharePosterInfo['verify_status']] ?? $sharePosterInfo['verify_status'];
        $returnData['image_url'] = AliOSS::replaceCdnDomainForDss($sharePosterInfo['image_path']);
        $returnData['award_amount'] = 0;
        $returnData['award_type'] = 0;
        $returnData['reason_str'] = '';
        $returnData['task_num'] = $sharePosterInfo['task_num'];
        $time = time();

        // 根据状态判断展示逻辑
        switch ($sharePosterInfo['verify_status']) {
            case RealSharePosterModel::VERIFY_STATUS_QUALIFIED: // 审核通过
                // 获取奖励:活动ID小于245的活动展示奖品数量，之后的活动不在展示，此处代码兼容活动数据
                $awardInfo = RealSharePosterAwardModel::getRecord(['share_poster_id' => $id]);
                if (!empty($awardInfo)) {
                    $returnData['award_amount'] = $awardInfo['award_amount'];
                    $returnData['award_type']   = $awardInfo['award_type'];
                    // 获取发放状态
                    $sendAwardInfo = RealUserAwardMagicStoneModel::getRecord(['id' => $awardInfo['award_id']], ['award_status']);
                    $returnData['award_status'] = $sendAwardInfo['award_status'] ?? RealUserAwardMagicStoneModel::STATUS_WAITING;
                }
                break;
            case RealSharePosterModel::VERIFY_STATUS_WAIT || RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED:
                $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $sharePosterInfo['activity_id']]);
                // 检查活动能否重新上传
                if (self::checkWeekActivityAllowUpload($activityInfo, $time)) {
                    $returnData['can_upload'] = Constants::STATUS_TRUE;
                }
                // no break; 审核通过和不通过的公用部分
            case RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED: // 未通过
                $returnData['reason_str'] = self::reasonToStr(explode(',', $sharePosterInfo['verify_reason']), $reasonDict);
                if (!empty($sharePosterInfo['remark'])) {
                    !empty($returnData['reason_str']) ? $returnData['reason_str'] .= '/' . $sharePosterInfo['remark'] : $returnData['reason_str'] .=  $sharePosterInfo['remark'];
                }
                break;
            default:
                return $returnData;
        }

        return RealActivityService::xyzopFormatOne($returnData);
    }

    /**
     * 真人 - 检查周周领奖活动是否可以上传 - 只校验了活动状态，没有校验用户是否有资格参与
     * @param $activityInfo
     * @param $time
     * @return bool
     */
    public static function checkWeekActivityAllowUpload($activityInfo, $time): bool
    {
        if (empty($activityInfo)) {
            return false;
        }
        $time = $time ?? time();
        $activityOverAllowUploadSecond = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'activity_over_allow_upload_second');
        // 能否重新上传 - 不能：活动已结束 或 活动已结束但结束时间超过5天
        if (($time - $activityInfo['end_time']) > $activityOverAllowUploadSecond) {
            return false;
        }

        // 检查活动状态
        if (!RealActivityService::xyzopCheckIsSpecialActivityId(['activity_id' => $activityInfo['activity_id']])) {
            if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
                return false;
            }
        }
        return true;
    }

    /**
     * 真人 - 获取小程序码
     * @param $request
     * @return mixed
     * @throws RunTimeException
     */
    public static function getQrPath($request)
    {
        $studentId  = $request['student_id'] ?? 0;
        $channelId  = $request['channel_id'] ?? 0;
        if (empty($studentId) || empty($channelId)) {
            throw new RunTimeException(['params_error'], [$request]);
        }
        $userStatus = ErpUserService::getStudentStatus($studentId);
        $qrData = [
            'poster_id'           => $request['poster_id'],
            'user_current_status' => $userStatus['pay_status'] ?? 0,
            'activity_id'         => $request['activity_id'] ?? 0,
            'from_service'        => $request['from_service'] ?? '',
            'employee_uuid'       => $request['employee_uuid'] ?? '',
        ];
        $userType = Constants::USER_TYPE_STUDENT;
        $landingType = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        $userQrArr = MiniAppQrService::getUserMiniAppQr(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $studentId, $userType, $channelId, $landingType, $qrData);
        if (empty($userQrArr['qr_path'])) {
            throw new RunTimeException(['invalid_data']);
        }
        $qrPath = AliOSS::replaceCdnDomainForDss($userQrArr['qr_path']);
        return ['qr_path' => $qrPath, 'origin_qr_path' => $userQrArr['qr_path'], 'qr_id' => $userQrArr['qr_id']];
    }

    public static function parseUnique($uniqueCode,$type = Constants::SMART_APP_ID, $activityType = '')
    {
        $qrInfo          = QrInfoOpCHModel::getQrInfoById($uniqueCode);
        if ($type == Constants::REAL_APP_ID){
            $studentInfo = ErpStudentModel::getRecord(['id' => $qrInfo['user_id']], ['uuid']);
        }else{
            $studentInfo = DssStudentModel::getRecord(['id' => $qrInfo['user_id']], ['uuid']);
        }
        $checkActivityId = json_decode($qrInfo['qr_data'],true);

        if (!empty($activityType)) {
            // 限时活动
            if (empty($studentInfo['uuid'])) {
                throw new RunTimeException(['record_not_found']);
            }
        } else {
            if (empty($studentInfo['uuid']) || empty($checkActivityId['check_active_id'])) {
                throw new RunTimeException(['record_not_found']);
            }
        }
        return [
            'uuid'        => $studentInfo['uuid'],
            'check_activity_id' => $checkActivityId['check_active_id']
        ];
    }

    /**
     * 员工代替学生生成学生转介绍海报
     * @param $params
     * @return array|string[]
     */
    public static function replaceStudentCreatePoster($params): array
    {
        $returnData = [];
        $studentUuid = $params['student_uuid'] ?? '';
        $employeeUuid = $params['employee_uuid'] ?? '';
        if (empty($studentUuid) || empty($employeeUuid)) {
            SimpleLogger::info("student_uuid_or_employee_uuid_is_empty", [$params]);
            return $returnData;
        }

        $studentStatus = 0;
        switch ($params['app_id']) {
            case Constants::REAL_APP_ID:
                $studentInfo = ErpStudentModel::getStudentInfoByUuid($studentUuid);
                // 获取用户当前状态
                try {
                    if (!empty($studentInfo)) {
                        $studentStatus  = StudentService::dssStudentStatusCheck($studentInfo['id'])['student_status'] ?? 0;
                    }
                } catch (RunTimeException $e) {
                    SimpleLogger::info("student_status_error", [$params, $studentStatus, $studentInfo]);
                }
                break;
            default:
                break;
        }
        // 检查学生是否存在
        SimpleLogger::info("student_info", [$params, $studentInfo ?? []]);
        if (empty($studentInfo)) {
            return $returnData;
        }
        // 获取规则id详情
        $ruleInfo = MessagePushRulesModel::getRecord(
            [
                'id'   => DictConstants::get(DictConstants::MESSAGE_RULE, 'invite_friend_rule_id'),
                'type' => MessagePushRulesModel::PUSH_TYPE_CUSTOMER
            ],
            ['content']
        );
        $ruleInfoContentList = json_decode($ruleInfo['content'], true);
        // 取出规则列表里面第一张图片作为海报底图
        $imageInfo = [];
        foreach ($ruleInfoContentList as $item) {
            if ($item['type'] == WeChatConfigModel::CONTENT_TYPE_IMG && !empty($item['value'])) {
                $imageInfo = $item;
                break;
            }
        }
        unset($item);
        // 获取海报水印配置
        $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
        // 生成海报
        $posterId = $imageInfo['poster_id'] ?? 0;
        switch ($params['app_id']) {
            case Constants::REAL_APP_ID:
                $posterInfo = PosterService::generateLifeQRPosterAliOss(
                    [
                        'path' => $imageInfo['value'] ?? '',
                        'poster_id' => $posterId
                    ],
                    $config,
                    $studentInfo['id'],
                    $params['channel_id'] ?? 0,
                    [
                        'activity_id'   => RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'employee_replace_student_create_poster_activity_id'),
                        'employee_uuid' => $employeeUuid,
                        'poster_id'     => $posterId,
                        'user_status'   => $studentStatus,
                        'from_service'  => $params['from_service'] ?? '',
                    ]
                );
                break;
            default:
                break;
        }
        // 返回海报连接
        return [
            'format_poster_url' => $posterInfo['poster_save_full_path'] ?? '',
        ];
    }


    /**
     * 获取活动审核记录列表
     * @param $studentId
     * @param $activityId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getWeekActivityVerifyList($studentId, $activityId, $page, $limit)
    {
        // 获取列表
        $sharePosterData = RealSharePosterModel::getSharePosterHistory(['student_id' => $studentId, 'activity_id' => $activityId], $page, $limit);
        // 格式化信息
        if (empty($sharePosterData['list'])) {
            return $sharePosterData;
        }
        $returnData['total_count'] = $sharePosterData['total_count'];
        // 获取活动分享任务列表
        $activityData = RealWeekActivityModel::getActivityAndTaskData($activityId)[0];
        // 组合数据
        $time = time();
        foreach ($sharePosterData['list'] as $item) {
            $tmpList = [];
            $tmpList['task_name'] = RealWeekActivityService::formatWeekActivityTaskName(array_merge($activityData, $item));
            $tmpList['create_time'] = date("Y.m.d H:i", $item['create_time']);
            $tmpList['verify_status'] = $item['verify_status'];
            $tmpList['id'] = $item['id'];
            $tmpList['task_num'] = $item['task_num'];
            $tmpList['can_upload'] = (int)self::checkWeekActivityAllowUpload($activityData, $time);
            $returnData['list'][] = $tmpList;
        }
        return $returnData;
    }

    /**
     * 获取用户活动中上传截图成功通过审核的次数
     * @param $studentId
     * @param $activityId
     * @param $verifyStatus
     * @return number
     */
    public static function getSharePosterVerifySuccessCountData($studentId, $activityId, $verifyStatus = RealSharePosterModel::VERIFY_STATUS_QUALIFIED)
    {
        // 获取用户活动中上传截图成功通过审核的次数
        return RealSharePosterModel::getCount([
            'student_id' => $studentId,
            'activity_id' => $activityId,
            'type' => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => $verifyStatus
        ]);
    }
}

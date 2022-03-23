<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/18
 * Time: 17：37
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssEventTaskModel;
use App\Models\Dss\DssReferralActivityModel;
use App\Models\Dss\DssSharePosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentAccountDetail;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterDesignateUuidModel;
use App\Models\SharePosterModel;
use App\Libs\Erp;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\SharePosterTaskListModel;
use App\Models\TemplatePosterWordModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeekActivityModel;
use App\Models\WeekWhiteListModel;
use App\Services\Queue\QueueService;
use App\Services\TraitService\TraitSharePosterService;

class SharePosterService
{
    use TraitSharePosterService;

    public static $redisExpire = 432000; // 12小时

    const KEY_POSTER_VERIFY_LOCK = 'POSTER_VERIFY_LOCK';
    const KEY_POSTER_UPLOAD_LOCK = 'POSTER_UPLOAD_LOCK';

    /**
     * TODO qingfeng.lian  delete function
     * 上传截图列表
     * @param $params
     * @return array
     */
    public static function sharePosterList($params)
    {
        if (!empty($params['task_id'])) {
            $taskInfo = ErpEventTaskModel::getInfoByNodeId($params['task_id']);
            $params['task_id'] = $taskInfo[0]['id'] ?? 0;
        }
        list($posters, $totalCount) = SharePosterModel::posterList($params);
        if (!empty($posters)) {
            $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
            $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
            // 积分奖励
            $pointsAwardIds = array_column($posters, 'points_award_id');
            $pointsAwardIds = array_filter($pointsAwardIds);
            $pointsAwardInfo = [];
            if (!empty($pointsAwardIds)) {
                $pointsAwardInfo = ErpUserEventTaskAwardGoldLeafModel::getList(['id' => $pointsAwardIds]);
                $pointsAwardInfo = array_column($pointsAwardInfo['list'], null, 'id');
            }
            // 红包奖励
            $awardIds = array_column($posters, 'award_id');
            $awardIds = array_filter($awardIds);
            $awardInfo = [];
            if (!empty($awardIds)) {
                $awardInfo = ErpUserEventTaskAwardModel::getRecords(
                    ['id' => $awardIds],
                    ['id', 'award_amount', 'award_type', 'status', 'reason']
                );
                $awardInfo = array_column($awardInfo, null, 'id');
            }

            foreach ($posters as &$poster) {
                $poster['award_amount'] = 0;
                $poster['award_type'] = '';

                // 红包奖励
                $ids = explode(',', $poster['award_id']);
                foreach ($ids as $_id) {
                    if (!isset($awardInfo[$_id])) {
                        continue;
                    }
                    $poster['award_amount'] += $awardInfo[$_id]['award_amount'] ?? 0;
                    $poster['award_type'] = $awardInfo[$_id]['award_type'] ?? '';
                }
                // 积分奖励
                $ids = explode(',', $poster['points_award_id']);
                foreach ($ids as $_id) {
                    if (!isset($pointsAwardInfo[$_id])) {
                        continue;
                    }
                    $poster['award_amount'] += $pointsAwardInfo[$_id]['award_num'] ?? 0;
                    $poster['award_type'] = ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF;
                }
                $poster = self::formatOne($poster, $statusDict, $reasonDict);

                // 如果task_num > 0 显示任务名称
                if ($poster['task_num'] > 0) {
                    $activityTaskList = SharePosterTaskListModel::getRecords(['activity_id' => $poster['activity_id']]);
                    $activityTaskList = array_column($activityTaskList, 'task_name', 'task_num');
                    $poster['activity_name'] = $activityTaskList[$poster['task_num']] ?? '';
                }
            }
        }

        return [$posters, $totalCount];
    }

    /**
     * 上传截图审核历史记录
     * 周周领奖用户参与记录
     * @param $params
     * @return array
     */
    public static function weekActivityStudentUploadHistory($params)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        $page = $params['page'] ?? 1;
        $count = $params['count'] ?? 20;
        $studentId = $params['user_id'] ?? 0;
        // 获取参与的活动列表
        $joinActivityIds = SharePosterModel::getStudentJoinActivityList($studentId);
        if (empty($joinActivityIds)) {
            return $returnData;
        }
        $returnData['total_count'] = count($joinActivityIds);
        $activityIdOffset = array_slice($joinActivityIds, ($page - 1) * $count, $count);
        if (empty($activityIdOffset)) {
            return $returnData;
        }
        // 获取活动基础数据
        $activityBaseInfo = array_column(WeekActivityModel::getActivityAndTaskData($activityIdOffset), null, 'activity_id');
        $dictData = DictConstants::getSet(DictConstants::ACTIVITY_ENABLE_STATUS);
        $weekActivityConfig = DictConstants::getSet(DictConstants::DSS_WEEK_ACTIVITY_CONFIG);
        $activityEnableStatusConfig = DictConstants::getSet(DictConstants::ACTIVITY_ENABLE_STATUS);
        // 统计一个活动中用户参与次数， 成功，失败、等待审核
        $joinRecord = SharePosterModel::getSharePosterHistoryGroupActivityIdAndTaskNum($studentId, $activityIdOffset);
        $joinRecordFormat = $joinVerifyData = [];
        foreach ($joinRecord as $jk => $jv) {
            $joinRecordFormat[$jv['activity_id'] . '_' . (empty($jv['task_num']) ? 1 : $jv['task_num'])] = $jv;
            $joinVerifyData[$jv['activity_id']][$jv['verify_status']] += 1;
        }
        foreach ($activityBaseInfo as $info) {
            // 最早的活动不支持多次分享 所以task_num_count会是0， 给默认1
            $taskNumCount = empty($info['task_num_count']) ? '1' : $info['task_num_count'];
            $tmpFormatData = [
                'activity_id' => $info['activity_id'],
                'task_num_count' => $taskNumCount,
                'award_prize_type' => $info['award_prize_type'],
                'delay_day' => empty($info['delay_second']) ? 0 : ($weekActivityConfig['send_award_base_delay_second'] + $info['delay_second']) / Util::TIMESTAMP_ONEDAY,
                'activity_name' => self::formatWeekActivityName($info),
                'activity_status_zh' => ($info['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE) ? $dictData[OperationActivityModel::ENABLE_STATUS_DISABLE] : WeekActivityService::formatActivityTimeStatus($info)['activity_status_zh'],
                'success' => intval($joinVerifyData[$info['activity_id']][SharePosterModel::VERIFY_STATUS_QUALIFIED] ?? 0),
                'fail' => intval($joinVerifyData[$info['activity_id']][SharePosterModel::VERIFY_STATUS_UNQUALIFIED] ?? 0),
                'wait' => intval($joinVerifyData[$info['activity_id']][SharePosterModel::VERIFY_STATUS_WAIT] ?? 0),
                'task_list' => []
            ];
            // 早期活动不支持多次分享
            if (empty($info['task_data'])) {
                $tmpJoinRecordFormatKey = $info['activity_id'] . '_' . $taskNumCount;
                $tmpFormatAwardData = self::formatSharePosterAwardStatus($info['activity_id'], $joinRecordFormat[$tmpJoinRecordFormatKey]);
                $tmpFormatData['task_list'][] = [
                    'task_num' => $taskNumCount,
                    'award_type' => $tmpFormatAwardData['award_type'],
                    'verify_status' => $tmpFormatAwardData['verify_status'],
                    'award_amount' => $tmpFormatAwardData['award_amount'],
                    'award_status' => $tmpFormatAwardData['award_status'],
                    'award_status_zh' => $tmpFormatAwardData['award_status_zh'],
                ];
            } else {
                //多次分享任务
                list($tmpTask, $tmpTaskNumCount) = self::filterSpecialActivityTaskData($info['activity_id'], explode(',', $info['task_data']));
                $tmpFormatData['task_list'] = array_map(function ($tmv) use ($joinRecordFormat, $info) {
                    list($tmpTaskNode['task_num'],
                        $tmpTaskNode['award_amount'],
                        $tmpTaskNode['award_type'],) = explode('-', $tmv);
                    $tmpTaskNode['verify_status'] = (int)$joinRecordFormat[$info['activity_id'] . '_' . $tmpTaskNode['task_num']]['verify_status'];
                    $tmpTaskNode['award_status'] = $joinRecordFormat[$info['activity_id'] . '_' . $tmpTaskNode['task_num']]['award_status'];
                    $tmpFormatAwardData = self::formatSharePosterAwardStatus($info['activity_id'], $tmpTaskNode);
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
     * 格式化单条数据
     * @param $poster
     * @param array $statusDict
     * @param array $reasonDict
     * @return mixed
     */
    public static function formatOne($poster, $statusDict = [], $reasonDict = [])
    {
        if (empty($reasonDict)) {
            $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
        }
        if (empty($statusDict)) {
            $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
        }
        $imgSizeH = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'img_size_h');
        $poster['status_name'] = $statusDict[$poster['poster_status']];
        $poster['reason_str'] = self::reasonToStr($poster['verify_reason'], $reasonDict);
        $poster['mobile'] = isset($poster['mobile']) ? Util::hideUserMobile($poster['mobile']) : '';
        $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
        $poster['check_time'] = Util::formatTimestamp($poster['check_time'], '');
        $poster['img_url'] = AliOSS::signUrls(
            $poster['image_path'],
            "",
            "",
            "",
            false,
            "",
            $imgSizeH
        );
        if (!empty($poster['remark'])) {
            $poster['reason_str'][] = $poster['remark'];
        }
        $poster['reason_str'] = implode('/', $poster['reason_str']);
        $poster['activity_name'] = $poster['activity_name'] . '(' . date('m月d日', $poster['start_time']) . '-' . date('m月d日', $poster['end_time']) . ')';
        if (!isset($poster['award_amount'])) {
            $poster['award_amount'] = 0;
            $poster['award_type'] = 0;
            //默认的奖励文案和奖励数量--2021.10.15
            $poster['default_award_copywriting'] = "";
            $poster['default_award_amount'] = "";
            if (!empty($poster['points_award_id'])) {
                $ids = explode(',', $poster['points_award_id']);
                $pointsAwardInfo = ErpUserEventTaskAwardGoldLeafModel::getList(['id' => $ids]);
                $pointsAwardInfo = array_column($pointsAwardInfo['list'], 'award_num');
                $poster['award_amount'] = array_sum($pointsAwardInfo);
                $poster['award_type'] = ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF;
            }
            if (!empty($poster['award_id'])) {
                $ids = explode(',', $poster['award_id']);
                $awardInfo = ErpUserEventTaskAwardModel::getRecords(
                    ['id' => $ids],
                    ['id', 'award_amount', 'award_type', 'status', 'reason']
                );
                $awardInfo = array_column($awardInfo, null, 'id');
                $poster['award_amount'] = array_sum(array_column($awardInfo, 'award_amount'));
                $_one = array_pop($awardInfo);
                $poster['award_type'] = $_one['award_type'] ?? '';
            }
        }
        //获取奖励发放配置
        list($wkIds, $oneActivityId, $twoActivityId) = DictConstants::get(DictConstants::XYZOP_1262_WEEK_ACTIVITY, [
            'xyzop_1262_week_activity_ids',
            'xyzop_1262_week_activity_one',
            'xyzop_1262_week_activity_two'
        ]);
        $wkIds = array_merge(explode(',', $wkIds), [$oneActivityId], explode(',', $twoActivityId));
        if (in_array($poster['activity_id'], $wkIds)) {
            $poster['default_award_copywriting'] = "活动结束后人工发放";
            $poster['default_award_amount'] = "人工发放";
        }
        $poster['award'] = self::formatAwardInfo($poster['award_amount'], $poster['award_type']);

        // 如果task_num 不等于0 读取task_num对应的分享任务名称
        $poster['task_name'] = $poster['activity_name'];
        if ($poster['task_num'] > 0) {
            $poster['task_name'] = SharePosterTaskListModel::getRecord(['activity_id' => $poster['activity_id'], 'task_num' => $poster['task_num']])['task_name'] ?? '';
        }
        return $poster;
    }

    /**
     * 审核原因转字符串
     * @param $reason
     * @param array $dict
     * @return array
     */
    public static function reasonToStr($reason, $dict = [])
    {
        if (is_string($reason)) {
            $reason = explode(',', $reason);
        }
        if (empty($reason)) {
            return [];
        }
        if (empty($dict)) {
            $dict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
        }
        $str = [];
        foreach ($reason as $item) {
            $str[] = $dict[$item] ?? $item;
        }
        return $str;
    }

    /**
     * 审核通过、发放奖励
     * @param $posterIds
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     * @throws \Exception
     */
    public static function approvedCheckin($posterIds, $employeeId)
    {
        if (count($posterIds) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        if (empty($posterIds)) {
            throw new RunTimeException(['poster_id_is_required']);
        }
        $posters = SharePosterModel::getPostersByIds($posterIds);
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $time = time();
        $status = SharePosterModel::VERIFY_STATUS_QUALIFIED;
        // 查询所有打卡活动下的任务：
        $taskIds = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'task_ids');
        $taskIds = json_decode($taskIds, true);
        if (empty($taskIds)) {
            SimpleLogger::error('EMPTY TASK CONFIG', []);
        }
        $allTasks = [];
        if (!empty($taskIds)) {
            $allTasks = DssEventTaskModel::getRecords(
                [
                    'id' => $taskIds
                ]
            );
        }
        // 已超时的海报
        foreach ($posters as $poster) {
            if ($poster['poster_status'] != SharePosterModel::VERIFY_STATUS_WAIT) {
                continue;
            }

            $awardId = $poster['award_id'];
            if (empty($awardId)) {
                $taskId = 0;
                // 检查打卡次数，发红包
                $total = SharePosterModel::getCount([
                    'type' => SharePosterModel::TYPE_CHECKIN_UPLOAD,
                    'student_id' => $poster['student_id'],
                    'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                    'id[!]' => $poster['id']
                ]);
                // 审核通过状态还未更新，查询总数加1
                $total += 1;
                foreach ($allTasks as $task) {
                    $condition = json_decode($task['condition'], true);
                    if ($total == $condition['total_days']) {
                        $taskId = $task['id'];
                        break 1;
                    }
                }
                if (!empty($taskId)) {
                    $taskRes = self::completeTask($poster['uuid'], $taskId, ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE);
                    if (empty($taskRes['user_award_ids'])) {
                        throw new RuntimeException(['empty erp award ids']);
                    }
                    $needDealAward = [];
                    foreach ($taskRes['user_award_ids'] as $awardId) {
                        $needDealAward[$awardId] = ['id' => $awardId];
                    }
                    if (!empty($needDealAward)) {
                        //实际发放结果数据 调用微信红包，
                        QueueService::sendRedPack($needDealAward);
                    }
                }
            }

            // 更新记录
            $updateRecord = SharePosterModel::updateRecord(
                $poster['id'],
                [
                    'verify_status' => $status,
                    'award_id' => $awardId,
                    'verify_time' => $time,
                    'update_time' => $time,
                    'verify_user' => $employeeId,
                ]
            );
            if (empty($updateRecord)) {
                throw new RunTimeException(['update_failure']);
            }
            $userInfo = UserService::getUserWeiXinInfoByUserId(
                Constants::SMART_APP_ID,
                $poster['student_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
            );
            // 发送审核消息队列
            QueueService::checkinPosterMessage($poster['day'], $status, $userInfo['open_id'], Constants::SMART_APP_ID);
        }
        return true;
    }

    /**
     * 完成任务，返回奖励ID
     * @param $uuid
     * @param $taskId
     * @param int $status
     * @return array|mixed
     * @throws RunTimeException
     */
    public static function completeTask($uuid, $taskId, $status = ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE)
    {
        if (empty($uuid) || empty($taskId)) {
            return [];
        }

        $erp = new Erp();
        $taskResult = $erp->updateTask($uuid, $taskId, $status);
        if (empty($taskResult)) {
            throw new RunTimeException(['erp_create_user_event_task_award_fail']);
        }
        return $taskResult;
    }

    /**
     * 审核不通过
     * @param $posterId
     * @param $employeeId
     * @param $reason
     * @param $remark
     * @return int|null
     * @throws RunTimeException
     */
    public static function refusedCheckin($posterId, $employeeId, $reason, $remark)
    {
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }

        $posters = SharePosterModel::getPostersByIds([$posterId]);
        $poster = $posters[0];
        if (empty($poster)) {
            throw new RunTimeException(['record_not_found']);
        }
        $status = SharePosterModel::VERIFY_STATUS_UNQUALIFIED;
        $time = time();
        $updateData = [
            'verify_status' => $status,
            'award_id' => $poster['award_id'],
            'verify_time' => $time,
            'update_time' => $time,
            'verify_user' => $employeeId,
            'verify_reason' => implode(',', $reason),
            'remark' => $remark,
        ];
        $update = SharePosterModel::updateRecord($poster['id'], $updateData);
        // 审核不通过, 发送模版消息
        if ($update > 0) {
            $userInfo = UserService::getUserWeiXinInfoByUserId(
                Constants::SMART_APP_ID,
                $poster['student_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
            );
            QueueService::checkinPosterMessage($poster['day'], $status, $userInfo['open_id'], Constants::SMART_APP_ID);
        }

        return $update > 0;
    }

    /**
     * 格式化奖励信息
     * @param $amount
     * @param $type
     * @return string
     */
    public static function formatAwardInfo($amount, $type)
    {
        if (empty($amount)) {
            return '';
        }
        if ($type == ErpUserEventTaskAwardModel::AWARD_TYPE_CASH) {
            //金钱单位：分
            return ($amount / 100) . '元';
        }
        if ($type == ErpUserEventTaskAwardModel::AWARD_TYPE_DURATION) {
            //时间单位：天
            return $amount . '天';
        }
        if ($type == ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF) {
            // 积分
            return $amount;
        }
        return '';
    }


    /**
     * 格式化信息
     * @param $formatData
     * @return mixed
     */
    public static function formatData($formatData)
    {
        // 获取dict数据
        $dictMap = DssDictService::getTypesMap([Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON, Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS]);
        foreach ($formatData as $dk => &$dv) {
            $dv['img_oss_url'] = AliOSS::replaceCdnDomainForDss($dv['img_url']);
            $dv['status_name'] = $dictMap[Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS][$dv['status']]['value'];
            $reasonStr = [];
            if (!empty($dv['reason'])) {
                $dv['reason'] = explode(',', $dv['reason']);
                array_map(function ($reasonId) use ($dictMap, &$reasonStr) {
                    $reasonStr[] = $dictMap[Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON][$reasonId]['value'];
                }, $dv['reason']);
            }
            if ($dv['remark']) {
                $reasonStr[] = $dv['remark'];
            }
            $dv['reason_str'] = implode('/', $reasonStr);
        }
        return $formatData;
    }

    /**
     * @param $awardBaseInfo
     * @param $awardGiveInfo
     * @param $redPackGiveInfo
     * @return array|void
     * 奖励领取说明信息
     */
    private static function displayAwardExplain($awardBaseInfo, $awardGiveInfo, $redPackGiveInfo)
    {
        $failReasonZh = '';
        if ($awardBaseInfo['award_type'] != ErpReferralService::AWARD_TYPE_CASH) {
            return;
        }
        if ($awardGiveInfo['status'] == ErpReferralService::AWARD_STATUS_GIVE_FAIL) {
            $failReasonZh = WeChatAwardCashDealModel::getWeChatErrorMsg($redPackGiveInfo['result_code']);
        } elseif ($awardGiveInfo['status'] == ErpReferralService::AWARD_STATUS_REJECTED) {
            $failReasonZh = $awardGiveInfo['reason'];
        }
        $awardStatusZh = ErpReferralService::AWARD_STATUS[$awardGiveInfo['status']];
        return [$awardStatusZh, $failReasonZh];
    }

    /**
     * 上传截图奖励明细列表
     * @param $studentId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function sharePostAwardList($studentId, $page, $limit)
    {
        //获取学生已参加活动列表
        $data = ['count' => 0, 'list' => []];
        $queryWhere = ['student_id' => $studentId, 'type' => DssSharePosterModel::TYPE_UPLOAD_IMG];
        $count = DssSharePosterModel::getCount($queryWhere);
        if (empty($count)) {
            return $data;
        }
        $data['count'] = $count;
        //查询起始数据量超出数据总量，直接返回
        $offset = ($page - 1) * $limit;
        if ($offset > $count) {
            return $data;
        }
        $queryWhere['ORDER'] = ['create_time' => 'DESC'];
        $queryWhere['LIMIT'] = [$offset, $limit];
        $activityList = DssSharePosterModel::getRecords($queryWhere, ['id', 'activity_id', 'status', 'create_time', 'img_url', 'reason', 'remark', 'award_id', 'points_award_id']);
        if (empty($activityList)) {
            return $data;
        }
        // 获取老的红包奖励信息
        $awardIds = array_filter(array_unique(array_column($activityList, 'award_id')));
        $awardInfo = $redPackDeal = [];
        if (!empty($awardIds)) {
            //奖励相关的状态
            $awardInfo = array_column(ErpUserEventTaskAwardModel::getRecords(['id' => $awardIds], ['id', 'award_amount', 'award_type', 'status', 'reason']), null, 'id');
            //红包相关的发放状态
            $redPackDeal = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $awardIds]), null, 'user_event_task_award_id');
        }
        // 获取新的积分奖励信息
        $pointsAwardIds = array_column($activityList, 'points_award_id');
        if (!empty($pointsAwardIds)) {
            $pointsAwardList = ErpUserEventTaskAwardGoldLeafModel::getRecords(['id' => $pointsAwardIds]);
            $pointsAwardArr = array_column($pointsAwardList, null, 'id');
        }

        //获取活动信息
        $activityInfo = array_column(DssReferralActivityModel::getRecords(['id' => array_unique(array_column($activityList, 'activity_id'))], ['name', 'id', 'task_id', 'event_id']), null, 'id');
        //格式化信息
        $activityList = self::formatData($activityList);
        foreach ($activityList as $k => $v) {
            $data['list'][$k]['name'] = $activityInfo[$v['activity_id']]['name'];
            $data['list'][$k]['status'] = $v['status'];
            $data['list'][$k]['status_name'] = $v['status_name'];
            $data['list'][$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
            $data['list'][$k]['img_oss_url'] = $v['img_oss_url'];
            $data['list'][$k]['reason_str'] = $v['reason_str'];
            $data['list'][$k]['id'] = $v['id'];
            // 计算奖励
            if ($v['points_award_id'] > 0) {
                $_pointsAwardInfo = !empty($pointsAwardArr[$v['points_award_id']]) ? $pointsAwardArr[$v['points_award_id']] : [];
                // 积分奖励
                $_awardNum = $_pointsAwardInfo['award_num'] ?? 0;
                $_awardType = ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF;
                $awardStatusZh = ErpReferralService::AWARD_STATUS[$_pointsAwardInfo['status']];
                $failReasonZh = "";
            } else {
                // 老版现金奖励
                $_awardNum = !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_amount'] : 0;
                $_awardType = !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_type'] : 0;
                list($awardStatusZh, $failReasonZh) = !empty($v['award_id']) ? self::displayAwardExplain($awardInfo[$v['award_id']], $awardInfo[$v['award_id']], $redPackDeal[$v['award_id']] ?? null) : [];
            }

            $data['list'][$k]['award'] = self::formatAwardInfo($_awardNum, $_awardType);
            $data['list'][$k]['award_status_zh'] = $awardStatusZh;
            $data['list'][$k]['fail_reason_zh'] = $failReasonZh;
        }
        return $data;
    }

    /**
     * 上传截图
     * @param $params
     * @return int|mixed|string|null
     * @throws RunTimeException
     */
    public static function uploadSharePoster($params)
    {
        $activityId = $params['activity_id'] ?? 0;
        $studentId = $params['student_id'] ?? 0;
        $imagePath = $params['image_path'] ?? '';
        $taskNum = $params['task_num'] ?? 0;

        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        // 上传并发处理，但不显示任何错误内容，用户无感
        $redis = RedisDB::getConn();
        $lockKey = self::KEY_POSTER_UPLOAD_LOCK . $params['student_id'] . $params['activity_id'];
        $lock = $redis->set($lockKey, $uploadRecord['id'] ?? 0, 'EX', 3, 'NX');
        if (empty($lock)) {
            throw new RunTimeException(['']);
        }
        $time = time();
        //审核通过不允许上传截图
        $uploadRecord = SharePosterModel::getRecord([
            'student_id' => $studentId,
            'activity_id' => $activityId,
            'task_num' => $taskNum,
            'ORDER' => ['id' => 'DESC']
        ], ['verify_status', 'id']);
        if (!empty($uploadRecord) && ($uploadRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        /** 如果没上传过，则校验身份 */
        if (empty($uploadRecord)) {
            $studentInfo = DssStudentModel::getRecord(['id' => $studentId]);
            //资格检测 - 获取用户身份属性
            list($studentIsNormal) = UserService::checkDssStudentIdentityIsNormal($studentId);
            // 不是有效用户，检查是否是指定的uuid
            if (!$studentIsNormal) {
                // 检查用户是不是活动指定的uuid
                $designateUuid = SharePosterDesignateUuidModel::getRecord(['activity_id' => $activityId, 'uuid' => $studentInfo['uuid'] ?? '']);
                if (empty($designateUuid)) {
                    throw new RunTimeException(['student_status_disable']);
                }
            }
            // 检查活动是否在用户所在的国家
            OperationActivityModel::checkWeekActivityCountryCode($studentInfo, $activityInfo);
        }
        // 检查周周领奖活动是否可以上传
        if (!SharePosterService::checkWeekActivityAllowUpload($activityInfo, $time)) {
            throw new RunTimeException(['wait_for_next_event']);
        }

        $data = [
            'student_id' => $studentId,
            'type' => SharePosterModel::TYPE_WEEK_UPLOAD,
            'activity_id' => $activityId,
            'image_path' => $imagePath,
            'verify_reason' => '',
            'unique_code' => '',
            'create_time' => $time,
            'update_time' => $time,
            'task_num' => $taskNum,
        ];
        if (empty($uploadRecord) || $uploadRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            $res = SharePosterModel::insertRecord($data);
        } else {
            unset($data['create_time']);
            $count = SharePosterModel::updateRecord($uploadRecord['id'], $data);
            if (empty($count)) {
                throw new RunTimeException(['update_fail']);
            }
            $res = $uploadRecord['id'];
        }
        if (empty($res)) {
            throw new RunTimeException(['share_poster_add_fail']);
        }
        //系统自动审核
        QueueService::checkPoster(['id' => $res, 'app_id' => Constants::SMART_APP_ID]);
        return $res;
    }

    /**
     * 截图审核通过
     * @param $id
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function approvalPoster($id, $params = [])
    {
        $type = $params['type'] ?? SharePosterModel::TYPE_WEEK_UPLOAD;
        $posters = SharePosterModel::getPostersByIds($id, $type);
        if (count($posters) != count($id)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $params['activity_id']]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['week_activity_not_found']);
        }
        $now = time();
        $updateData = [
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time' => $now,
            'verify_user' => $params['employee_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
            'update_time' => $now,
        ];
        $msgId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'approval_poster_wx_msg_id');
        $msgUrl = DictConstants::get(DictConstants::DSS_JUMP_LINK_CONFIG, 'dss_gold_left_shop_url');
        $sendAwardBaseDelaySecond = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'send_award_base_delay_second');
        $replaceParams = [
            'delay_send_award_day' => intval((intval($sendAwardBaseDelaySecond) + intval($activityInfo['delay_second'])) / Util::TIMESTAMP_ONEDAY),
            'url' => $msgUrl,
        ];
        $operationStudentActivity = [];
        // 获取活动奖励信息列表
        $passAwardList = array_column(SharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityInfo['activity_id']]), 'award_amount', 'success_pass_num');
        krsort($passAwardList); // 排序 - 目的是后面能直接方便地取出最大次数的奖励
        //开始处理数据
        foreach ($posters as $key => $poster) {
            // 审核数据操作锁，解决并发导致的重复审核和发奖
            $lockKey = self::KEY_POSTER_VERIFY_LOCK . $poster['id'];
            try {
                if (!Util::setLock($lockKey, 60)) {
                    continue;
                }

                $where = [
                    'id' => $poster['id'],
                    'verify_status' => $poster['poster_status']
                ];
                $update = SharePosterModel::batchUpdateRecord($updateData, $where);
                // 影响行数是0 ，说明没有执行成功，不做后续处理
                if (empty($update)) {
                    SimpleLogger::info("DSS_approvalPoster", [$poster, $update]);
                    continue;
                }
                //智能产品激活
                QueueService::autoActivate(['student_uuid' => $poster['uuid'], 'passed_time' => time(), 'app_id' => Constants::SMART_APP_ID]);
                // 计算审核通过的次数
                $passNum = SharePosterModel::getCount([
                    'student_id' => $poster['student_id'],
                    'activity_id' => $poster['activity_id'],
                    'type' => SharePosterModel::TYPE_WEEK_UPLOAD,
                    'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                ]);
                // 组装微信消息需要的参数
                $replaceParams = [
                    'activity_name' => SharePosterService::formatWeekActivityName([
                        'task_num_count' => $passAwardList[0]['success_pass_num'],
                        'start_time' => $activityInfo['start_time'],
                        'end_time' => $activityInfo['end_time'],
                    ]),
                    'url' => $msgUrl,
                    'passes_num' => $passNum,
                    'award_num' => $passAwardList[$passNum] ?? 0,
                ];
                // 区分发奖规则 - 保存即时发奖的数据
                if (self::checkIsNewRule($poster['activity_id'])) {
                    if (!isset($operationStudentActivity[$poster['student_id'] . '_' . $poster['activity_id']])) {
                        // 投递发奖信息
                        QueueService::addUserPosterAward([
                            'app_id' => Constants::SMART_APP_ID,
                            'student_id' => $poster['student_id'],
                            'activity_id' => $poster['activity_id'],
                            'act_status' => ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE,
                            'verify_time' => $now,
                        ]);
                        $operationStudentActivity[$poster['student_id'] . '_' . $poster['activity_id']] = $now;
                    }
                    // 即时发奖消息模板id
                    $msgId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'approval_poster_forthwith_wx_msg_id');
                }
                // 发送消息
                QueueService::sendUserWxMsg(Constants::SMART_APP_ID, $poster['student_id'], $msgId, [
                    'replace_params' => $replaceParams,
                ]);
            } finally {
                $res = Util::unLock($lockKey);
                SimpleLogger::info("DSS_approvalPoster_try_finally_lock", [$poster, $lockKey, $res]);
            }
        }
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
        $activityId = $data['activity_id'] ?? 0;
        $studentId = $data['student_id'] ?? 0;
        $studentInfo = DssStudentModel::getRecord(['id' => $studentId]);
        $studentUUID = $studentInfo['uuid'] ?? '';
        $verifyTime = $data['verify_time'] ?? 0;
        $status = $data['status'] ?? ErpReferralService::EVENT_TASK_STATUS_COMPLETE;

        $lockKey = "queue_dss_add_user_award_lock_" . $studentId . '_' . $activityId;
        try {
            // 加锁失败扔回队列
            if (!Util::setLock($lockKey, 60)) {
                // 扔回队列
                QueueService::addUserPosterAward([
                    'app_id' => Constants::SMART_APP_ID,
                    'student_id' => $studentId,
                    'activity_id' => $activityId,
                    'act_status' => ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE,
                    'verify_time' => $verifyTime,
                ]);
                SimpleLogger::info("queue_dss_add_user_award_set_lock_fail", $data);
                return true;
            }
            // 奖励白名单用户，发放的奖励应该是待发放状态
            $whiteList = WeekWhiteListModel::getRecord(['uuid' => $studentUUID, 'status' => WeekWhiteListModel::NORMAL_STATUS]);
            if (!empty($whiteList)) {
                $status = ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING;   // 待发放
            }
            // 获取活动信息
            $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
            // 活动不存在停止发奖并记录日志
            if (empty($activityInfo)) {
                SimpleLogger::info('addUserAward', ['msg' => 'activity_not_found', $data, $activityInfo]);
                return false;
            }
            // 获取学生信息
            $studentInfo = DssStudentModel::getRecord(['id' => $studentId]);
            // 指定业务线中学生是否存在
            if (empty($studentInfo)) {
                SimpleLogger::info('addUserAward', ['msg' => 'student_not_found', $data, $studentInfo]);
                return false;
            }
            /** 这里 脚本发放是没有 审核通过时间的*/
            if (self::checkIsNewRule($activityId)) {
                /** 新规则 */
                // 获取审核通过截图列表
                $sharePosterList = self::getStudentSharePosterPassList($studentId, $activityId, $verifyTime);
                // 整理审核截图通过次数信息
                $isSendAwardNum = 0;
                foreach ($sharePosterList as $key => $item) {
                    if ($item['verify_time'] < $verifyTime || !empty($item['points_award_id'])) {
                        $isSendAwardNum += 1;
                        unset($sharePosterList[$key]);
                    }
                }
                unset($key, $item);
                if (empty($sharePosterList)) {
                    // 没有未发放的奖励，返回成功
                    SimpleLogger::info('addUserAward', ['msg' => 'share_poster_list_empty', $data, $studentInfo, $sharePosterList]);
                    return true;
                }
                // 发奖
                foreach ($sharePosterList as $poster) {
                    $isSendAwardNum += 1;
                    // 获取审核通过奖励规则
                    $passAwardInfo = self::getActivityForthwithSendAwardRule($activityId, $isSendAwardNum);
                    SimpleLogger::info("addUserAward_poster", [$poster, $isSendAwardNum]);
                    self::sendStudentWeekActivityAward($studentInfo, $activityInfo, $passAwardInfo, $status, [$poster['id']]);
                }
            } else {
                // 获取审核通过奖励规则
                $passAwardInfo = self::getActivityOverSendAwardRule($studentId, $activityId);
                self::sendStudentWeekActivityAward($studentInfo, $activityInfo, $passAwardInfo, $status);
            }
        } finally {
            Util::unLock($lockKey);
        }
        return true;
    }

    /**
     * 截图审核-未通过
     * @param $id
     * @param array $params
     * @param int $status
     * @return bool
     * @throws RunTimeException
     */
    public static function refusedPoster($id, $params = [], $status = SharePosterModel::VERIFY_STATUS_UNQUALIFIED)
    {
        $type = $params['type'] ?? SharePosterModel::TYPE_WEEK_UPLOAD;
        $poster = SharePosterModel::getPostersByIds([$id], $type);
        $poster = $poster[0] ?? [];
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $time = time();
        $update = SharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'verify_time' => $time,
            'verify_user' => $params['employee_id'],
            'verify_reason' => implode(',', $params['reason']),
            'update_time' => $time,
            'remark' => $params['remark'] ?? '',
        ]);
        if (!empty($poster['award_id'])) {
            (new Erp())->updateAward(
                explode(',', $poster['award_id']),
                ErpReferralService::AWARD_STATUS_REJECTED,
                $params['employee_id']
            );
        }
        // 审核不通过, 发送模版消息
        if ($update > 0 && $status == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            $wechatConfigId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'refused_poster_wx_msg_id');
            QueueService::sendUserWxMsg(Constants::SMART_APP_ID, $poster['student_id'], $wechatConfigId, [
                'replace_params' => [
                    'url' => DictConstants::get(DictConstants::DSS_JUMP_LINK_CONFIG, 'dss_share_poster_history_list')
                ],
            ]);
        }

        return $update > 0;
    }

    /**
     * 获取分享文案列表
     * @param $params
     * @return array
     */
    public static function getShareWordList($params)
    {
        $list = TemplatePosterWordModel::getFrontList($params);
        foreach ($list as &$item) {
            $item = TemplatePosterWordModel::formatOne($item);
        }
        return $list;
    }

    /**
     * 截图审核详情
     * @param $id
     * @param array $userInfo
     * @return array|mixed
     * @throws RunTimeException
     */
    public static function sharePosterDetail($id, $userInfo = [])
    {
        if (empty($id)) {
            return [];
        }
        list($posters) = SharePosterModel::getWeekPosterList(['id' => $id, 'no_limit' => true]);
        $poster = $posters[0];
        if (empty($poster)) {
            return [];
        }
        $poster['can_upload'] = Constants::STATUS_TRUE;
        if ($poster['poster_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            $poster['can_upload'] = Constants::STATUS_FALSE;
        }
        $time = time();
        // 检查周周领奖活动是否可以上传
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $poster['activity_id']]);
        if (!SharePosterService::checkWeekActivityAllowUpload($activityInfo, $time)) {
            $poster['can_upload'] = Constants::STATUS_FALSE;
        }

        $gold_leaf = ErpUserEventTaskAwardGoldLeafModel::getRecord(['id' => $poster['points_award_id']], ['status']);
        $poster['gold_leaf_status'] = $gold_leaf['status'];
        $poster = self::formatOne($poster);
        $activity = WeekActivityModel::getRecord(['activity_id' => $poster['activity_id']]);
        $activity = ActivityService::formatData($activity);
        return ['poster' => $poster, 'activity' => $activity];
    }

    /**
     * 获取当前上线状态的分享海报文案列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function sharePosterWordList($params)
    {
        $where = ['status' => TemplatePosterWordModel::NORMAL_STATUS];
        !empty($params['app_id']) && $where['app_id'] = $params['app_id'];

        $list = TemplatePosterWordModel::getRecords($where, ['id', 'content']);
        if (empty($list)) {
            return [];
        }
        foreach ($list as &$item) {
            $item = TemplatePosterWordModel::formatOne($item);
        }
        unset($item);
        return $list;
    }

    /**
     * 智能 - 检查周周领奖活动是否可以上传 - 不校验是学生是否能参与该活动
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
        $activityOverAllowUploadSecond = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'activity_over_allow_upload_second');
        // 能否重新上传 - 活动结束时间超过5天不能重新上传
        if (($time - $activityInfo['end_time']) > $activityOverAllowUploadSecond) {
            return false;
        }

        /** 检查活动状态 - 非特殊活动中的id 需要检查活动启用状态 */
        //获取奖励发放配置
        list($wkIds, $oneActivityId, $twoActivityId) = DictConstants::get(DictConstants::XYZOP_1262_WEEK_ACTIVITY, [
            'xyzop_1262_week_activity_ids',
            'xyzop_1262_week_activity_one',
            'xyzop_1262_week_activity_two'
        ]);
        $wkIds = array_merge(explode(',', $wkIds), [$oneActivityId], explode(',', $twoActivityId));
        if (!in_array($activityInfo['activity_id'], $wkIds)) {
            if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
                return false;
            }
        }
        return true;
    }

    /**
     * 格式化处理活动名称
     * @param $activityData
     * @return string
     */
    public static function formatWeekActivityName($activityData)
    {
        $taskNumCount = empty($activityData['task_num_count']) ? '1' : $activityData['task_num_count'];
        $timeFormat = '(' . date("m.d", $activityData['start_time']) . '-' . date("m.d", $activityData['end_time']) . ')';
        return $taskNumCount . '次分享截图活动' . $timeFormat;
    }

    /**
     * 格式化处理活动分享任务名称
     * @param $activityTaskData
     * @return string
     */
    public static function formatWeekActivityTaskName($activityTaskData)
    {
        $taskNumCount = empty($activityTaskData['task_num_count']) ? '1' : $activityTaskData['task_num_count'];
        $timeFormat = '(' . date("m.d", $activityTaskData['start_time']) . '-' . date("m.d", $activityTaskData['end_time']) . ')';
        $taskNum = empty($activityTaskData['task_num']) ? 1 : $activityTaskData['task_num'];
        return $taskNumCount . '次分享截图活动-'.$taskNum .$timeFormat;
    }

    /**
     * 格式化处理截图上传奖励发放状态
     * @param int $activityId   活动id
     * @param array $joinRecord   上传记录信息share_poster表信息
     * @return array
     */
    private static function formatSharePosterAwardStatus($activityId, $joinRecord)
    {
        $returnData = [
            'award_amount' => empty($joinRecord['award_amount']) ? '--' : $joinRecord['award_amount'],
            'award_type' =>  Constants::ERP_ACCOUNT_NAME_GOLD_LEFT,
            'verify_status' => (int)$joinRecord['verify_status'],
        ];
        //临时指定的多次分享任务的活动
        $activityIds2005 = explode(',', DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'activity_id_is_2005day'));
        $approvalPosterForthwithId = explode(',', DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'tmp_rule_last_activity_id'));
        $oldRuleLastActivityId = explode(',', DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id'));
        if (in_array($activityId, $activityIds2005) && $joinRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            // 2005天发放奖励的活动，审核成功的 显示人工发放
            $returnData['award_amount'] = "--";
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE;
            $returnData['award_status_zh'] = "人工发放";
        } elseif (!empty($joinRecord['award_id']) || !empty($joinRecord['points_award_id']))  {
            // 发奖记录id不为空，说明奖励已经发放
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE;
            $returnData['award_status_zh'] = ErpUserEventTaskAwardGoldLeafService::STATUS_DICT[ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE];
            // 如果发的是红包 - 查询红包金额
            !empty($joinRecord['award_id']) && $returnData['award_type'] = Constants::ERP_ACCOUNT_NAME_CASH_CODE;
            // TODO qingfeng.lian  红包金额查询的问题
        } elseif ($activityId <= $approvalPosterForthwithId && $activityId > $oldRuleLastActivityId && $joinRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            // 发奖记录id为空，改为统一发放之后到临时支持即时发放时中间可能存在没有记录奖励id的数据，这部分数据有是已发放的
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE;
            $returnData['award_status_zh'] = ErpUserEventTaskAwardGoldLeafService::STATUS_DICT[ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE];
            // 这中间都是统一发放的 所以直接查询奖励即可
            $returnData['award_amount'] = ErpUserEventTaskAwardGoldLeafModel::getRecord(['activity_id' => $activityId, 'student_id' => $joinRecord['student_id']])['award_num'] ?? 0;
        } elseif ($joinRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            // 审核通过 - 已发放
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE;
            $returnData['award_status_zh'] = ErpUserEventTaskAwardGoldLeafService::STATUS_DICT[ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE];
        } elseif ($joinRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
            // 待审核 - 待发放
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING;
            $returnData['award_status_zh'] = ErpUserEventTaskAwardGoldLeafService::STATUS_DICT[ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING];
        } else {
            // 审核未通过
            $returnData['award_status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED;
            $returnData['award_status_zh'] = ErpUserEventTaskAwardGoldLeafService::STATUS_DICT[ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED];
        }
        return $returnData;
    }

    /**
     * 过滤活动的分享任务列表数据 - 如果是特殊活动只返回第一个分享任务信息
     * 活动设置: 1.多次分享任务 2.奖励延时发放,但是手动提前发放奖励的活动ID,分享任务使用第一个任务
     * @param $activityId
     * @param $taskListData
     * @return array
     */
    private static function filterSpecialActivityTaskData($activityId, $taskListData)
    {
        //活动设置为1.多次分享任务 2.奖励延时发放,但是手动提前发放奖励的活动ID,分享任务使用第一个任务
        $specialDictActivityIds = explode(',', DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'activity_id_is_2005day'));
        if (in_array($activityId, $specialDictActivityIds)) {
            return [[$taskListData[0]], 1];
        }
        return [$taskListData, count($taskListData)];
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
        $sharePosterData = SharePosterModel::getSharePosterHistory(['student_id' => $studentId, 'activity_id' => $activityId], $page, $limit);
        // 格式化信息
        if (empty($sharePosterData['list'])) {
            return $sharePosterData;
        }
        $returnData['total_count'] = $sharePosterData['total_count'];
        // 获取活动分享任务列表
        $activityData = WeekActivityModel::getActivityAndTaskData($activityId)[0];
        // 组合数据
        $time = time();
        foreach ($sharePosterData['list'] as $item) {
            $tmpList = [];
            $tmpList['task_name'] = SharePosterService::formatWeekActivityName(array_merge($activityData, $item));
            $tmpList['create_time'] = date("Y.m.d H:i", $item['create_time']);
            $tmpList['verify_status'] = $item['verify_status'];
            $tmpList['id'] = $item['id'];
            $tmpList['task_num'] = $item['task_num'];
            $tmpList['can_upload'] = (int)self::checkWeekActivityAllowUpload($activityData, $time);
            $returnData['list'][] = $tmpList;
        }
        return $returnData;
    }
}

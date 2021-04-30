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
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssEventTaskModel;
use App\Models\Dss\DssReferralActivityModel;
use App\Models\Dss\DssSharePosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\SharePosterModel;
use App\Libs\Erp;
use App\Models\WeChatAwardCashDealModel;
use App\Services\Queue\QueueService;
use Medoo\Medoo;

class SharePosterService
{
    /**
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
            $imgSizeH = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'img_size_h');

            foreach ($posters as &$poster) {
                $poster['mobile']      = Util::hideUserMobile($poster['mobile']);
                $poster['img_url']     = AliOSS::signUrls($poster['image_path'], "", "", "", false, "", $imgSizeH);
                $poster['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS, $poster['poster_status']);
                $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
                $poster['check_time']  = !empty($poster['check_time']) ? date('Y-m-d H:i', $poster['check_time']) : '';

                $reasonStr = [];
                if (!empty($poster['verify_reason'])) {
                    $reason = explode(',', $poster['verify_reason']);
                    foreach ($reason as $reasonId) {
                        $reasonStr[] = DictService::getKeyValue(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON, $reasonId);
                    }
                }
                if (!empty($poster['remark'])) {
                    $reasonStr[] = $poster['remark'];
                }
                $poster['reason_str'] = implode('/', $reasonStr);
            }
        }

        return [$posters, $totalCount];
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
                    'type'          => SharePosterModel::TYPE_CHECKIN_UPLOAD,
                    'student_id'    => $poster['student_id'],
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
                    'award_id'      => $awardId,
                    'verify_time'   => $time,
                    'update_time'   => $time,
                    'verify_user'   => $employeeId,
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
            'award_id'      => $poster['award_id'],
            'verify_time'   => $time,
            'update_time'   => $time,
            'verify_user'   => $employeeId,
            'verify_reason' => implode(',', $reason),
            'remark'        => $remark,
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
     * 获取学生参加周周有奖活动的记录列表
     * @param $studentId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function joinRecordList($studentId, $page, $limit)
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
        $activityList = DssSharePosterModel::getRecords($queryWhere, ['activity_id', 'status', 'create_time', 'img_url', 'reason', 'remark', 'award_id']);
        if (empty($activityList)) {
            return $data;
        }
        $awardIds = array_filter(array_unique(array_column($activityList, 'award_id')));
        $awardInfo = $redPackDeal = [];
        if (!empty($awardIds)) {
            //奖励相关的状态
            $awardInfo = array_column(ErpUserEventTaskAwardModel::getRecords(['id'=>$awardIds], ['id','award_amount','award_type','status','reason']), null, 'id');
            //红包相关的发放状态
            $redPackDeal = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $awardIds]), null, 'user_event_task_award_id');
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
            $data['list'][$k]['award'] = !empty($awardInfo[$v['award_id']]) ? self::formatAwardInfo($awardInfo[$v['award_id']]['award_amount'], $awardInfo[$v['award_id']]['award_type']) : '';
            $data['list'][$k]['img_oss_url'] = $v['img_oss_url'];
            $data['list'][$k]['reason_str'] = $v['reason_str'];
            [$awardStatusZh, $failReasonZh] = !empty($v['award_id']) ? self::displayAwardExplain($awardInfo[$v['award_id']], $awardInfo[$v['award_id']], $redPackDeal[$v['award_id']] ?? null) : [];
            $data['list'][$k]['award_status_zh'] = $awardStatusZh;
            $data['list'][$k]['fail_reason_zh'] = $failReasonZh;
        }
        return $data;
    }

    public static function formatAwardInfo($amount, $type, $subType = '')
    {
        if ($type == 1) {
            //金钱单位：分
            return ($amount / 100) . '元';
        } elseif ($type == 2) {
            //时间单位：天
            return $amount . '天';
        } elseif ($type == 3 && $subType == ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF ) {
            // 积分
            return $amount . '金叶子';
        }
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
            $dv['img_oss_url'] = AliOSS::signUrls($dv['img_url']);
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
        $activityList = DssSharePosterModel::getRecords($queryWhere, ['id','activity_id', 'status', 'create_time', 'img_url', 'reason', 'remark', 'award_id', 'points_award_id']);
        if (empty($activityList)) {
            return $data;
        }
        // 获取老的红包奖励信息
        $awardIds = array_filter(array_unique(array_column($activityList, 'award_id')));
        $awardInfo = $redPackDeal = [];
        if (!empty($awardIds)) {
            //奖励相关的状态
            $awardInfo = array_column(ErpUserEventTaskAwardModel::getRecords(['id'=>$awardIds], ['id','award_amount','award_type','status','reason']), null, 'id');
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
            if ($v['points_award_id'] >0) {
                $_pointsAwardInfo = !empty($pointsAwardArr[$v['points_award_id']]) ? $pointsAwardArr[$v['points_award_id']] : [];
                // 积分奖励
                $_awardNum =  $_pointsAwardInfo['award_num'] ?? 0;
                $_awardType =  ErpEventTaskModel::AWARD_TYPE_INTEGRATION;
                $awardStatusZh = ErpReferralService::AWARD_STATUS[$_pointsAwardInfo['status']];
                $failReasonZh = "";
                $_awardTypeSubType = ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF;
            }else {
                // 老版现金奖励
                $_awardNum =  !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_amount'] : 0;
                $_awardType =  !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_type'] : 0;
                list($awardStatusZh, $failReasonZh) = !empty($v['award_id']) ? self::displayAwardExplain($awardInfo[$v['award_id']], $awardInfo[$v['award_id']], $redPackDeal[$v['award_id']] ?? null) : [];
                $_awardTypeSubType = '';
            }

            $data['list'][$k]['award'] = self::formatAwardInfo($_awardNum, $_awardType, $_awardTypeSubType);
            $data['list'][$k]['award_status_zh'] = $awardStatusZh;
            $data['list'][$k]['fail_reason_zh'] = $failReasonZh;
        }
        return $data;
    }
}

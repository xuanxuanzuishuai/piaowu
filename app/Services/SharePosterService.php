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
use App\Models\CountingActivityUserStatisticModel;
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
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeekActivityModel;
use App\Services\Queue\QueueService;
use I18N\Lang;

class SharePosterService
{
    public static $redisExpire = 432000; // 12小时

    const KEY_POSTER_VERIFY_LOCK = 'POSTER_VERIFY_LOCK';
    const KEY_POSTER_UPLOAD_LOCK = 'POSTER_UPLOAD_LOCK';

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
            }
        }

        return [$posters, $totalCount];
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
        $poster['reason_str']  = self::reasonToStr($poster['verify_reason'], $reasonDict);
        $poster['mobile'] = isset($poster['mobile']) ? Util::hideUserMobile($poster['mobile']) : '';
        $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
        $poster['check_time']  = Util::formatTimestamp($poster['check_time'], '');
        $poster['img_url']     = AliOSS::signUrls(
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
        $poster['award'] = self::formatAwardInfo($poster['award_amount'], $poster['award_type']);
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
                $_awardType =  ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF;
                $awardStatusZh = ErpReferralService::AWARD_STATUS[$_pointsAwardInfo['status']];
                $failReasonZh = "";
            }else {
                // 老版现金奖励
                $_awardNum =  !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_amount'] : 0;
                $_awardType =  !empty($awardInfo[$v['award_id']]) ? $awardInfo[$v['award_id']]['award_type'] : 0;
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

        // 上传并发处理，但不显示任何错误内容，用户无感
        $redis = RedisDB::getConn();
        $lockKey = self::KEY_POSTER_UPLOAD_LOCK . $params['student_id'] . $params['activity_id'];
        $lock = $redis->set($lockKey, $uploadRecord['id'] ?? 0, 'EX', 3, 'NX');
        if (empty($lock)) {
            throw new RunTimeException(['']);
        }

        //获取学生信息
        $studentDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        if ($studentDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
            throw new RunTimeException(['student_status_disable']);
        }
        //审核通过不允许上传截图
        $type = SharePosterModel::TYPE_WEEK_UPLOAD;
        $where = [
            'activity_id' => $activityId,
            'student_id'  => $studentId,
            'type'        => $type,
            'ORDER'       => ['id' => 'DESC']
        ];
        $field = ['id', 'verify_status', 'award_id'];
        $uploadRecord = SharePosterModel::getRecord($where, $field);
        if (!empty($uploadRecord['verify_status'])
            && $uploadRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        $time = time();
        $data = [
            'student_id'  => $studentId,
            'type'        => $type,
            'activity_id' => $activityId,
            'image_path'  => $imagePath,
            'create_time' => $time,
            'update_time' => $time,
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

        $divisionTime = DictConstants::get(DictConstants::NORMAL_UPLOAD_POSTER_DIVISION_TIME, 'division_time');
        $taskConfig = DictConstants::getSet(DictConstants::NORMAL_UPLOAD_POSTER_TASK);

        $updateData = [
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => time(),
            'verify_user'   => $params['employee_id'] ?? 0,
            'remark'        => $params['remark'] ?? '',
            'update_time'   => time(),
        ];
        $redis = RedisDB::getConn();
        $needAwardList = [];
        foreach ($posters as $key => $poster) {
            // 审核数据操作锁，解决并发导致的重复审核和发奖
            $lockKey = self::KEY_POSTER_VERIFY_LOCK . $poster['id'];
            $lock = $redis->set($lockKey, $poster['id'], 'EX', 120, 'NX');
            if (empty($lock)) {
                continue;
            }
            if (!empty($poster['award_id'])) {
                $needRejectAward[] = $poster['award_id'];
            }
            $where = [
                'id' => $poster['id'],
                'verify_status' => $poster['poster_status']
            ];
            $update = SharePosterModel::batchUpdateRecord($updateData, $where);

            if($update && $poster['type'] == SharePosterModel::TYPE_WEEK_UPLOAD){
                CountingActivitySignService::countSign($poster['student_id'], null, $poster['activity_id']);
            }

            //计算当前真正应该获得的奖励
            $where = [
                'id[!]'         => $poster['id'],
                'student_id'    => $poster['student_id'],
                'type'          => SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                'create_time[>=]' => $divisionTime,
            ];
            $count = SharePosterModel::getCount($where);
            if ($poster['create_time'] < $divisionTime) {
                $taskId = $taskConfig['-1'];
            } else {
                $taskId = $taskConfig[$count] ?? $taskConfig['-1'];
            }
            if (!empty($update) && empty($poster['points_award_ids'])) {
                $needAwardList[] = [
                    'id' => $poster['id'],
                    'uuid' => $poster['uuid'],
                    'task_id' => $taskId,
                    'activity_id' => $poster['activity_id']
                ];
            }
            //智能产品激活
            QueueService::autoActivate(['student_uuid' => $poster['uuid'], 'passed_time' => time(),'app_id' => Constants::SMART_APP_ID]);
        }
        if (!empty($needAwardList)) {
            QueueService::addUserPosterAward($needAwardList);
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
        foreach ($data as $poster) {
            $res = (new Erp())->addEventTaskAward($poster['uuid'], $poster['task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            if (empty($res['data'])) {
                SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$poster]);
            }
            $awardIds  = $res['data']['user_award_ids'] ?? [];
            $pointsIds = $res['data']['points_award_ids'] ?? [];
            $awardId   = implode(',', $awardIds);
            $pointsId  = implode(',', $pointsIds);
            SharePosterModel::updateRecord($poster['id'], ['award_id' => $awardId, 'points_award_id'=> $pointsId]);
            QueueService::sharePosterAwardMessage(['points_award_ids' => $pointsIds, 'activity_id' => $poster['activity_id'] ?? 0]);
        }
        return true;
    }

    /**
     * 截图审核-未通过
     * @param $id
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function refusedPoster($id, $params = [])
    {
        $type = $params['type'] ?? SharePosterModel::TYPE_WEEK_UPLOAD;
        $poster = SharePosterModel::getPostersByIds([$id], $type);
        $poster = $poster[0] ?? [];
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $status = SharePosterModel::VERIFY_STATUS_UNQUALIFIED;
        $time   = time();
        $update = SharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'verify_time'   => $time,
            'verify_user'   => $params['employee_id'],
            'verify_reason' => implode(',', $params['reason']),
            'update_time'   => $time,
            'remark'        => $params['remark'] ?? '',
        ]);
        if (!empty($poster['award_id'])) {
            (new Erp())->updateAward(
                explode(',', $poster['award_id']),
                ErpReferralService::AWARD_STATUS_REJECTED,
                $params['employee_id']
            );
        }
        // 审核不通过, 发送模版消息
        if ($update > 0) {
            $vars = [
                'activity_name' => $poster['activity_name'],
                'status' => $status
            ];
            MessageService::sendPosterVerifyMessage($poster['open_id'], $vars);
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
        $activities = WeekActivityModel::getSelectList([]);
        $allIds = array_column($activities, 'activity_id');
        if (!in_array($poster['activity_id'], $allIds)) {
            $poster['can_upload'] = Constants::STATUS_FALSE;
        }
        if ($poster['poster_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            $poster['can_upload'] = Constants::STATUS_FALSE;
        }
        $error = '';
        if (!empty($userInfo['user_id'])) {
            $userDetail = StudentService::dssStudentStatusCheck($userInfo['user_id'], false, null);
            if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
                $error = Lang::getWord('only_year_user_enter_event');
            }
        }
        $poster = self::formatOne($poster);
        $activity = ActivityService::getByTypeAndId(TemplatePosterModel::STANDARD_POSTER, $poster['activity_id']);
        return ['poster' => $poster, 'activity' => $activity, 'error' => $error];
    }

}

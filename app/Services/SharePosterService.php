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
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\SharePosterModel;
use App\Libs\Erp;
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
}

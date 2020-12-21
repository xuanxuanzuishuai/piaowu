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
use App\Libs\Util;
use App\Models\Dss\DssEventTaskModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\SharePosterModel;
use App\Libs\Erp;
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
                if (!empty($poster['reason'])) {
                    $reason = explode(',', $poster['reason']);
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
     * @return array
     * @throws RunTimeException
     */
    public static function approvedCheckin($posterIds, $employeeId)
    {
        if (count($posterIds) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        $posters = SharePosterModel::getPostersByIds($posterIds);
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $time = time();
        $status = SharePosterModel::VERIFY_STATUS_QUALIFIED;
        // 查询所有打卡活动下的任务：
        $allTasks = DssEventTaskModel::getRecords(
            [
                'event_id' => DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'collection_event_id'),
                'type' => DssEventTaskModel::STATUS_NORMAL,
            ]
        );
        // 已超时的海报
        $timeoutOnes = [];
        foreach ($posters as $poster) {
            if ($poster['valid_time'] < $time) {
                $timeoutOnes[] = $poster;
                continue;
            }

            $userInfo = UserService::getUserWeiXinInfoByUserId(
                Constants::SMART_APP_ID,
                $poster['student_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
            );
            // 发送审核消息
            PushMessageService::notifyUserCustomizeMessage(
                DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'verify_message_config_id'),
                [
                    'day'    => $poster['day'],
                    'status' => DictService::getKeyValue('share_poster_check_status', $status),
                    'url'    => DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'url'),
                    'remark' => '【点此消息】查看更多打卡进度',
                ],
                $userInfo['open_id'],
                Constants::SMART_APP_ID
            );
            $awardId = $poster['award_id'];
            if (empty($awardId)) {
                $taskId = 0;
                // 检查打卡次数，发红包
                $total = SharePosterModel::getCount([
                    'type'          => SharePosterModel::TYPE_CHECKIN_UPLOAD,
                    'student_id'    => $poster['student_id'],
                    'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED
                ]);
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
                    $awardId = implode(',', $taskRes['user_award_ids']);
                    $awardBaseInfo = ErpUserEventTaskAwardModel::awardRelateEvent($taskRes['user_award_ids']);
                    $needDealAward = [];
                    foreach ($awardBaseInfo as $award) {
                        $needDealAward[$award['award_id']] = ['id' => $award['award_id']];
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
        }
        return $timeoutOnes;
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
        if (empty($taskResult['data'])) {
            throw new RunTimeException(['erp_create_user_event_task_award_fail']);
        }
        return $taskResult['data'];
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
            throw new RunTimeException(['get_share_poster_error']);
        }

        $status = SharePosterModel::VERIFY_STATUS_UNQUALIFIED;
        $time = time();
        $update = SharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'award_id'      => $poster['award_id'],
            'verify_time'   => $time,
            'update_time'   => $time,
            'verify_user'   => $employeeId,
            'verify_reason' => implode(',', $reason),
            'remark'        => $remark,
            'ext'           => Medoo::raw("JSON_SET(`ext`, '$.valid_time', '".($poster['valid_time'] + 24 * 3600)."')")
        ]);
        // 审核不通过, 发送模版消息
        if ($update > 0) {
            $userInfo = UserService::getUserWeiXinInfoByUserId(
                Constants::SMART_APP_ID,
                $poster['student_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
            );
            PushMessageService::notifyUserCustomizeMessage(
                DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'verify_message_config_id'),
                [
                    'day'    => $poster['day'],
                    'status' => DictService::getKeyValue('share_poster_check_status', $status),
                    'url'    => DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'url'),
                    'remark' => '【点此消息】分享返学费活动主页重新上传',
                ],
                $userInfo['open_id'],
                Constants::SMART_APP_ID
            );
        }

        return $update > 0;
    }

}

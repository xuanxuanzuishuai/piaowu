<?php
/**
 * 后台管理
 */

namespace App\Services\MorningReferral;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MorningDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Morning\MorningStudentModel;
use App\Models\MorningSharePosterModel;
use App\Models\MorningTaskAwardModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Services\DictService;
use App\Services\Queue\MorningReferralTopic;
use App\Services\Queue\QueueService;
use App\Services\SharePosterService;

class MorningClockActivityManageService
{
    const KEY_POSTER_VERIFY_LOCK = 'morning_verify_poster_approved_lock_';

    /**
     * 获取截图审核列表
     * @param $params
     * @return array
     */
    public static function getSharePosterList($params)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        $where = [];
        list($page, $count) = Util::formatPageCount($params);
        // 如果传入的uuid不为空和传入的uuid取交集，交集为空认为不会有数据， 不为空直接用交集作为条件
        $searchUUID = [];
        if (!empty($params['student_uuid'])) $searchUUID[] = $params['student_uuid'];
        // 如果存在学员手机号用手机号换取uuid
        if (!empty($params['student_mobile'])) {
            $mobileInfo = ErpStudentModel::getUserInfoByMobiles([$params['student_mobile']], Constants::QC_APP_ID);
            // 手机号不存在，则结果必然是空
            if (empty($mobileInfo)) {
                return $returnData;
            }
            $_mobileUUIDS = [$mobileInfo['uuid']];
            $searchUUID = empty($searchUUID) ? $_mobileUUIDS : array_intersect($searchUUID, [$_mobileUUIDS]);
        }
        // 如果存在学员名称用名称换取uuid
        if (!empty($params['student_name'])) {
            $studentNameUUID = MorningStudentModel::getRecords(['name[~]' => $params['student_name']], ['uuid']);
            $_nameUUIDS = array_column($studentNameUUID, 'uuid');
            $searchUUID = empty($searchUUID) ? $_nameUUIDS : array_intersect($searchUUID, $_nameUUIDS);
        }
        $searchUUID = array_unique(array_diff($searchUUID, ['']));
        if (!empty($params['student_mobile']) || !empty($params['student_name']) || !empty($params['student_uuid'])) {
            if (empty($searchUUID)) return $returnData;
        }
        $where['student_uuid'] = $searchUUID;
        !empty($params['create_time_start']) && $where['create_time_start'] = strtotime($params['create_time_start']);
        !empty($params['create_time_end']) && $where['create_time_end'] = strtotime($params['create_time_end']);
        !empty($params['task_num']) && $where['task_num'] = $params['task_num'];
        !empty($params['verify_status']) && $where['verify_status'] = $params['verify_status'];

        // 获取总数和列表
        list($returnData['total_count'], $returnData['list']) = MorningSharePosterModel::searchList($where, $page, $count);
        if (!empty($returnData['list'])) {
            $uuids = array_column($returnData['list'], 'student_uuid');
            // 获取学生手机号
            $uuidMobiles = ErpStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'mobile']);
            $uuidMobiles = !empty($uuidMobiles) ? array_column($uuidMobiles, null, 'uuid') : [];
            // 获取学生名称
            $uuidNames = MorningStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'name']);
            $uuidNames = !empty($uuidNames) ? array_column($uuidNames, null, 'uuid') : [];
            foreach ($returnData['list'] as &$item) {
                $item = self::formatSharePosterInfo(
                    $item,
                    [
                        'name'   => $uuidNames[$item['student_uuid']]['name'] ?? '',
                        'mobile' => $uuidMobiles[$item['student_uuid']]['mobile'] ?? '',
                    ]
                );
            }
        }
        return $returnData;
    }

    /**
     * 格式化信息
     * @param $info
     * @param array $studentInfo
     * @return mixed
     */
    public static function formatSharePosterInfo($info, $studentInfo = [])
    {
        $info['format_create_time'] = date("Y-m-d H:i:s", $info['create_time']);
        $info['format_verify_time'] = !empty($info['verify_time']) ? date("Y-m-d H:i:s", $info['verify_time']) : '';
        $info['status_zh'] = MorningDictConstants::get(MorningDictConstants::SHARE_POSTER_CHECK_STATUS, $info['verify_status']);
        if (isset($info['verify_user_name']) && empty($info['verify_user_name'])) {
            $info['verify_user_name'] = '';
        }
        $info['img_url'] = AliOSS::replaceCdnDomainForDss($info['image_path']);
        $info['mobile'] = !empty($studentInfo['mobile']) ? Util::hideUserMobile($studentInfo['mobile']) : '';
        $info['status_name'] = $studentInfo['name'] ?? '';
        $info['format_verify_reason'] = SharePosterService::reasonToStr($info['verify_reason']);
        !empty($info['remark']) && $info['format_verify_reason'][] = $info['remark'];

        $clockInNode = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_clock_in_node'), true);
        $info['format_task_num'] = $clockInNode[$info['task_num']]['name'];
        return $info;
    }

    /**
     * 审核拒绝
     * @param     $params
     * @param int $status
     * @return bool
     * @throws RunTimeException
     */
    public static function sharePosterRefused($params, $status = SharePosterModel::VERIFY_STATUS_UNQUALIFIED)
    {
        $posterId = $params['poster_id'] ?? 0;
        if (empty($posterId)) {
            throw new RunTimeException(['poster_id_is_required']);
        }
        $reason = $params['reason'] ?? [];
        $remark = $params['remark'] ?? '';
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }
        $poster = MorningSharePosterModel::getRecord([
            'id'            => $posterId,
            'activity_type' => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
        ]);
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $time = time();
        $update = MorningSharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'verify_time'   => $time,
            'verify_user'   => $params['employee_id'],
            'verify_reason' => implode(',', $reason),
            'update_time'   => $time,
            'remark'        => $remark,
        ]);
        return $update > 0;
    }

    /**
     * 审核通过
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function sharePosterApproved($params)
    {
        $posterIds = $params['poster_ids'] ?? 0;
        if (empty($posterIds)) {
            throw new RunTimeException(['poster_id_is_required']);
        }
        $posters = MorningSharePosterModel::getRecords([
            'id'            => $posterIds,
            'activity_type' => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
            'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT,
        ]);
        if (empty($posters)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $needSendAwardData = [];
        $now = time();
        $updateData = [
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => $now,
            'verify_user'   => $params['employee_id'] ?? 0,
            'remark'        => $params['remark'] ?? '',
            'update_time'   => $now,
        ];
        //处理数据
        foreach ($posters as $key => $poster) {
            // 加锁， 按照学生为维度，同一时间只能处理一个学生，防止奖励计算错误
            $lockKey = self::KEY_POSTER_VERIFY_LOCK . $poster['student_uuid'];
            try {
                // 加锁失败重试3次依然失败跳过
                if (!Util::setLock($lockKey, 60, 3)) {
                    continue;
                }
                // 更新审核结果
                $where = [
                    'id'            => $poster['id'],
                    'verify_status' => $poster['verify_status'],
                ];
                $update = MorningSharePosterModel::batchUpdateRecord($updateData, $where);
                // 更新失败，不处理
                if (empty($update)) {
                    SimpleLogger::info("sharePosterApproved_update_status_fail", [$updateData, $where]);
                    continue;
                }
                // 计算奖励
                $currentAward = self::computeStudentClockActivityAward($poster['student_uuid']);
                if (empty($currentAward)) {
                    SimpleLogger::info("sharePosterApproved_not_award_node", [$poster['student_uuid']]);
                    continue;
                }
                // 生成待发放奖励
                $taskAwardData = [
                    'student_uuid'  => $poster['student_uuid'],
                    'activity_type' => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
                    'status'        => OperationActivityModel::SEND_AWARD_STATUS_WAITING,
                    'award_type'    => ErpEventTaskModel::AWARD_TYPE_CASH,
                    'award_amount'  => Util::fen($currentAward['award_amount']),
                    'task_num'      => $currentAward['task_num'],
                    'award_node'    => $currentAward['node'],
                    'award_from'    => $poster['id'],
                    'create_time'   => $now,
                    'operator_id'   => $params['employee_id'],
                    'operate_time'  => $now,
                ];
                $taskAwardId = MorningTaskAwardModel::insertRecord($taskAwardData);
                if (empty($taskAwardId)) {
                    SimpleLogger::info("sharePosterApproved_save_award_node", $taskAwardData);
                    continue;
                }
                // 组装投递消息数组
                $needSendAwardData[] = [
                    'student_uuid'           => $poster['student_uuid'],
                    'share_poster_record_id' => $poster['id'],
                    'task_award_id'          => $taskAwardId,
                ];
            } finally {
                Util::unLock($lockKey);
            }
        }
        // 批量投递消费消费队列
        foreach ($needSendAwardData as $_sendData) {
            QueueService::morningPushMsg(MorningReferralTopic::EVENT_CLOCK_ACTIVITY_SEND_RED_PACK, $_sendData, rand(0, 5));
        }
        return true;
    }

    /**
     * 计算学生5日打卡活动奖励
     * @param $studentUuid
     * @return array|int[]
     */
    public static function computeStudentClockActivityAward($studentUuid)
    {
        $returnData = [
            'task_num'     => 0,
            'award_amount' => 0,
        ];
        // 获取奖励节点
        $awardNode = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_award_node'), true);
        if (empty($awardNode)) {
            return [];
        }
        // 获取奖励记录
        $awardList = MorningTaskAwardModel::getStudentFiveDayAwardList($studentUuid);
        $countAwardList = count($awardList);
        if ($countAwardList == count($awardNode)) {
            return [];
        }
        $awardTaskNode = array_column($awardNode, null, 'day');
        // 获取参与记录
        $recordCount = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid, [SharePosterModel::VERIFY_STATUS_QUALIFIED]);
        $currentAwardNode = $awardTaskNode[count($recordCount) + 1] ?? [];
        if (empty($currentAwardNode)) {
            return [];
        }
        $returnData['task_num'] = $currentAwardNode['day'];
        $returnData['award_amount'] = $currentAwardNode['award_num'];
        return $returnData;
    }

    /**
     * 获取清晨截图审核和红包审核列表的下拉选项
     * @param array $dict
     * @return array
     */
    public static function dropDown(array $dict)
    {
        $list = [];
        if (empty($dict)) {
            return $list;
        }
        foreach ($dict as $_type => $_keyCodeStr) {
            $_keyCodeList = explode(',', $_keyCodeStr);
            foreach ($_keyCodeList as $_keyCode) {
                $_data = DictService::getKeyValue($_type, $_keyCode) ?? '';
                $list[$_type][$_keyCode] = array_column(json_decode($_data, true), 'name', 'node');
            }
        }
        return $list;
    }
}
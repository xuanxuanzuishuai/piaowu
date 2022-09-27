<?php
/**
 * 后台管理
 */

namespace App\Services\MorningReferral;

use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MorningDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\Morning\MorningStudentModel;
use App\Models\MorningSharePosterModel;
use App\Models\MorningTaskAwardModel;
use App\Models\SharePosterModel;
use App\Services\SharePosterService;

class MorningClockActivityManageService
{
    const KEY_POSTER_VERIFY_LOCK = 'morning_verify_poster_approved_lock';

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
            $mobileInfo = ErpStudentModel::getRealUserInfoByMobile([$params['student_mobile']]);
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
        ]);
        if (empty($posters)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $now = time();
        $updateData = [
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => $now,
            'verify_user'   => $params['employee_id'] ?? 0,
            'remark'        => $params['remark'] ?? '',
            'update_time'   => $now,
        ];
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
                'id'            => $poster['id'],
                'verify_status' => $poster['poster_status'],
            ];
            $update = MorningSharePosterModel::batchUpdateRecord($updateData, $where);
            // 更新失败，不处理
            if (empty($update)) {
                SimpleLogger::info("sharePosterApproved_update_status_fail", [$updateData, $where]);
                continue;
            }
            // TODO qingfeng.lian  这里如果审核通过后给用户发放红包 //批量投递消费消费队列
            // 审核通过后 - 组装发放奖励的消息
        }
        // TODO qingfeng.lian  这里如果审核通过后给用户发放红包 //批量投递消费消费队列
        // QueueService::addRealUserPosterAward($sendAwardQueueData);
        // QueueService::realSendPosterAwardMessage($sendWxMessageQueueData);
        return true;
    }
}
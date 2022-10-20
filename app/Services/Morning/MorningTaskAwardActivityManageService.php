<?php
/**
 * 清晨任务奖励管理
 * author: qingfeng.lian
 * date: 2022/10/14
 */

namespace App\Services\Morning;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MorningDictConstants;
use App\Libs\Util;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Morning\MorningStudentModel;
use App\Models\Morning\MorningUserWechatModel;
use App\Models\MorningTaskAwardModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WechatOpenidListModel;
use App\Services\Queue\MorningReferralTopic;
use App\Services\Queue\QueueService;

class MorningTaskAwardActivityManageService
{

    /**
     * 更新红包发放进度
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function redPackUpdateStatus($params)
    {
        // $taskAwardId = $params['task_award_id'] ?? 0;
        $operationId = $params['employee_id'] ?? 0;
        $recordIds = $params['ids'] ?? [];
        $remark = $params['remark'] ?? '';
        $status = $params['status'] ?? MorningTaskAwardModel::STATUS_DISABLED;
        $activityType = $params['activity_type'] ?? MorningTaskAwardModel::MORNING_ACTIVITY_TYPE;
        if (empty($recordIds) || empty($operationId)) {
            throw new RunTimeException(['invalid_award_id_or_reviewer_id']);
        }
        if (count($recordIds) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        // 查询记录
        $awardRecordList = MorningTaskAwardModel::getRecords([
            'id'            => $recordIds,
            'activity_type' => $activityType,
            'award_type'    => ErpEventTaskModel::AWARD_TYPE_CASH,
        ]);
        if (empty($awardRecordList)) {
            return true;
        }

        foreach ($awardRecordList as $item) {
            if (in_array($item['status'], [MorningTaskAwardModel::STATUS_GIVE, MorningTaskAwardModel::STATUS_GIVE_ING])) {
                // 发放成功或发放中待领取的不能更新操作状态
                continue;
            }
            if ($status == MorningTaskAwardModel::STATUS_GIVE_ING) {
                $_sendData = [
                    'student_uuid'           => $item['student_uuid'],
                    'task_award_id'          => $item['id'],
                    'share_poster_record_id' => $item['award_from'],
                ];
                // 更新为待发放
                MorningTaskAwardModel::updateStatusIsGiving($item['id'], $remark, $operationId);
                QueueService::morningPushMsg(MorningReferralTopic::EVENT_CLOCK_ACTIVITY_SEND_RED_PACK, $_sendData, rand(0, 5));
            } else {
                // 更新为不发放
                MorningTaskAwardModel::updateStatusIsDisabled($item['id'], $remark, $operationId);
            }
        }
        return true;
    }

    /**
     * 红包审核列表
     * @param $params
     * @return array
     */
    public static function redPackList($params)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        $where = [
            'award_type' => ErpUserEventTaskAwardModel::AWARD_TYPE_CASH,
        ];
        list($page, $count) = Util::formatPageCount($params);
        // 如果传入的uuid不为空和传入的uuid取交集，交集为空认为不会有数据， 不为空直接用交集作为条件
        $searchUUID = [];
        if (!empty($params['student_uuid'])) $searchUUID[] = $params['student_uuid'];
        // 如果存在学员手机号用手机号换取uuid
        if (!empty($params['student_mobile'])) {
            $mobileInfo = ErpStudentModel::getUserInfoByMobiles([$params['student_mobile']], Constants::QC_APP_ID);
            // 手机号不存在，则结果必然为空
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
        !empty($params['create_time_start']) && $where['create_time_start'] = $params['create_time_start'];
        !empty($params['create_time_end']) && $where['create_time_end'] = $params['create_time_end'];
        !empty($params['status']) && $where['status'] = $params['status'];
        !empty($params['operator_name']) && $where['operator_name'] = $params['operator_name'];
        !empty($params['operate_time_start']) && $where['operate_time_start'] = $params['operate_time_start'];
        !empty($params['operate_time_end']) && $where['operate_time_end'] = $params['operate_time_end'];
        // 获取总数和列表
        list($returnData['total_count'], $returnData['list']) = MorningTaskAwardModel::searchList($where, $page, $count);
        $returnData['list'] = self::formatTaskAwardInfo($returnData['list']);
        return $returnData;
    }

    /**
     * 格式化信息
     * @param $list
     * @return array
     */
    public static function formatTaskAwardInfo($list)
    {
        if (empty($list)) {
            return [];
        }
        $uuids = array_column($list, 'student_uuid');
        // 获取学生手机号
        $uuidMobiles = ErpStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'mobile']);
        $uuidMobiles = !empty($uuidMobiles) ? array_column($uuidMobiles, null, 'uuid') : [];
        // 获取学生名称
        $uuidNames = MorningStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'name']);
        $uuidNames = !empty($uuidNames) ? array_column($uuidNames, null, 'uuid') : [];
        // 是否绑定微信
        $uuidOpenIds = MorningUserWechatModel::getMorningStudentWechatOpenids($uuids, ['user_uuid', 'open_id']);
        $uuidOpenIds = !empty($uuidOpenIds) ? array_column($uuidOpenIds, null, 'user_uuid') : [];
        $penIds = !empty($uuidOpenIds) ? array_column($uuidOpenIds, 'open_id') : [];
        // 是否关注微信
        $openIdSubList = WechatOpenidListModel::getSubList($penIds, Constants::QC_APP_ID);
        $openIdSubList = !empty($openIdSubList) ? array_column($openIdSubList, null, 'openid') : [];
        // 奖励节点
        $awardNode = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_award_node'), true);
        $awardNode = array_column($awardNode, null, 'node');
        foreach ($list as &$item) {
            // 补全学生信息
            $mobile = $uuidMobiles[$item['student_uuid']]['mobile'] ?? '';
            $item['mobile'] = !empty($mobile) ? Util::hideUserMobile($mobile) : '';
            $item['student_name'] = $uuidNames[$item['student_uuid']]['name'] ?? '';
            // 补全微信信息 - 是否绑定微信
            $item['bind_wechat_status'] = DssUserWeiXinModel::STATUS_DISABLE;
            $openid = $uuidOpenIds[$item['student_uuid']]['open_id'] ?? '';
            !empty($openid) && $item['bind_wechat_status'] = DssUserWeiXinModel::STATUS_NORMAL;
            $item['bind_wechat_status_zh'] = $item['bind_wechat_status'] == DssUserWeiXinModel::STATUS_NORMAL ? '已绑定' : '未绑定';
            // 补全微信信息 - 是否关注微信
            $item['subscribe_status'] = isset($openIdSubList[$openid]) ? WechatOpenidListModel::SUBSCRIBE_WE_CHAT : WechatOpenidListModel::UNSUBSCRIBE_WE_CHAT;
            $item['subscribe_status_zh'] = $item['subscribe_status'] == WechatOpenidListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注';
            // 补全发放信息 - 发放状态
            $item['status_zh'] = ErpUserEventTaskAwardModel::STATUS_DICT[$item['status']] ?? '';
            // 补全发放信息 - 发放奖励节点
            $item['award_node_zh'] = $awardNode[$item['award_node']]['name'] ?? '';
            // 补全发放信息 - 发放金额
            $item['award_amount'] = Util::yuan($item['award_amount']);
            // 补全发放信息 - 发放微信返回结果
            $item['result_codes_zh'] = WeChatAwardCashDealModel::batchGetWeChatErrorMsg($item['result_codes']);
            // 操作时间
            $item['format_operate_time'] = date("Y-m-d H:i:s", $item['operate_time']);
            // 创建时间
            $item['format_create_time'] = date("Y-m-d H:i:s", $item['create_time']);
        }
        return $list;
    }
}
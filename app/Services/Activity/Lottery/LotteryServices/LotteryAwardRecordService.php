<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\CountingActivityAwardModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardRecordModel;
use App\Services\LogisticsService\ExpressDetailService;

class LotteryAwardRecordService
{
    /**
     * 获取最近一段时间中奖活动的列表
     * @param $opActivityId
     * @return array
     */
    public static function getHitAwardByTime($opActivityId)
    {
        $endTime = time();
        $startTime = $endTime - Util::TIMESTAMP_ONEDAY;
        $activityInfo = LotteryAwardRecordModel::getHitAwardByTime($opActivityId, $startTime, $endTime);
        if (!empty($activityInfo)) {
            //处理手机号

            //处理中奖时间
        }

        return $activityInfo ?: [];
    }

    /**
     * 获取用户在指定活动的抽奖次数
     * @param $opActivityId
     * @param $uuid
     * @return int|number
     */
    public static function useLotteryTimes($opActivityId, $uuid)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'uuid'           => $uuid,
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

    /**
     * 活动参与记录搜索
     * @param array $searchParams
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function search(array $searchParams, int $page = 1, int $pageSize = 20): array
    {
        $recordsData = [
            'total' => 0,
            'list'  => [],
        ];
        //获取活动数据
        $activityData = LotteryActivityModel::getRecord(['op_activity_id' => $searchParams['op_activity_id']],
            ['app_id']);
        if (empty($activityData)) {
            return $recordsData;
        }
        //查询条件
        //主表
        if (isset($searchParams['id'])) {
            $where['ar.id'] = $searchParams['id'];
        } else {
            $where = ["ar.id[>=]" => 1,];
        }
        if (isset($searchParams['uuid'])) {
            $where['ar.uuid'][] = trim($searchParams['uuid']);
        }
        if (isset($searchParams['op_activity_id'])) {
            $where['ar.op_activity_id'] = $searchParams['op_activity_id'];
        }
        if (isset($searchParams['create_time_min'])) {
            $where['ar.create_time[>=]'] = (int)$searchParams['create_time_min'];
        }
        if (isset($searchParams['create_time_max'])) {
            $where['ar.create_time[>=]'] = (int)$searchParams['create_time_min'];
        }
        if (isset($searchParams['shipping_status'])) {
            $where['ar.shipping_status'] = (int)$searchParams['shipping_status'];
        }
        //学生
        if (!empty($searchParams['mobile'])) {
            $studentUuid = ErpStudentModel::getRecord(['mobile' => (int)$searchParams['mobile']], ['uuid']);
            if (empty($studentUuid)) {
                return $recordsData;
            }
            $where['ar.uuid'][] = $studentUuid['uuid'];
        }
        //奖品表
        if (!empty($searchParams['award_level'])) {
            $where['ai.level'] = (int)$searchParams['award_level'];
        }
        if (!empty($searchParams['award_type'])) {
            $where['ai.type'] = (int)$searchParams['award_type'];
        }
        return LotteryAwardRecordModel::search($where, [
            'ar.id',
            'ar.create_time',
            'ar.address_detail',
            'ar.erp_address_id',
            'ar.shipping_status',
            'ar.logistics_company',
            'ar.express_number',
            'ar.unique_id',
            'ar.award_type',
            'ar.uuid',
            'ai.level',
            'ai.name',
            'ai.type',
        ], $page, $pageSize);
    }

    /**
     * 修改发货状态
     * @param $id
     * @param $awardType
     * @param $updateData
     * @return bool
     */
    public static function updateAwardShippingStatus($id, $awardType, $updateData): bool
    {
        //目前实物支持取消发货/禁止重复取消
        if ($awardType != Constants::AWARD_TYPE_TYPE_ENTITY) {
            return false;
        }
        $res = LotteryAwardRecordModel::updateRecord($id, $updateData);
        return !empty($res);
    }

    /**
     * 物流详情
     * @param $opActivityId
     * @param $uniqueId
     * @param $operatorType
     * @param string $studentUuid
     * @return array
     */
    public static function expressDetail($opActivityId, $uniqueId, $operatorType, string $studentUuid = ''): array
    {
        if ($operatorType == Constants::OPERATOR_TYPE_CLIENT) {
            $where['uuid'] = $studentUuid;
        }
        $where = [
            'op_activity_id' => $opActivityId,
            'unique_id'      => $uniqueId,
            'award_type'     => Constants::AWARD_TYPE_TYPE_ENTITY,
        ];
        //奖励记录数据
        $awardRecordData = LotteryAwardRecordModel::getRecord($where,
            ['id', 'logistics_status', 'address_detail', 'create_time', 'unique_id']);
        if (empty($awardRecordData)) {
            return [];
        }
        $erpExpressDetail = ExpressDetailService::getExpressDetails($awardRecordData);
        //更新物流信息
        self::updateAwardLogisticsData($awardRecordData, $erpExpressDetail);
        return $erpExpressDetail;
    }

    /**
     * 修改物流数据
     * @param $awardRecordData
     * @param $erpExpressDetail
     * @return bool
     */
    private static function updateAwardLogisticsData($awardRecordData, $erpExpressDetail): bool
    {
        if (empty($erpExpressDetail['logistics_no'])) {
            return false;
        }
        // 已收货，无需修改
        if ($awardRecordData['logistics_status'] == Constants::LOGISTICS_STATUS_SIGN) {
            return true;
        }
        $logisticsStatus = ExpressDetailService::formatLogisticsStatus($erpExpressDetail['express_record'][0]['node']);
        if (empty($logisticsStatus)) {
            return false;
        }
        //无需修改
        if ($awardRecordData['logistics_status'] == $logisticsStatus) {
            return false;
        }
        $res = LotteryAwardRecordModel::batchUpdateRecord(
            [
                'logistics_status'  => $logisticsStatus,
                'shipping_status'   => $erpExpressDetail['shipping_status'],
                'express_number'    => $erpExpressDetail['logistics_no'],
                'logistics_company' => $erpExpressDetail['company'],
            ],
            [
                'id' => $awardRecordData['id'],
            ]);
        return !empty($res);
    }

    /**
     * 修改收货地址:实物&待发货条件才可以修改
     * @param $id
     * @param $addressDetail
     * @return bool
     * @throws RunTimeException
     */
    public static function updateAwardShippingAddress($id, $addressDetail): bool
    {
        $recordData = LotteryAwardRecordModel::getRecord(['id' => $id], ['award_type', 'shipping_status']);
        if ($recordData['award_type'] != Constants::AWARD_TYPE_TYPE_ENTITY) {
            throw new RuntimeException(["not_entity_award_stop_update_shipping_address"]);
        }
        if ($recordData['shipping_status'] != Constants::SHIPPING_STATUS_BEFORE) {
            throw new RuntimeException(["not_waiting_send_stop_update_shipping_address"]);
        }
        //请求erp新增地址
        $erp = new Erp();
        $result = $erp->modifyStudentAddress($addressDetail);
        if (empty($result) || $result['code'] != 0 || empty($result['data']['address_id'])) {
            throw new RuntimeException([$result['errors'][0]['err_no']]);
        }
        $addressDetail['id'] = $result['data']['address_id'];
        $res = LotteryAwardRecordModel::batchUpdateRecord([
            'erp_address_id' => $result['data']['address_id'],
            'address_detail' => json_encode($addressDetail, true),
        ], ['id' => $id, 'shipping_status' => Constants::SHIPPING_STATUS_BEFORE]);
        return !empty($res);
    }

}
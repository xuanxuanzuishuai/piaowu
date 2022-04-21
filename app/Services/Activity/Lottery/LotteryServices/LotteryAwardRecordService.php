<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CountingActivityAwardModel;
use App\Models\LotteryAwardRecordModel;
use App\Services\ErpService\ErpGoodsV1Service;
use App\Services\UniqueIdGeneratorService\DeliverIdGeneratorService;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Erp\ErpStudentModel;
use App\Models\LotteryActivityModel;
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
        $hitAwardInfo = LotteryAwardRecordModel::getHitAwardByTime($opActivityId, $startTime, $endTime);
        if (empty($hitAwardInfo)){
            return [];
        }

        $uuidList = array_unique(array_column($hitAwardInfo, 'uuid')) ?? [];
        $uuidInfo = ErpStudentModel::getRecords(['uuid' => $uuidList], ['uuid', 'mobile']);
        $mobileKeyUuid = array_column($uuidInfo, 'mobile', 'uuid');
        foreach ($hitAwardInfo as $value) {
            $single['mobile'] = Util::hideUserMobile($mobileKeyUuid[$value['uuid']]);
            $single['award_name'] = $value['name'];
            $single['hit_time'] = self::formatTime($endTime, $value['create_time']);
            $res[] = $single;
        }

        return $res ?? [];
    }

    /**
     * 格式化时间
     * @param $time
     * @param $createTime
     * @return false|string
     */
    public static function formatTime($time,$createTime)
    {
        $diff = $time - $createTime;
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3540) {
            return (bcdiv($diff, 60) ?: 1) . " 分钟前";
        } elseif ($diff < 86400) {
            return (bcdiv($diff, 3600) ?: 1) . ' 小时前';
        } elseif ($diff > 86400) {
            return date('m-d', $createTime);
        }
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
     * 获取用户当天使用抽奖的次数
     * @param $opActivityId
     * @param $uuid
     * @return int|number
     */
    public static function getUserDayHitNum($opActivityId, $uuid)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'uuid'           => $uuid,
            'create_time[>]' => strtotime(date('Y-m-d')),
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

    /**
     * 中奖记录入表
     * @param $params
     * @param $hitInfo
     * @return int|mixed|string|null
     */
    public static function addAwardRecord($params, $hitInfo)
    {
        if ($hitInfo['type'] == Constants::AWARD_TYPE_TYPE_ENTITY) {
            $uniqueId = (new DeliverIdGeneratorService())->getDeliverId();
            $shippingStatus = Constants::SHIPPING_STATUS_BEFORE;
        } else {
            $shippingStatus = Constants::SHIPPING_STATUS_DELIVERED;
        }
        $data = [
            'op_activity_id'  => $params['op_activity_id'],
            'uuid'            => $params['uuid'],
            'use_type'        => $params['use_type'],
            'award_id'        => $hitInfo['id'],
            'award_type'      => $hitInfo['type'],
            'unique_id'       => $uniqueId ?? 0,
            'shipping_status' => $shippingStatus,
            'create_time'     => time(),
        ];
        return LotteryAwardRecordModel::insertRecord($data);
    }

    /**
     * 更新中奖后活动表的相关数据
     * @param $params
     * @param $hitInfo
     * @return bool
     */
    public static function updateHitAwardInfo($params, $hitInfo)
    {
        $fields = [];
        if ($hitInfo['num'] > 0) {
            $fields[] = 'rest_award_num';
        }

        if ($hitInfo['type'] != Constants::AWARD_TYPE_EMPTY) {
            $fields[] = 'hit_times';
        }

        $times = self::useLotteryTimes($params['op_activity_id'], $params['uuid']);
        if ($times == 0) {
            $fields[] = 'join_num';
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        try {
            $recordId = self::addAwardRecord($params, $hitInfo);
            LotteryActivityService::updateAfterHitInfo($params['op_activity_id'], $fields);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
        }
        return $recordId ?? 0;
    }

    /**
     * 获取指定用户的中奖记录
     * @param $uuid
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getHitRecord($uuid, $page, $pageSize)
    {
        $data = LotteryAwardRecordModel::getHitRecord($uuid, $page, $pageSize);
        if (empty($data)) {
            return $data;
        }

        foreach ($data['list'] as $key => $value) {
            if (!empty($value['img_url'])) {
                $data['list'][$key]['img_url'] = AliOSS::replaceCdnDomainForDss($value['img_url']);
            }
        }
        return $data;
    }

    /**
     * 更新收货地址
     * @param $params
     * @return int|null
     */
    public static function modifyAddress($params)
    {
        $update = [
            'erp_address_id' => $params['erp_address_id'],
            'address_detail' => json_encode($params['address_detail'])
        ];
        return LotteryAwardRecordModel::updateRecord($params['record_id'], $update);
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
            ['app_id', 'name']);
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
        $recordData = LotteryAwardRecordModel::search($where, [
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
        $recordData['activity_name'] = $activityData['name'];
        return $recordData;
    }

    /**
     * 取消发货
     * @param $ids
     * @param $opActivityId
     * @param $employeeUuid
     * @return bool
     */
    public static function cancelDeliver($ids, $opActivityId, $employeeUuid): bool
    {
        $recordList = LotteryAwardRecordModel::getRecords(['id' => $ids, 'op_activity_id' => $opActivityId],
            ['shipping_status', 'award_type']);
        if (empty($recordList)) {
            return false;
        }
        //目前实物支持取消发货/禁止重复取消
        $updateData = [];
        foreach ($recordList as $rv) {
            if ($rv['shipping_status'] == Constants::SHIPPING_STATUS_CANCEL || $rv['award_type'] != Constants::AWARD_TYPE_TYPE_ENTITY) {
                continue;
            }
            $updateData = [
                'shipping_status'      => Constants::SHIPPING_STATUS_CANCEL,
                'cancel_shipping_time' => time(),
                'cancel_shipping_uuid' => $employeeUuid,
            ];
        }
        if (empty($updateData)) {
            return true;
        }
        $res = LotteryAwardRecordModel::batchUpdateRecord($updateData, ['id' => $ids]);
        return !empty($res);
    }

    /**
     * 物流详情
     * @param $opActivityId
     * @param $uniqueId
     * @return array
     */
    public static function expressDetail($opActivityId, $uniqueId): array
    {
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
        SimpleLogger::info('lottery award record data', $awardRecordData);
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

    /**
     * 获取未签收的实物获奖记录
     * @return array
     */
    public static function getUnreceivedAwardRecord(): array
    {
        return LotteryAwardRecordModel::getRecords([
            'create_time[>=]'     => strtotime('-1 month'),
            'create_time[<=]'     => strtotime('-24 hour'),
            'award_type'          => Constants::AWARD_TYPE_TYPE_ENTITY,
            'logistics_status[<]' => CountingActivityAwardModel::LOGISTICS_STATUS_SIGN,
        ], ['unique_id']);
    }

    /**
     * 同步实物发货的物流信息
     * @param $uniqueId
     * @return array|bool
     */
    public static function lotterySyncAwardLogistics($uniqueId)
    {
        //奖励记录数据
        $awardRecordData = LotteryAwardRecordModel::getRecord(['unique_id' => $uniqueId],
            ['id', 'logistics_status', 'address_detail', 'create_time', 'unique_id']);
        if (empty($awardRecordData)) {
            return [];
        }
        $erpExpressDetail = ExpressDetailService::getExpressDetails($awardRecordData);
        //更新物流信息
        return self::updateAwardLogisticsData($awardRecordData, $erpExpressDetail);
    }


    /**
     * 获取未发货的实物获奖记录:领奖时间在当前时间24小时之前
     * @return array
     */
    public static function getUnshippedAwardRecord(): array
    {
        return LotteryAwardRecordModel::getUnshippedAwardRecord(strtotime('-24 hour'),
            Constants::SHIPPING_STATUS_BEFORE, Constants::AWARD_TYPE_TYPE_ENTITY);

    }
}
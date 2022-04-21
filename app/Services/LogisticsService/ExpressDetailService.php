<?php

namespace App\Services\LogisticsService;

use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CountingActivityAwardModel;

class ExpressDetailService
{
    /**
     * 获取实物发货单的物流信息
     * @param $awardData
     * @return array
     */
    public static function getExpressDetails($awardData): array
    {
        if (!empty($awardData['unique_id'])) {
            $expressInfo = (new Erp())->getExpressDetails($awardData['unique_id']);
        }
        $ret['logistics_no'] = $expressInfo['logistics_no'] ?? '';
        $ret['shipping_status'] = $expressInfo['status'] ?? 1;
        $ret['company'] = $expressInfo['company'] ?? '';
        $ret['address_detail'] = $awardData['address_detail'] ?? '{}';
        $deliver[] = [
            'node'          => '已发货',
            'acceptTime'    => Util::formatTimeToChinese($awardData['create_time']),
            'acceptStation' => '小叶子已为您发货，包裹待揽收'
        ];
        $ret['express_record'] = array_merge_recursive(self::formatExpressRecord($expressInfo['logistics_detail'] ?? []),
            $deliver);
        return $ret;
    }

    /**
     * 格式化物流信息
     * @param array $record
     * @return array
     */
    private static function formatExpressRecord(array $record = []): array
    {
        $nodeArr = [];
        foreach ($record as &$value) {
            if (in_array($value['node'], $nodeArr)) {
                $value['node'] = '';
            } else {
                $nodeArr[] = $value['node'];
            }
            $value['acceptTime'] = Util::formatTimeToChinese(strtotime($value['acceptTime']));

        }
        return $record;
    }

    /**
     * 解析物流信息，获取最新状态
     * @param $expressInfoLastNode
     * @return int
     */
    public static function formatLogisticsStatus($expressInfoLastNode): int
    {
        $logisticsStatus = 0;
        switch ($expressInfoLastNode) {
            case "已签收":
                $logisticsStatus = CountingActivityAwardModel::LOGISTICS_STATUS_SIGN;
                break;
            case "派件中":
                $logisticsStatus = CountingActivityAwardModel::LOGISTICS_STATUS_IN_DISPATCH;
                break;
            case "运输中":
                $logisticsStatus = CountingActivityAwardModel::LOGISTICS_STATUS_IN_TRANSIT;
                break;
            case "已揽收":
                $logisticsStatus = CountingActivityAwardModel::LOGISTICS_STATUS_COLLECT;
                break;
            default:
                SimpleLogger::error('logistics node data error', []);
        }
        return $logisticsStatus;
    }
}
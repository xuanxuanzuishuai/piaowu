<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:39 AM
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Models\CountingActivityAwardModel;
use App\Libs\Constants;
use App\Libs\Erp;
use App\Models\CountingActivityModel;
use App\Models\CountingAwardConfigModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Services\Queue\QueueService;

class CountingActivityAwardService
{
    //erp接口参数数据
    const EVENT_TYPE = 1;
    const SOURCE_TYPE = 6002;

    /**
     * 任务列表
     *
     * @param int $signId
     * @return bool
     */
    public static function grantCountingAward(int $signId): bool
    {
        $sign = CountingActivityAwardModel::getRecords([
            'sign_id' => $signId,
            'shipping_status' => CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
        ]);


        if (empty($sign)){
            SimpleLogger::error('select counting_activity_award data not found', ['sign_id' => $sign]);
            return false;
        }

        //验证奖励库存
        $storage = CountingAwardConfigModel::getRecords([
            'op_activity_id' => $sign['op_activity_id'],
            'status' => CountingAwardConfigModel::EFFECTIVE_STATUS,
        ]);

        $mark = true;

        foreach ($storage as $s){
            if ($s['type'] != CountingActivityAwardModel::TYPE_ENTITY)  continue;

            if (($s['quantity'] - $s['amount'] ) < 0) {
                $mark = false;
                break;
            }
        }


        foreach ($sign as $item){

            switch ($item['type']) {
                case CountingActivityAwardModel::TYPE_GOLD_LEAF:
                    CountingActivityAwardService::grantGoldLeaf($item);
                    break;
                case CountingActivityAwardModel::TYPE_ENTITY:
                    CountingActivityAwardService::grantEntity($item,$mark);
                    break;
                default:
                    SimpleLogger::error('counting_activity_award data type error', [$item]);
            }
        }

        return true;
    }


    /**
     * 发放金叶子
     *
     * @param array $data
     * @return bool
     */
    public static function grantGoldLeaf(array $data) :bool
    {

        $student =  DssStudentModel::getById($data['student_id']);
        if (empty($student)){
            SimpleLogger::error('counting_activity_award data type error', $data);
            return false;
        }

        $countingActivity = CountingActivityModel::getRecord([
            'op_activity_id' => $data['op_activity_id']
        ],['task_id','name','instruction','title']);

        //发放金叶子
        $leaf['msg_body'] = [
            'student_uuid' => $student['uuid'],
            'app_id' => Constants::SMART_APP_ID,
            'sub_type' => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
            'num' => $data['amount'],
            'event_task_id' => $countingActivity['task_id'],
            'source_type' => self::SOURCE_TYPE,
            'remark' => $countingActivity['title']
        ];
        $leaf['event_type'] = self::EVENT_TYPE;

        //post请求
        $ret = (new Erp())->grantGoldLeaf($leaf);
        if (!$ret) return false;
        $ret = CountingActivityAwardModel::updateStatus($data['id'],CountingActivityAwardModel::SHIPPING_STATUS_DELIVERED);
        if (empty($ret)) return false;
        //推动微信消息
        QueueService::sendGoldLeafWxMessage([
            'student_id'  => $data['student_id'],
            'uuid'        => $student['uuid'],
            'amount'      => $data['amount'],
            'name'        => $countingActivity['title'],
            'instruction' => $countingActivity['instruction'],
        ]);
        return true;
    }

    /**
     * 请求erp邮递实物
     *
     * @param array $data
     * @param bool $mark
     * @return bool
     */
    public static function grantEntity(array $data,bool $mark = true): bool
    {
        if (!$mark) {
            CountingActivityAwardModel::updateStatus($data['id'], CountingActivityAwardModel::SHIPPING_STATUS_SPECIAL);
            return true;
        }

        $student = DssStudentModel::getById($data['student_id']);
        if (empty($student)) {
            SimpleLogger::error('counting_activity_award data type error', $data);
            return false;
        }

        $params = [
            'order_id'     => $data['unique_id'],
            'plat_id'      => CountingActivityAwardModel::UNIQUE_ID_PREFIX,
            'app_id'       => Constants::SMART_APP_ID,
            'sale_shop'    => CountingActivityAwardModel::SALE_SHOP,
            'goods_id'     => $data['goods_id'],
            'goods_code'   => $data['goods_code'],
            'mobile'       => $student['mobile'],
            'uuid'         => $student['uuid'],
            'num'          => $data['amount'],
            'address_id'   => $data['erp_address_id'],
        ];

        $res = (new Erp())->deliverGoods($params);
        if (!empty($res['code'])) {
            SimpleLogger::error('erp request error', [$res]);
            return false;
        }

        CountingActivityAwardModel::updateStatus($data['id']);

        return true;
    }

    /**
     * 请求erp获取实物发货信息和物流信息
     * @param $uniqueId
     * @return bool
     */
    public static function syncAwardLogistics($uniqueId)
    {
        $expressInfo = (new Erp())->getExpressDetails($uniqueId, false);
        if (empty($expressInfo['logistics_detail'])) {
            return false;
        }
        $logisticsStatus = 0;
        //解析物流信息，获取最新状态
        switch ($expressInfo['logistics_detail'][0]['node']) {
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
                SimpleLogger::error('logistics node data error',[$expressInfo]);
        }
        if (empty($logisticsStatus)) {
            return false;
        }
        CountingActivityAwardModel::batchUpdateRecord(
            [
                'logistics_status' => $logisticsStatus,
                'shipping_status' => $expressInfo['status'],
                'express_number' => $expressInfo['logistics_no'],
                'logistics_company' => $expressInfo['company'],
            ],
            [
                'unique_id' => $uniqueId,
            ]);

        return true;
    }
}

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
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Services\Queue\QueueService;

class CountingActivityAwardService
{
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
            'award_status' => CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
        ]);


        if (empty($sign)){
            SimpleLogger::error('select counting_activity_award data not found', ['sign_id' => $sign]);
            return false;
        }

        foreach ($sign as $item){

            switch ($item['type']) {
                case CountingActivityAwardModel::TYPE_GOLD_LEAF:
                    CountingActivityAwardService::grantGoldLeaf($item);
                    break;
                case CountingActivityAwardModel::TYPE_ENTITY:
                    CountingActivityAwardService::grantEntity($item);
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
        ],['event_task_id','name','instruction']);

        //发放金叶子
        $leaf = [
            'student_uuid' => $student['uuid'],
            'app_id' => Constants::SMART_APP_ID,
            'sub_type' => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
            'num' => $data['amount'],
            'event_type' => 1,
            'event_task_id' => $countingActivity['event_task_id'],
        ];

        $ret = QueueService::grantGoldLeaf($leaf);
        if (!$ret) return false;
        $ret = CountingActivityAwardModel::updateStatus($data['id']);
        if (empty($ret)) return false;
        //推动微信消息
        QueueService::sendGoldLeafWxMessage([
            'student_id'  => $data['student_id'],
            'uuid'        => $student['uuid'],
            'amount'      => $data['amount'],
            'name'        => $countingActivity['name'],
            'instruction' => $countingActivity['instruction'],
        ]);
        return true;
    }

    /**
     * 请求erp邮递实物
     *
     * @param array $data
     * @return bool
     */
    public static function grantEntity(array $data): bool
    {
        $student = DssStudentModel::getById($data['student_id']);
        if (empty($student)) {
            SimpleLogger::error('counting_activity_award data type error', $data);
            return false;
        }

        $params = [
            'order_id'   => $data['unique_id'],
            'plat_id'    => CountingActivityAwardModel::UNIQUE_ID_PREFIX,
            'app_id'     => Constants::SMART_APP_ID,
            'sale_shop'  => CountingActivityAwardModel::SALE_SHOP,
            'goods_id'   => $data['goods_id'],
            'goods_code' => $data['goods_code'],
            'mobile'     => $student['mobile'],
            'uuid'       => $student['student_uuid'],
            'num'        => $data['amount'],
            'address_id' => $data['erp_address_id'],
        ];

        $res = (new Erp())->deliverGoods($params);
        if (!empty($res['code'])) {
            SimpleLogger::error('erp request error', [$res]);
            return false;
        }

        CountingActivityAwardModel::updateStatus($data['id']);

        return true;
    }




}

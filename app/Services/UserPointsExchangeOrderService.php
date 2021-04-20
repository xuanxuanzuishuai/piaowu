<?php


namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Services\Queue\UserPointsExchangeRedPackTopic;

class UserPointsExchangeOrderService
{
    // 处理结果： 1：已经放入待发放队列，  非1表示失败
    const RED_PACK_ADD_QUEUE_SUCCESS = 1;
    const RED_PACK_ADD_QUEUE_FAIL = 0;

    /**
     * 积分兑换红包
     * 当前仅支持 积分类型是金叶子就积分
     * @param $params
     * @return int[]
     * @throws RunTimeException
     */
    public static function toRedPack($params)
    {
        $returnData =  [
            'points_exchange_red_pack_status' => self::RED_PACK_ADD_QUEUE_FAIL,
        ];
        $orderId = $params['order_id'] ?? 0;
        $uuid = $params['uuid'] ?? '';
        // 根据订单号查询订单是否已经存在 - 存在 不能申请发放
        $orderInfo = UserPointsExchangeOrderModel::getRecord(['order_id' => $orderId]);
        if (!empty($orderInfo)) {
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => "order is exists", 'params' => $params]);
            throw new RunTimeException(['order_is_exist']);
        }
        $userInfo = DssStudentModel::getRecord(['uuid' => $uuid], ['id']);
        if (empty($userInfo)) {
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => 'unknown_user', 'params' => $params]);
            throw new RunTimeException(['unknown_user']);
        }

        // 组合发送红包的数据
        $insertRedPackData = [
            'order_id' => $orderId,
            'uuid' => $uuid,
            'order_from' => $params['service_name'],
            'order_type' => UserPointsExchangeOrderModel::ORDER_TYPE_RED_PACK,
            'points' => $params['points_exchange'],
            'order_amounts' => $params['red_amounts'],
            // app_id 和 account_sub_type（账户子类型） 组合就是积分种类， 8_3002:金叶子
            'app_id' => Constants::SMART_APP_ID,
            'account_sub_type' => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
            'user_id' => $userInfo['id'],
            'create_time' => time(),
            'status' => UserPointsExchangeOrderModel::STATUS_WAITING,
        ];
        // 放入发放红包队列
        $id = UserPointsExchangeOrderModel::insertRecord($insertRedPackData);
        if (empty($id)) {
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => 'insert_failure', 'params' => $params, 'insertData' => $insertRedPackData, 'id' => $id]);
            throw new RunTimeException(['insert_failure']);
        }

        // 放入待发放红包队列
        $queueData = [ 'user_points_exchange_order_id' => $id];
        try {
            (new UserPointsExchangeRedPackTopic())->sendRedPack($queueData)->publish();
        }catch (RunTimeException $e) {
            SimpleLogger::info('UserExchangePointsOrderService::toRedPack', [
                'err' => 'sync_data_push_queue_error , topic is UserExchangePointsRedPackTopic',
                'params' => $params,
                'insertData' => $insertRedPackData,
                'id' => $id,
                'queueData' => $queueData,
                'add_queue_err' => $e->getMessage(),
            ]);
            throw new RunTimeException(['sync_data_push_queue_error']);
        }

        $returnData['points_exchange_red_pack_status'] = self::RED_PACK_ADD_QUEUE_SUCCESS;
        return $returnData;
    }
}
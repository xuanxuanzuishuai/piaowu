<?php


namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Models\UserPointsExchangeOrderWxModel;
use App\Models\WeChatAwardCashDealModel;
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
        $returnData = [
            'points_exchange_red_pack_status' => self::RED_PACK_ADD_QUEUE_FAIL,
        ];
        $orderId = $params['order_id'] ?? 0;
        $uuid = $params['uuid'] ?? '';
        // 根据唯一标识查询红包兑换记录是否已经存在
        $recordInfo = UserPointsExchangeOrderWxModel::getRecord(['record_sn' => $params['record_sn']]);
        if (!empty($recordInfo)) {
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => "record_sn_is_exist", 'params' => $params]);
            throw new RunTimeException(['record_sn_is_exist']);
        }
        // 用户信息
        $userInfo = DssStudentModel::getRecord(['uuid' => $uuid], ['id']);
        if (empty($userInfo)) {
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => 'unknown_user', 'params' => $params]);
            throw new RunTimeException(['unknown_user']);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
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
        ];
        // 保存订单
        $id = UserPointsExchangeOrderModel::insertRecord($insertRedPackData);
        if (empty($id)) {
            $db->rollBack();
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => 'insert_failure', 'params' => $params, 'insertData' => $insertRedPackData, 'id' => $id]);
            throw new RunTimeException(['insert_failure']);
        }
        // 保存兑换记录
        $insertRecodeData = [
            'user_points_exchange_order_id' => $id,
            'uuid' => $uuid,
            'user_id' => $userInfo['id'],
            'mch_billno' => CashGrantService::createMchBillNo([$id, $params['record_sn']], [], $params['red_amounts']),
            'order_amounts' => $params['red_amounts'],
            'status' => UserPointsExchangeOrderWxModel::STATUS_WAITING,
            'record_sn' => $params['record_sn']
            // `open_id` varchar(32) DEFAULT '' COMMENT '用户微信标识',
            // `app_id` tinyint(4) DEFAULT '0' COMMENT '业务标识',
            // `busi_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '业务类型 1：学生服务号 2：老师服务号 3：学生订阅号 4: 老师订阅号 5: XX小程序',
            // `result_code` varchar(500) NOT NULL DEFAULT '' COMMENT '请求微信的返回值',
            // `create_time` int(11) NOT NULL DEFAULT '0',
        ];
        $recordId = UserPointsExchangeOrderWxModel::insertRecord($insertRecodeData);
        if (empty($recordId)) {
            $db->rollBack();
            SimpleLogger::info('UserPointsExchangeOrderService::toRedPack', ['err' => 'UserPointsExchangeOrderWxModel::insert', 'params' => $params, 'insertData' => $insertRecodeData]);
            throw new RunTimeException(['insert_failure']);
        }
        $db->commit();

        // 放入待发放红包队列
        $queueData = ['user_points_exchange_order_id' => $id, 'record_sn' => $params['record_sn']];
        try {
            (new UserPointsExchangeRedPackTopic())->sendRedPack($queueData)->publish();
        } catch (RunTimeException $e) {
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

    public static function getList($params, $page, $count)
    {
        $returnList = ['records' => [], 'total_count' => []];
        $where = [];
        if (!empty($params['student_uuid'])) {
            $where['uuid'] = $params['student_uuid'];
        }
        if (!empty($params['reviewer_id'])) {
            $where['uuid'] = $params['reviewer_id'];
        }
        if (!empty($params['student_mobile'])) {
            $studentList = DssStudentModel::getRecords(['mobile' => $params['student_mobile']], ['uuid']);
            $where['uuid'] = array_column($studentList,'uuid');
        }
        if (!empty($params['award_status'])) {
            $where['status'] = $params['award_status'];
        }
        if (!empty($params['s_create_time'])) {
            $where['create_time[>=]'] = $params['s_create_time'];
        }
        if (!empty($params['e_create_time'])) {
            $where['create_time[<]'] = $params['e_create_time'];
        }
        $returnList['total_count'] = UserPointsExchangeOrderWxModel::getCount($where);

        $where['LIMIT'] = [($page-1)*$count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        $list = UserPointsExchangeOrderWxModel::getRecords($where);

        foreach ($list as $_key => $_info) {
            $list[$_key]['student_uuid'] = $_info['uuid'];
            $list[$_key]['award_status_zh'] = UserPointsExchangeOrderWxModel::STATUS_DICT[$_info['status']];
            $list[$_key]['user_event_task_award_id'] = $_info['id'];
            $list[$_key]['student_mobile'] = $_info['id'];
        }
        $returnList['records'] = $list;
        return $returnList;
    }

    /**
     * 重试发送积分兑换红包
     * @throws RunTimeException
     */
    public static function retryExchangeRedPack($ids)
    {
        $awardList = UserPointsExchangeOrderWxModel::getRecords(['id' => $ids]);
        if (empty($awardList)) {
            return true;
        }
        foreach ($awardList as $item) {
            // 放入待发放红包队列
            $queueData = ['user_points_exchange_order_id' => $item['user_points_exchange_order_id'], 'record_sn' => $item['record_sn']];
            try {
                (new UserPointsExchangeRedPackTopic())->sendRedPack($queueData)->publish();
            } catch (RunTimeException $e) {
                SimpleLogger::info('UserExchangePointsOrderService::retryExchangeRedPack', [
                    'err' => 'retryExchangeRedPack',
                    'id' => $ids,
                    'queueData' => $queueData,
                    'add_queue_err' => $e->getMessage(),
                ]);
                return false;
            }
        }
        return true;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;

class AgentPreStorageModel extends Model
{
    public static $table = "agent_pre_storage";
    //支付方式:1支付宝 2微信 3银行卡
    const PAYMENT_MODE_ZFB = 1;
    const PAYMENT_MODE_WX = 2;
    const PAYMENT_MODE_BANK_CARD = 3;
    //状态:1待审核 2审核通过充值成功 3审核不通过驳回
    const STATUS_WAIT = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECT = 3;
    //课包类型:1体验包 2正式包
    const PACKAGE_TYPE_TRAIL = 1;
    const PACKAGE_TYPE_NORMAL = 2;

    /**
     * 查询指定的支付时间后可以消耗的预存年卡订单
     * @param $agentId
     * @param $paymentIntervalTime
     * @return array
     */
    public static function getPreStorageRemainingNormalCardData($agentId, $paymentIntervalTime)
    {
        $db = MysqlDB::getDB();
        return $db->select(
            self::$table,
            [
                '[><]' . AgentPreStorageDetailModel::$table => ['id' => 'pre_storage_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.payment_time',
                self::$table . '.package_unit_price',
            ],
            [
                self::$table . '.agent_id' => $agentId,
                self::$table . '.payment_time[<=]' => strtotime('-' . $paymentIntervalTime . ' days'),
                self::$table . '.package_type' => self::PACKAGE_TYPE_NORMAL,
                self::$table . '.status' => self::STATUS_APPROVED,
                AgentPreStorageDetailModel::$table . '.status' => AgentPreStorageDetailModel::STATUS_NOT_CONSUMED,
                'GROUP' => [self::$table . '.id'],
                'ORDER' => [self::$table . '.id' => 'ASC'],
                'LIMIT' => [0, 1]
            ]
        );
    }
}
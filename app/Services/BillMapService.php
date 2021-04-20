<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/17
 * Time: 20:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\BillMapModel;
use App\Models\ParamMapModel;

class BillMapService
{
    /**
     * 记录订单映射关系
     * @param $qrTicket
     * @param $parentBillId
     * @param $studentId
     * @return bool
     */
    public static function mapDataRecord($qrTicket, $parentBillId, $studentId)
    {
        //检测二维码参数归属人的角色类型
        $paramInfo = ParamMapModel::getParamByQrTicket($qrTicket);
        if (empty($paramInfo)) {
            SimpleLogger::error('qr ticket error', ['qr_ticket' => $qrTicket]);
            return false;
        }
        $insertData = [
            'param_map_id' => $paramInfo['id'],
            'bill_id' => $parentBillId,
            'student_id' => $studentId,
            'user_id' => $paramInfo['user_id'],
            'create_time' => time(),
            'type' => $paramInfo['type']
        ];
        $id = BillMapModel::insertRecord($insertData);
        if (empty($id)) {
            SimpleLogger::error('insert bill map data error', $insertData);
            return false;
        }
        return true;
    }
}
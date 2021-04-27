<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/17
 * Time: 20:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\BillMapModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\ParamMapModel;

class BillMapService
{
    /**
     * 记录订单映射关系
     * @param $sceneData
     * @param $parentBillId
     * @param $studentId
     * @return bool
     */
    public static function mapDataRecord($sceneData, $parentBillId, $studentId)
    {
        //检测票据是否存在param_map中
        if (isset($sceneData['param_id']) && !empty($sceneData['param_id'])) {
            $paramInfo = ParamMapModel::getParamByQrById($sceneData['param_id']);
        } else {
            //获取票据对应的用户身份类型
            $identityData = StudentInviteService::checkQrTicketIdentity($sceneData['r']);
            if (empty($identityData)) {
                SimpleLogger::error('qr ticket error', ['scene_data' => $sceneData]);
                return false;
            }
            //补全学生转介绍学生第一版的转介绍qr_ticket,只存在dss数据库的user_qr_ticket数据表中，在param_map中不存在的数据
            if ($identityData['type'] == ParamMapModel::TYPE_STUDENT) {
                $paramInfo = MiniAppQrService::getSmartQRAliOss($studentId, $identityData['type'], $sceneData);
            }
        }
        if (empty($paramInfo)) {
            SimpleLogger::error('param info empty', ['scene_data' => $sceneData]);
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
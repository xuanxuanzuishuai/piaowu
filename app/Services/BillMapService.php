<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/17
 * Time: 20:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\BillMapModel;
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
        //bill_map增加参数，避免对以往造成影响
        $openId = NULL;
        if (!empty($sceneData['open_id'])) {
            $openId = $sceneData['open_id'];
            unset($sceneData['open_id']);
        }
        $isSuccess = 0;
        if (!empty($sceneData['is_success'])) {
            $isSuccess = $sceneData['is_success'];
            unset($sceneData['is_success']);
        }

        //检测票据是否存在param_map中
        if (!empty($sceneData['qr_id'])) {
            $paramInfo = MiniAppQrService::getQrInfoById($sceneData['qr_id']);
        } elseif (isset($sceneData['param_id']) && !empty($sceneData['param_id'])) {
            //先查询ck,在查询param map
            $paramInfo = MiniAppQrService::getQrInfoById($sceneData['param_id']);
            if (empty($paramInfo)) {
                $paramInfo = ParamMapModel::getParamByQrById($sceneData['param_id']);
                $subInfo = json_decode($paramInfo['param_info'], true);
                $paramInfo['c'] = $subInfo['c'] ?? 0;
            }
        } elseif(!empty($sceneData['r'])){
            //获取票据对应的用户身份类型
            $identityData = StudentInviteService::checkQrTicketIdentity($sceneData['r'], $sceneData['qr_id']);
            if (empty($identityData)) {
                SimpleLogger::error('qr ticket error', ['scene_data' => $sceneData]);
                return false;
            }
            //补全学生转介绍学生第一版的转介绍qr_ticket,只存在dss数据库的user_qr_ticket数据表中，在param_map中不存在的数据
            if ($identityData['type'] == ParamMapModel::TYPE_STUDENT) {
                $paramInfo = MiniAppQrService::getSmartQRAliOss($studentId, $identityData['type'], $sceneData);
            }
        }elseif(!Util::emptyExceptZero($sceneData['c'])){
            $paramInfo = [
                'id'        => 0,
                'user_id'   => 0,
                'type'      => ParamMapModel::TYPE_STUDENT,
                'c'         => $sceneData['c'],
            ];
        }else{
            SimpleLogger::error('empty scene_data', ['scene_data' => $sceneData]);
            return false;
        }
        if (empty($paramInfo)) {
            SimpleLogger::error('param info empty', ['scene_data' => $sceneData]);
            return false;
        }
        $insertData = [
            'param_map_id' => $paramInfo['qr_id'] ?? $paramInfo['id'],  //qr_id 存在说明是预生成二维码
            'bill_id' => $parentBillId,
            'student_id' => $studentId,
            'user_id' => $paramInfo['user_id'],
            'create_time' => time(),
            'type' => $paramInfo['type'],
            'buy_channel'=>$paramInfo['c'] ?? 0,
            'is_success' => $isSuccess
        ];
        if (!empty($openId)) {
            $insertData['open_id'] = $openId;
        }
        $id = BillMapModel::insertRecord($insertData);
        if (empty($id)) {
            SimpleLogger::error('insert bill map data error', $insertData);
            return false;
        }
        return true;
    }

    /**
     * 根据订单号和学生id获取转介绍信息
     * @param string $parentBillId
     * @param int $studentId
     * @return array
     */
    public static function getQrInfoByBillId(string $parentBillId, int $studentId)
    {
        //新绑定逻辑条件检测 - 先读取clickhouse，如果没有查询param_map表
        $billMapInfo = BillMapModel::getRecord(['student_id' => $studentId, 'bill_id' => $parentBillId]);
        if (empty($billMapInfo)) {
            return [];
        }
        $extData = ['buy_channel'=>$billMapInfo['buy_channel']];
        $qrInfo  = MiniAppQrService::getQrInfoById($billMapInfo['param_map_id'], [], $extData);
        if (empty($qrInfo)) {
            $paramMapInfo = ParamMapModel::getRecord(['id' => $billMapInfo['param_map_id']]);
            if (!empty($paramMapInfo)) {
                $paramInfo = !empty($paramMapInfo['param_info']) ? json_decode($paramMapInfo['param_info'], true) : [];
                $qrInfo = array_merge($paramInfo, $paramMapInfo, $extData);
            }
        }
        !empty($qrInfo) && $qrInfo['type'] = $billMapInfo['type'];
        return $qrInfo;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:07 PM
 */

namespace App\Services;


use App\Models\MessageRecordLogModel;
use App\Models\MessageRecordModel;

class MessageRecordService
{

    public static function add($type, $activityId, $successNum, $failNum, $employeeId, $time, $activityType)
    {
        MessageRecordModel::insertRecord([
            'type' => $type,
            'activity_id' => $activityId,
            'success_num' => $successNum,
            'fail_num' => $failNum,
            'operator_id' => $employeeId,
            'create_time' => $time,
            'activity_type' => $activityType
        ]);
    }

    /**
     * 获取发送消息记录
     * @param $activityId
     * @param $employeeId
     * @param $pushWxTime
     * @param $activityType
     * @return mixed
     */
    public static function getMsgRecord($activityId, $employeeId, $pushWxTime, $activityType)
    {
        return MessageRecordModel::getRecord([
            'activity_id' => $activityId,
            'create_time' => $pushWxTime,
            'operator_id' => $employeeId,
            'activity_type' => $activityType
        ]);
    }

    /**
     * 更新消息记录
     * @param $msgId
     * @param $data
     */
    public static function updateMsgRecord($msgId, $data)
    {
        MessageRecordModel::updateRecord($msgId, $data);
    }

    /**
     * @param $openId
     * @param $activityType
     * @param $relateId
     * @param $pushRes
     * 每条消息的推送记录
     */
    public static function addRecordLog($openId, $activityType, $relateId, $pushRes)
    {
        MessageRecordLogModel::insertRecord([
            'open_id' => $openId,
            'activity_type' => $activityType,
            'relate_id' => $relateId,
            'push_res' => $pushRes,
            'create_time' => time()
        ]);
    }
}
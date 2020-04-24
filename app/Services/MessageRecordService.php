<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:07 PM
 */

namespace App\Services;


use App\Models\MessageRecordModel;

class MessageRecordService
{

    public static function add($type, $activityId, $successNum, $failNum, $employeeId, $time)
    {
        MessageRecordModel::insertRecord([
            'type' => $type,
            'activity_id' => $activityId,
            'success_num' => $successNum,
            'fail_num' => $failNum,
            'operator_id' => $employeeId,
            'create_time' => $time
        ]);
    }

    /**
     * 获取发送消息记录
     * @param $activityId
     * @param $employeeId
     * @param $pushWxTime
     * @return mixed
     */
    public static function getMsgRecord($activityId, $employeeId, $pushWxTime)
    {
        return MessageRecordModel::getRecord([
            'activity_id' => $activityId,
            'create_time' => $pushWxTime,
            'operator_id' => $employeeId
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
}
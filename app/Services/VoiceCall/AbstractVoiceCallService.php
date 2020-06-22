<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2020/6/22
 * Time: 下午5:58
 */

namespace App\Services\VoiceCall;


use App\Models\VoiceCallLogModel;

abstract class AbstractVoiceCallService implements VoiceCallIf {

    const VOICE_CALL_SUCCESS = 1; // 已接听
    const VOICE_CALL_FAIL = 2; // 未接听
    const BLOCK_KEY = 1;  // 禁止语音电话 按键

    public $callbackParams = [];

    /**
     * 创建语音电话任务
     * @param $params
     * @return int|mixed|null|string
     */
    public function createTask($params)
    {
        return VoiceCallLogModel::insertRecord($params);

    }

    /**
     * 执行任务
     * @param $params
     */
    public function execTask($params)
    {

    }

    /**
     * 记录执行结果
     * @param $id
     * @param $update
     * @return bool
     */
    public function saveExecResult($id,$update)
    {
        return VoiceCallLogModel::updateById($id,$update);
    }

    /**
     * 记录回调结果
     * @return bool
     */
    public function saveCallbackResult()
    {
        $callBackParams = $this->getCallbackParams();
        if(!empty($callBackParams)){
            $record = VoiceCallLogModel::getRecordByUniqueId($callBackParams['unique_id']);
            return VoiceCallLogModel::updateById($record['id'],$callBackParams);

        }

    }

    /**
     * 获取回调参数
     * @return array
     */
    public function getCallbackParams(){
        return $this->callbackParams;
    }

    /**
     * 设置回调参数
     * @param $params
     */
    public function setCallbackParams($params){
        $this->callbackParams = $params;
    }

}
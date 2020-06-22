<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2020/6/22
 * Time: 下午5:51
 */

namespace App\Services\VoiceCall;


use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;

class VoiceCallTRService extends  AbstractVoiceCallService
{

    const API_SEND = '/interface/open/v1/webcall';
    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }
    const CALLBACK_VOICECALL_COMPLETE = 'voicecall_complete';
    const VOICE_CALL_SUCCESS = 22; // 已接听
    const VOICE_CALL_FAIL = 21; // 未接听

    //提示音类型
    const VOICE_TYPE_STUDENT_URGE = 1; //提醒学生上课
    const VOICE_TYPE_STUDENT_LATE = 2; //提醒学生已迟到
    const VOICE_TYPE_TEACHER_LATE = 3; //提醒老师已迟到
    const VOICE_TYPE_PURCHASE_EXPERIENCE_CLASS = 4; //提醒用户体验课购买成功


    private $timeout = 3;

    /**
     * 加密方法
     * @param $timestamp
     * @return string
     */
    public function signature($timestamp){

        $appId = $this->getAppId();
        $token = DictConstants::get(DictConstants::VOICE_CALL_CONFIG, 'tianrun_voice_call_token');
        $signature = md5($appId.$token.$timestamp);
        return $signature;

    }

    /**
     * 天润企业appid
     * @return mixed
     */
    public function getAppId(){
        return DictConstants::get(DictConstants::VOICE_CALL_CONFIG, 'tianrun_voice_call_appid');
    }


    /**
     * 请求语音电话接口
     * @param $params
     * @param $type
     * @return array
     */
    public function execTask($params, $type = self::VOICE_TYPE_STUDENT_URGE){

        $now = time();

        try {
            $client = new Client();
            $url = $this->url . self::API_SEND;
            $post = [
                'query' => [
                    'appId' => $this->getAppId(),
                    'timestamp' => $now,
                    'sign' => $this->signature($now),
                    'tel' => $params['customer_number'],
                ],
                'timeout' => $this->timeout,
            ];
            if(!empty($type)) {
                $post['query']['type'] = $type;
            }

            $res = $client->request("post", $url, $post);
            $code = $res->getStatusCode();
            $data = $res->getBody()->getContents();

            SimpleLogger::info($url, ['code' => $code, 'data' => $data, 'post'=>$post]);

            $dataArr = json_decode($data, true);
            //不是返回值json 或者 状态不是200
            if ($code != 200 || json_last_error() != JSON_ERROR_NONE) {
                $update = [];
                $update['exec_time'] = $now;
                $update['exec_msg'] = 'curl_failed';
            }else{
                $update = [];
                $update['exec_time'] = $now;

                if($dataArr['result'] == 0 && !empty($dataArr['uniqueId'])){
                    $update['exec_status'] = 1;
                    $update['unique_id'] = $dataArr['uniqueId'];
                }
                $update['exec_msg'] = $dataArr['description'];
            }

            $this->saveExecResult($params['task_id'],$update);

        } catch (\Exception $e) {
            SimpleLogger::info($url, ['code' => $e->getCode(), 'data' => $e->getMessage()]);
        }
        return $dataArr?? [];
    }


    public function setCallbackParams($params){
        $format =  $this->formatCallbackParams($params);
        parent::setCallbackParams($format);
    }

    /**
     * 格式化 回调参数
     * @param $params
     * @return array|bool
     */
    public function formatCallbackParams($params){
        //若参数不对直接返回
        if (empty($params) || empty($params['uniqueId'])) {
            return false;
        }
        $data = [];
        $data['unique_id'] = $params['uniqueId'];
        $data['start_time'] = $params['startTime'];
        $data['answer_time'] = $params['answerTime'];
        $data['end_time'] = $params['endTime'];
        $data['ring_duration'] = $params['ringDuration'];
        $data['answer_duration'] = $params['answerDuration'];
        $data['call_status'] = $this->formatStatus($params['status']);
        $data['key'] = $params['key'];
        
        return $data;
    }

    /**
     * 语音通话状态标准化
     * @param $status
     * @return int
     */
    public static function formatStatus($status)
    {
        switch ($status) {
            case self::VOICE_CALL_SUCCESS:
                $formatStatus = parent::VOICE_CALL_SUCCESS;
                break;
            case self::VOICE_CALL_FAIL:
                $formatStatus = parent::VOICE_CALL_FAIL;
                break;
            default:
                $formatStatus = 0;
        }
        return $formatStatus;
    }
}
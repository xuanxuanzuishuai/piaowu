<?php
namespace App\Libs;

class DingDing
{
    private $host;

    const SPONSOR_APPLY = '/rapi/v1/dingding/workflow/launch';
    const APPLY_DETAIL = '/rapi/v1/dingding/workflow/instance';
    const GET_UUID_GET_MOBILE = '/rapi/v1/dingding/user/mobile';
    const BIND_MOBILE = '/rapi/v1/dingding/user/bind';
    const DEL_BIND_MOBILE = '/rapi/v1/dingding/user/unbind';

    const EXECUTE_TASK_NORMAL = 'EXECUTE_TASK_NORMAL';
    const EXECUTE_TASK_AGENT = 'EXECUTE_TASK_AGENT';
    const APPEND_TASK_BEFORE = 'APPEND_TASK_BEFORE';
    const APPEND_TASK_AFTER = 'APPEND_TASK_AFTER';
    const REDIRECT_TASK = 'REDIRECT_TASK';
    const START_PROCESS_INSTANCE = 'START_PROCESS_INSTANCE';
    const TERMINATE_PROCESS_INSTANCE = 'TERMINATE_PROCESS_INSTANCE';
    const FINISH_PROCESS_INSTANCE = 'FINISH_PROCESS_INSTANCE';
    const ADD_REMARK = 'ADD_REMARK';
    const PROCESS_CC = 'PROCESS_CC';

    const NONE = 'NONE';
    const AGREE = 'AGREE';
    const REFUSE = 'REFUSE';

    //审批事件回调类型
    const BPMS_TASK_CHANGE = 'bpms_task_change';
    const BPMS_INSTANCE_CHANGE = 'bpms_task_change';

    const BPMS_FINISH = 'finish';

    const BPMS_AGREE = 'agree';

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::DING_DING_CONFIG, 'host');
    }

    /**
     * @param $params
     * @return array|bool
     * 发起审批
     */
    public function sponsorApply($params)
    {
        return HttpHelper::requestJson($this->host . self::SPONSOR_APPLY, $params, 'POST');
    }

    /**
     * @param $params
     * @return array|bool
     * 审批详情
     */
    public function getApplyDetail($params)
    {
        return HttpHelper::requestJson($this->host . self::APPLY_DETAIL, $params, 'GET');
    }

    /**
     * @param $operationType
     * @return string
     * 操作类型中文展示
     */
    public static function getOperationTypeZh($operationType)
    {
        $arr = [
            self::EXECUTE_TASK_NORMAL => '正常执行任务',
            self::EXECUTE_TASK_AGENT => '代理人执行任务',
            self::APPEND_TASK_BEFORE => '前加签任务',
            self::APPEND_TASK_AFTER => '后加签任务',
            self::REDIRECT_TASK => '转交任务',
            self::START_PROCESS_INSTANCE => '发起申请',
            self::TERMINATE_PROCESS_INSTANCE => '终止(撤销)流程实例',
            self::FINISH_PROCESS_INSTANCE => '结束流程实例',
            self::ADD_REMARK => '添加评论',
            self::PROCESS_CC => '添加抄送人'
        ];
        return $arr[$operationType] ?? $operationType;
    }

    /**
     * @param $operationResult
     * @return string
     * 操作结果中文展示
     */
    public static function getOperationResultZh($operationResult)
    {
        $arr = [
            self::NONE => '无',
            self::AGREE => '同意',
            self::REFUSE => '拒绝'
        ];
        return $arr[$operationResult] ?? $operationResult;
    }

    /**
     * @param $params
     * @return array|bool
     * uuid得到手机号
     */
    public function getMobileByUuid($params)
    {
        return HttpHelper::requestJson($this->host . self::GET_UUID_GET_MOBILE, $params, 'GET');
    }

    /**
     * @param $params
     * @return array|bool
     * 绑定钉钉手机号
     */
    public function bindMobile($params)
    {
        return HttpHelper::requestJson($this->host . self::BIND_MOBILE, $params, 'POST');
    }

    /**
     * @param $params
     * @return array|bool
     * 解除绑定手机号
     */
    public function delBindMobile($params)
    {
        return HttpHelper::requestJson($this->host . self::DEL_BIND_MOBILE, $params, 'POST');
    }
}
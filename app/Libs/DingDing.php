<?php
namespace App\Libs;

use App\Libs\Exceptions\RunTimeException;

class DingDing
{
    private $host;
    const SELF_APP_ID = 10;
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
    const BPMS_INSTANCE_CHANGE = 'bpms_instance_change';

    const BPMS_FINISH = 'finish';
    const BPMS_BACK   = 'terminate';

    const BPMS_AGREE = 'agree';
    const BPMS_REFUSE = 'refuse';

    public function __construct()
    {
        $this->host = DictConstants::get(DictConstants::DING_DING_CONFIG, 'host');
    }

    /**
     * @param $params
     * @return mixed
     * @throws RunTimeException
     * 发起审批
     */
    public function sponsorApply($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $data = HttpHelper::requestJson($this->host . self::SPONSOR_APPLY, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException([self::getErrorCodeMsg($data['code'])]);
        }
        return $data['data'];
    }

    /**
     * @param $params
     * @return mixed
     * @throws RunTimeException
     * 审批详情
     */
    public function getApplyDetail($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $data = HttpHelper::requestJson($this->host . self::APPLY_DETAIL, $params, 'GET');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException([$data['code']]);
        }
        return $data['data'];
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
     * @return mixed
     * @throws RunTimeException
     * uuid得到手机号
     */
    public function getMobileByUuid($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $data =  HttpHelper::requestJson($this->host . self::GET_UUID_GET_MOBILE, $params, 'GET');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException([self::getErrorCodeMsg($data['code'])]);
        }
        return $data['data'];
    }

    /**
     * 绑定钉钉手机号
     * @param $params
     * @return mixed
     * @throws RunTimeException
     */
    public function bindMobile($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $data = HttpHelper::requestJson($this->host . self::BIND_MOBILE, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException([self::getErrorCodeMsg($data['code'])]);
        }
        return $data['data'];
    }

    /**
     * @param $params
     * @return mixed
     * @throws RunTimeException
     * 解除绑定手机号
     */
    public function delBindMobile($params)
    {
        $params['app_id'] = self::SELF_APP_ID;
        $data = HttpHelper::requestJson($this->host . self::DEL_BIND_MOBILE, $params, 'POST');
        if ($data['code'] != Valid::CODE_SUCCESS) {
            throw new RunTimeException([self::getErrorCodeMsg($data['code'])]);
        }
        return $data['data'];
    }

    /**
     * @param $code
     * @return string
     * 错误信息对应
     */
    private static function getErrorCodeMsg($code)
    {
        $arr = [
            '5000' => 'employee_not_exist',
            '5001' => 'ding_ding_user_not_exist',
            '5002' => 'ding_ding_mobile_has_bind',
            '5003' => 'not_bind_ding_ding',
            '5004' => 'not_find_ding_branch_info',
            '5005' => 'ding_ding_create_apply_fail',
            '5006' => 'not_find_ding_ding_apply',
            '5009' => 'binding_confirmation_has_been_sent'
        ];
        return $arr[$code] ?? 'ding_ding_request_error';
    }
}
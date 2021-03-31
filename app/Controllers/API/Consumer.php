<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/27
 * Time: 2:05 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Libs\Spreadsheet;
use App\Libs\TPNS;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\StudentAccountAwardPointsFileModel;
use App\Models\StudentAccountAwardPointsLogModel;
use App\Services\CashGrantService;
use App\Services\MessageService;
use App\Services\Queue\DurationTopic;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\StudentAccountAwardPointsTopic;
use App\Services\Queue\ThirdPartBillTopic;
use App\Services\RefereeAwardService;
use App\Services\StudentAccountAwardPointsLogService;
use App\Services\StudentService;
use App\Services\ThirdPartBillService;
use App\Services\UserRefereeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Consumer extends ControllerBase
{
    /**
     * 更新不同系统的access_token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAccessToken(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        WeChatMiniPro::factory($params['msg_body']['app_id'], $params['msg_body']['busi_type'])->setAccessToken($params['msg_body']['access_token']);
    }

    /**
     * 转介绍相关
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeAward(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            UserRefereeService::refereeAwardDeal($params['msg_body']['app_id'], $params['event_type'], $params['msg_body']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 红包相关
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackDeal(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            CashGrantService::redPackQueueDeal($params['msg_body']['award_id'], $params['event_type'], $params['msg_body']['reviewer_id'] ?: EmployeeModel::SYSTEM_EMPLOYEE_ID, $params['msg_body']['reason'] ?: '', ['activity_id' => $params['msg_body']['activity_id'] ?? 0]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 消息推送
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pushMessage(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            switch ($params['event_type']) {
                case PushMessageTopic::EVENT_WECHAT_INTERACTION:
                    MessageService::interActionDealMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_USER_BIND_WECHAT:
                    MessageService::boundWxActionDealMessage(
                        $params['msg_body']['open_id'],
                        $params['msg_body']['app_id'],
                        $params['msg_body']['user_type'],
                        $params['msg_body']['busi_type']
                    );
                    break;

                case PushMessageTopic::EVENT_PAY_NORMAL:
                    MessageService::yearPayActionDealMessage($params['msg_body']['user_id'], $params['msg_body']['package_type']);
                    break;

                case PushMessageTopic::EVENT_SUBSCRIBE:
                    $data = MessageService::preSendVerify($params['msg_body']['open_id'], DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id'));
                    if (!empty($data)) {
                        MessageService::realSendMessage($data);
                    }
                    break;

                case PushMessageTopic::EVENT_PUSH_MANUAL_RULE_WX:
                    MessageService::realSendManualMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_PUSH_WX:
                    MessageService::pushWXMsg($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_PUSH_RULE_WX:
                    MessageService::realSendMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_UNSUBSCRIBE:
                    MessageService::clearMessageRuleLimit($params['msg_body']['open_id']);
                    break;

                case PushMessageTopic::EVENT_AIPL_PUSH:
                    TPNS::push($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_MONTHLY_PUSH:
                    MessageService::monthlyEvent($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_PUSH_CHECKIN_MESSAGE:
                    MessageService::checkinMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_WEB_PAGE_CLICK:
                    MessageService::sendRecallPageSms($params['msg_body']);
                    break;
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 第三方订单导入消费
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function thirdPartBill(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'topic_name',
                'type'       => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key'        => 'event_type',
                'type'       => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key'        => 'msg_body',
                'type'       => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try{
            $lastId = 0;
            switch ($params['event_type']) {
                case ThirdPartBillTopic::EVENT_TYPE_IMPORT:
                    $lastId = ThirdPartBillService::handleImport($params['msg_body']);
                    break;
                default:
                    SimpleLogger::error('consume_third_part_bill', ['unknown_event_type' => $params]);
            }
        }catch (RunTimeException $runTimeException){
            return HttpHelper::buildErrorResponse($response, $runTimeException->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, ['last_id' => $lastId]);
    }

    /**
     * 发放学生积分奖励
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentAccountAwardPoints(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            SimpleLogger::info("consumer::studentAccountAwardPoints params error.", ['params' => $params]);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $time = time();
            $msgBody = $params['msg_body'];
            // 检查 event_type 和 topic_name
            if ($params['event_type'] != StudentAccountAwardPointsTopic::EVENT_TYPE_IMPORT) {
                SimpleLogger::info("consumer::studentAccountAwardPoints topic_name or event_type error.", ['params' => $params]);
                return HttpHelper::buildErrorResponse($response, ['event_type error']);
            }
            // 检查是否可执行，正在执行中停止执行 ，没有查到写日志， 状态非可执行状态加日志
            $info = StudentAccountAwardPointsFileModel::getRecord([
                'operator_id' => $msgBody['operator_id'],
                'chunk_file' => $msgBody['chunk_file'],
                'app_id' => $msgBody['app_id'],
                'sub_type' => $msgBody['sub_type'],
            ]);
            if (empty($info) || $info['status'] != StudentAccountAwardPointsFileModel::STATUS_CREATE) {
                SimpleLogger::info("consumer::studentAccountAwardPoints status is not create.", ['params' => $params, 'info' => $info]);
                return HttpHelper::buildErrorResponse($response, ['status is not create']);
            }

            // 保存文件到本地
            $localPath = AliOSS::saveTmpFile(AliOSS::signUrls($msgBody['chunk_file']));
            if (!$localPath) {
                SimpleLogger::info('consumer::studentAccountAwardPoints save to location file error', ['params' => $params]);
                return HttpHelper::buildErrorResponse($response, ['save to location file error']);
            }

            // 读取excel
            $excelData = Spreadsheet::getActiveSheetData($localPath);
            $excelTitle = array_shift($excelData);
            //如果excel为空，直接标记任务完成
            if (empty($excelData)) {
                $lockRes = StudentAccountAwardPointsFileModel::updateStatusCreateToCompleteById($info['id']);
                if (!$lockRes) {
                    SimpleLogger::info("consumer::studentAccountAwardPoints empty ,update status create to complete error.", ['params' => $params, 'info' => $info]);
                    return HttpHelper::buildErrorResponse($response, []);
                }
            }

            //查询student信息 and 组装请求数据
            $requestErpData = StudentAccountAwardPointsLogService::excelDataToLogData($excelData);
            $uuidArr = array_diff(array_column($requestErpData,'uuid'), [null]);
            $mobileArr = array_diff(array_column($requestErpData,'mobile'), [null]);
            $studentList = ErpStudentModel::getListByUuidAndMobile($uuidArr, $mobileArr, ['id','uuid','mobile']);
            // 生成新的以 uuid 和 mobile为key 的数组
            $uuidInfoList = [];
            $mobileInfoList = [];
            foreach ($studentList as $_sTime) {
                $uuidInfoList[$_sTime['uuid']] = $_sTime;
                $mobileInfoList[$_sTime['mobile']] = $_sTime;
            }
            unset($studentList);

            // 组合数据 - 请求erp接口数据和日志表保存数据
            $batchInsertData = [];
            $errData = [];
            foreach ($requestErpData as $_key => $_info) {
                $_student_info = !empty($_info['uuid']) ? $uuidInfoList[$_info['uuid']] : $mobileInfoList[$_info['mobile']];
                // 如果student_id不存在，直接按失败处理 ,  $_student_info is NULL or empty continue and add $errData
                if (!$_student_info) {
                    $errData[] = StudentService::formatErrData($_info['uuid'], $_info['mobile'],'学生不存在', $_info['num']);
                    continue;
                }
                $batchInsertData[$_key] = $_info;
                $batchInsertData[$_key]['num'] = Util::fen($_info['num']);  //转换成分和erp.student_account表单位保持一致
                $batchInsertData[$_key]['operator_id'] = $msgBody['operator_id'];
                $batchInsertData[$_key]['app_id'] = $msgBody['app_id'];
                $batchInsertData[$_key]['sub_type'] = $msgBody['sub_type'];
                $batchInsertData[$_key]['remark'] = $msgBody['remark'];
                $batchInsertData[$_key]['create_time'] = $time;
                $batchInsertData[$_key]['file_id'] = $info['id'];
                // student_id, uuid, mobile,
                $batchInsertData[$_key]['student_id'] = $_student_info['id'];
                $batchInsertData[$_key]['uuid'] = $_student_info['uuid'];
                $batchInsertData[$_key]['mobile'] = $_student_info['mobile'];
                //补全 请求erp接口必要字段
                $requestErpData[$_key]['student_id'] = $_student_info['id'];
            }

            // 更新状态为正在执行 (update status=1,update_time=time())
            $lockRes = StudentAccountAwardPointsFileModel::updateStatusExecById($info['id']);
            if (!$lockRes) {
                SimpleLogger::info("consumer::studentAccountAwardPoints update status create to exec error.", ['params' => $params, 'info' => $info]);
                return HttpHelper::buildErrorResponse($response, ['update status create to exec error']);
            }

            // 发放积分
            $requestErpRes = (new Erp())->batchAwardPoints([
                'award_points_list' => $requestErpData,
                'app_id' => $msgBody['app_id'],
                'sub_type' => $msgBody['sub_type'],
                'batch_id' => 'op_' . $info['id'],  //批次号
                'remark' => $msgBody['remark'],
            ]);
            SimpleLogger::info("consumer::studentAccountAwardPoints request erp request",['res' => $requestErpRes, 'fileid'=>$info['id']]);

            if (isset($requestErpRes['code']) && $requestErpRes['code'] == 0) {
                $failList = $requestErpRes['data']['fail_list'];
                $fail_num = isset($requestErpRes['data']['fail_list']) ? count($requestErpRes['data']['fail_list']) : 0;
                // 移除添加失败的记录
                foreach ($batchInsertData as $_insertKey => $_insertVal) {
                    foreach ($failList as $_fKey => $_fVal) {
                        if (!empty($_fVal['uuid']) && $_fVal['uuid'] == $_insertVal['uuid']) {
                            unset($batchInsertData[$_insertKey]);
                            break;
                        }
                    }
                }
                // 保存发放日志
                SimpleLogger::info("consumer::studentAccountAwardPoints batch insert start",['fileid'=>$info['id']]);
                $batchInsertDataChunk = array_chunk($batchInsertData,2000);
                foreach ($batchInsertDataChunk as $_chunk) {
                    StudentAccountAwardPointsLogModel::batchInsert($_chunk);
                }
                SimpleLogger::info("consumer::studentAccountAwardPoints batch insert end",['fileid'=>$info['id']]);

            }else {
                // 发放失败， 不写入日志
                $failList = $requestErpData;
                $fail_num = count($requestErpData);
            }

            // 更新状态为完成
            SimpleLogger::info("consumer::studentAccountAwardPoints update status start ", ['fileid'=>$info['id']]);
            $lockRes = StudentAccountAwardPointsFileModel::updateStatusExecToCompleteById($info['id']);
            if (!$lockRes) {
                SimpleLogger::info("consumer::studentAccountAwardPoints update status create exec to complete error.", ['params' => $params, 'info' => $info]);
                return HttpHelper::buildErrorResponse($response, ['update status create exec to complete error']);
            }

            // 发放失败的数据保存到excel
            $failExcelLocalPath = pathinfo($localPath, PATHINFO_DIRNAME) . '/fail_' . pathinfo($localPath, PATHINFO_BASENAME);
            if (!empty($failList)) {
                $excelTitle[]='失败原因';
                foreach ($failList as $_fTime) {
                    $errData[] = StudentService::formatErrData($_fTime['uuid'], $_fTime['mobile'], $_fTime['err_msg'], $_fTime['num']);
                }
                SimpleLogger::info("consumer::studentAccountAwardPoints error data.", ['title' => $excelTitle, 'info' => $errData, 'fileid'=>$info['id']]);
                Spreadsheet::createXml($failExcelLocalPath, $excelTitle, $errData);
            }
            // 发送邮件 - 把本次失败的数据生成excel 发送到指定邮箱
            SimpleLogger::info("consumer::studentAccountAwardPoints send mail start ", ['fileid'=>$info['id']]);
            list($toMail, $err_title, $title) = DictConstants::get(DictConstants::AWARD_POINTS_SEND_MAIL_CONFIG, ['to_mail', 'err_title', 'title']);
            $success_num = $requestErpRes['data']['success_num'] ?? 0;
            $accountNameList = StudentAccountAwardPointsLogService::getAccountName();
            $account_name = $accountNameList[$params['app_id'] . '_' . $params['sub_type']];
            $emailTitle = $fail_num > 0 ? $err_title : $title;
            $content = '本次积分账户已发放完成' . $account_name . '总共' . count($requestErpData) . '条数据，成功处理' . $success_num . '条，有 ' . $fail_num . '条导入失败，可下载附件，查看失败数据及其内容。重新导入失败数据时，请按照上传模板重新导入失败数据';
            $res = PhpMail::sendEmail($toMail, $emailTitle, $content, $failExcelLocalPath);
            if (!$res) {
                SimpleLogger::error("Consumer::studentAccountAwardPoints send mail fail", ['params' => $params, 'info' => $info, 'error' => $res,'fileid'=>$info['id']]);
            }
        } catch (RunTimeException $runTimeException) {
            SimpleLogger::error("consumer::studentAccountAwardPoints catch", ['params' => $params, 'err' => $runTimeException->getAppErrorData()]);
            return HttpHelper::buildErrorResponse($response, $runTimeException->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 延迟发放的时长奖励
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendDuration(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            switch ($params['event_type']) {
                case DurationTopic::EVENT_SEND_DURATION:
                    RefereeAwardService::sendDuration($params['msg_body']['award_id']);
                    break;
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}
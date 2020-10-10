<?php
/**
 * User: lizao
 * Date: 2020.09.23 14:33
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\MessageService;
use App\Models\MessagePushRulesModel;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Message extends ControllerBase
{
    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 推送规则列表
     */
    public function rulesList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            list($rules, $totalCount) = MessageService::rulesList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'rules'       => $rules,
            'total_count' => $totalCount
        ]);
    }

    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 推送规则详情
     */
    public function ruleDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $detail = MessageService::ruleDetail($params['id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $detail);
    }

    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 推送规则启用状态修改
     */
    public function ruleUpdateStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'in',
                'value'      => MessagePushRulesModel::RULE_STATUS_DICT,
                'error_code' => 'status_is_invalid'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $errorCode = MessageService::ruleUpdateStatus($params);
            if (!empty($errorCode)) {
                throw new RunTimeException([$errorCode]);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 推送规则更新
     */
    public function ruleUpdate(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key'        => 'content_1',
                'type'       => 'requiredWithout',
                'flag'       => true,
                'value'      => ['content_2', 'image'],
                'error_code' => 'data_can_not_be_empty'
            ],
            [
                'key'        => 'content_2',
                'type'       => 'requiredWithout',
                'flag'       => true,
                'value'      => ['content_1', 'image'],
                'error_code' => 'data_can_not_be_empty'
            ],
            [
                'key'        => 'image',
                'type'       => 'requiredWithout',
                'flag'       => true,
                'value'      => ['content_1', 'content_2'],
                'error_code' => 'data_can_not_be_empty'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $errorCode = MessageService::ruleUpdate($params);
            if (!empty($errorCode)) {
                throw new RunTimeException([$errorCode]);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 上一次推送记录
     */
    public function manualLastPush(Request $request, Response $response)
    {
        try {
            list($templateFile, $data) = MessageService::manualLastPush();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'file' => $templateFile,
            'data' => $data,
        ]);
    }

    /**
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     * 手动推送消息
     */
    public function manualPush(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'push_type',
                'type'       => 'required',
                'error_code' => 'push_type_is_required',
            ]
        ];
        $params = $request->getParams();
        if ($params['push_type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
            $rules = array_merge($rules, [
                [
                    'key'        => 'content_1',
                    'type'       => 'requiredWithout',
                    'flag'       => true,
                    'value'      => ['content_2', 'image'],
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'content_2',
                    'type'       => 'requiredWithout',
                    'flag'       => true,
                    'value'      => ['content_1', 'image'],
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'image',
                    'type'       => 'requiredWithout',
                    'flag'       => true,
                    'value'      => ['content_1', 'content_2'],
                    'error_code' => 'data_can_not_be_empty'
                ]
            ]);
        } else {
            $rules = array_merge($rules, [
                [
                    'key'        => 'first_sentence',
                    'type'       => 'required',
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'activity_detail',
                    'type'       => 'required',
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'activity_desc',
                    'type'       => 'required',
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'remark',
                    'type'       => 'required',
                    'error_code' => 'data_can_not_be_empty'
                ],
                [
                    'key'        => 'link',
                    'type'       => 'required',
                    'error_code' => 'data_can_not_be_empty'
                ]
            ]);
        }
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            // 获取上传文件
            $pushFile = $_FILES['push_file'] ?? [];
            if (empty($pushFile)) {
                throw new RunTimeException(['push_file_is_required']);
            }
            // 验证文件
            $extension = strtolower(pathinfo($pushFile['name'])['extension']);
            if (!in_array($extension, ['xls', 'xlsx'])) {
                throw new RunTimeException(['file_format_invalid']);
            }
            // 暂存文件
            $fileName = $_ENV['STATIC_FILE_SAVE_PATH'].'/'.md5(rand().time()).'.'.$extension;
            if (move_uploaded_file($pushFile['tmp_name'], $fileName) == false) {
                throw new RunTimeException(['download_file_error']);
            }

            $data = MessageService::verifySendList($fileName);
            unlink($fileName);
            if (!empty($data) && is_string($data)) {
                throw new RunTimeException([$data]);
            }
            if (empty($data)) {
                throw new RunTimeException(['send_list_empty']);
            }
            // Save send log
            $logId = MessageService::saveSendLog($params);

            // Send message with number: $data
            MessageService::manualPushMessage($logId, $data, $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}
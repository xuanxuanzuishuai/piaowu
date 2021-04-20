<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\MessagePushRulesModel;
use App\Models\PosterModel;
use App\Models\WeChatAwardCashDealModel;
use App\Services\BillMapService;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\PosterService;
use App\Services\AgentService;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use App\Services\ReferralService;
use App\Services\ThirdPartBillService;
use App\Services\UserRefereeService;
use App\Services\UserService;
use App\Services\WechatService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Dss extends ControllerBase
{
    /**
     * 获取可生成海报的活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activeList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $result = ReferralActivityService::getActiveList($params);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 获取活动海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'employee_id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getEmployeePoster($params['activity_id'], $params['employee_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 分享海报，返回参数ID
     */
    public static function getParamsId(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key' => 'user_id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $id = ReferralActivityService::getParamsId($params);
        return HttpHelper::buildResponse($response, ['id' => $id]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 分享海报根据参数ID返回参数信息
     */
    public static function getParamsInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'param_id',
                'type' => 'required',
                'error_code' => 'param_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $paramInfo = ReferralActivityService::getParamsInfo($params['param_id']);
        return HttpHelper::buildResponse($response, $paramInfo);
    }

    /**
     * 创建转介绍关系
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createRelation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'qr_ticket',
                'type' => 'required',
                'error_code' => 'qr_ticket_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            UserRefereeService::registerDeal($params['student_id'], $params['uuid'], $params['qr_ticket'], $params['app_id'], $params['ext_params']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 红包信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'award_id',
                'type' => 'required',
                'error_code' => 'award_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => explode(',', $params['award_id'])]);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 海报底图数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function posterBaseInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_id',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = PosterModel::getRecord(['id' => $params['poster_id'], 'status' => Constants::STATUS_TRUE], ['path']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取消息信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function messageInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = MessagePushRulesModel::getById($params['id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 创建代理和订单映射关系
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function makeAgentBillMap(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'parent_bill_id',
                'type' => 'required',
                'error_code' => 'parent_bill_id_is_required'
            ],
            [
                'key' => 'qr_ticket',
                'type' => 'required',
                'error_code' => 'qr_ticket_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = AgentBillMapModel::add($params['qr_ticket'], $params['parent_bill_id'], $params['student_id']);
        return HttpHelper::buildResponse($response, ['res' => $res]);
    }


    /**
     * 第三方导入订单列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function thirdBillList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $records = ThirdPartBillService::thirdBillList($params, $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records,
        ]);
    }

    /**
     * 用户登出
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function tokenLogout(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'user_id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $userType = $params['user_type'] ?: DssUserWeiXinModel::USER_TYPE_STUDENT;
        $appId = DssUserWeiXinModel::dealAppId($params['app_id']);
        try {
            WechatTokenService::delTokenByUserId($params['user_id'], $userType, $appId);
            UserService::recordUserActiveConsumer($params['user_id']);
        } catch (RunTimeException $e) {
            SimpleLogger::error('token logout error', [$e->getAppErrorData()]);
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 检测社群分班条件
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function distributionClassCondition(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'parent_bill_id',
                'type' => 'required',
                'error_code' => 'parent_bill_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $condition = AgentService::distributionClassCondition($params['parent_bill_id'], $params['student_id']);
        return HttpHelper::buildResponse($response, (int)$condition);
    }

    /**
     * 获取海报图片路径对应的id
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPathId(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'path',
                    'type' => 'required',
                    'error_code' => 'path_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $posterId = PosterService::getIdByPath($params['path'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['poster_id' => $posterId]);
    }

    /**
     * 是否绑定了线下代理
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function isBindOffline(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'student_id',
                    'type' => 'required',
                    'error_code' => 'student_id_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $res = AgentService::isBindOffLine($params['student_id'], $params['parent_bill_id'] ?: '', $params['type'] ?: '');
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['res' => $res]);
    }

    /**
     * 获取待发放金叶子积分明细
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function goldLeafList(Request $request, Response $response) {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $res = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList($params, $page, $limit);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Dss::goldLeafList error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'total_count' => $res['total'],
            'logs' => $res['list'],
            'total_num' => $res['total_num'],
        ]);
    }

    //我邀请的学生信息列表
    public function myInviteStudentList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'referrer_uuid',
                'type' => 'required',
                'error_code' => 'referrer_uuid_is_required'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
        ];
        $result = Valid::Validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $count) = Util::formatPageCount($params);
            $inviteStudentList = ReferralService::myInviteStudentList($params, $page, $count);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Dss::myInviteStudentList error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $inviteStudentList);
    }
    /**
     * 获取用户个性化菜单类型
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getUserMenuType(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'open_id',
                    'type' => 'required',
                    'error_code' => 'open_id_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $res = WechatService::getUserTypeByOpenid($params['open_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['type' => $res]);
    }

    /**
     * 强制更新用户标签
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateUserTag(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'open_id',
                    'type' => 'required',
                    'error_code' => 'open_id_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $res = WechatService::updateUserTag($params['open_id'], true);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['type' => $res]);
    }
}
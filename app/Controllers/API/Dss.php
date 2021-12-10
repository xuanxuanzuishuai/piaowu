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
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\MessagePushRulesModel;
use App\Models\PosterModel;
use App\Models\RtActivityModel;
use App\Models\SharePosterModel;
use App\Models\UserPointsExchangeOrderWxModel;
use App\Models\WeChatAwardCashDealModel;
use App\Services\BillMapService;
use App\Services\DssDictService;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\ErpUserEventTaskAwardService;
use App\Services\PosterService;
use App\Services\AgentService;
use App\Services\QrInfoService;
use App\Services\RealSharePosterService;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use App\Services\ReferralService;
use App\Services\RtActivityService;
use App\Services\SharePosterService;
use App\Services\SourceMaterialService;
use App\Services\ThirdPartBillService;
use App\Services\UserPointsExchangeOrderService;
use App\Services\UserRefereeService;
use App\Services\UserService;
use App\Services\WechatService;
use App\Services\WechatTokenService;
use App\Services\WeekActivityService;
use App\Services\WeekWhiteListService;
use App\Services\WhiteGrantRecordService;
use App\Services\WhiteRecordService;
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
     * 通过参数生成转介绍param_id，不生成任何图片
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public static function getNewParamsId(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'user_id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $qrType = DictConstants::get(DictConstants::MINI_APP_QR, 'qr_type_none');
        $qrData = [
            'user_id'     => $params['user_id'],
            'user_type'   => DssUserQrTicketModel::STUDENT_TYPE,
            'channel_id'  => $params['channel_id'],
            'app_id'      => Constants::SMART_APP_ID,
            'qr_type'     => $qrType,
            'date'        => date('Y-m-d', time()),
        ];
        $qrInfo = QrInfoService::getQrIdList(Constants::SMART_APP_ID, Constants::SMART_MINI_BUSI_TYPE, [$qrData]);
        $qrId = !empty($qrInfo) ? end($qrInfo)['qr_id'] : null;
        return HttpHelper::buildResponse($response, ['id' => $qrId]);
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

        if (isset($params['node_relate_task']) && $params['node_relate_task'] == DssDictService::getKeyValue(DictConstants::NODE_SETTING, 'points_exchange_red_pack_id')){
            $data = UserPointsExchangeOrderWxModel::getRecords(['id' => explode(',', $params['award_id'])]);
        }else {
            $data = WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => explode(',', $params['award_id'])]);
        }
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
                'key' => 'scene_data',
                'type' => 'required',
                'error_code' => 'scene_data_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = BillMapService::mapDataRecord($params['scene_data'], $params['parent_bill_id'], $params['student_id']);
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
            UserService::recordUserActiveConsumer($params);
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
        return HttpHelper::buildResponse($response, $condition);
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
            $res = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList($params, $page, $limit,true);
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

    /**
     * 积分兑换红包列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pointsExchangeRedPackList(Request $request, Response $response)
    {
        try {
            $rules = [
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
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            list($page, $count) = Util::formatPageCount($params);
            $res = UserPointsExchangeOrderService::getList($params, $page, $count);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 手动发送积分兑换红包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function retryExchangeRedPack(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'points_exchange_order_wx_id',
                    'type' => 'required',
                    'error_code' => 'points_exchange_order_wx_id_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $res = UserPointsExchangeOrderService::retryExchangeRedPack($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 上传截图奖励明细列表
     * @param Request $request
     * @param Response $response
     */
    public function sharePostAwardList(Request $request, Response $response)
    {
        try {
            $rules = [
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
            list($page, $count) = Util::formatPageCount($params);
            $res = SharePosterService::sharePostAwardList($params['user_id'], $page, $count);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 截图列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function posterList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            if (!empty($params['type']) && $params['type'] == SharePosterModel::TYPE_CHECKIN_UPLOAD) {
                $params['type'] = SharePosterModel::TYPE_WEEK_UPLOAD;
            }
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            list($list, $total) = SharePosterModel::getPosterList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [$list, $total]);
    }

    /**
     * 截图上传
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function uploadSharePoster(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'student_id',
                    'type' => 'required',
                    'error_code' => 'student_id_is_required'
                ],
                [
                    'key' => 'activity_id',
                    'type' => 'required',
                    'error_code' => 'activity_id_is_required'
                ],
                [
                    'key' => 'image_path',
                    'type' => 'required',
                    'error_code' => 'image_path_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $id = SharePosterService::uploadSharePoster($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $id);
    }

    /**
     * 查询上传截图
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getSharePoster(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $sharePoster = SharePosterModel::getRecord($params['where'] ?? [], $params['field'] ?? []);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $sharePoster);
    }

    /**
     * 截图审核通过
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function approvalPoster(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'id',
                    'type' => 'required',
                    'error_code' => 'id_is_required'
                ],
                [
                    'key' => 'activity_id',
                    'type' => 'required',
                    'error_code' => 'activity_id_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $sharePoster = SharePosterService::approvalPoster($params['id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $sharePoster);
    }

    /**
     * 截图审核驳回
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refusedPoster(Request $request, Response $response)
    {
        try {
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
            $sharePoster = SharePosterService::refusedPoster($params['id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $sharePoster);
    }

    /**
     * 截图审核-活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activityList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            list($data, $total) = WeekActivityService::getSelectList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['activities' => $data, 'total_count' => $total]);
    }

    public static function parseUnique(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'unique_code',
                'type' => 'required',
                'error_code' => 'unique_code_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
           $data = RealSharePosterService::parseUnique($params['unique_code']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
    
    /**
     * 转介绍专属售卖落地页 - 好友推荐专属奖励
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function getAwardInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_is_integer',
            ],
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key' => 'package_id',
                'type' => 'integer',
                'error_code' => 'package_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = UserRefereeService::getAwardInfo($params);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Op::UserRefereeService::getAwardInfo error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * rt亲友优惠券活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rtActivityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            $ruleType = $params['rule_type'] ?? '';
            $page = 1;
            $count = 1000;
            $activityName = $params['name'] ?? '';
            $enableStatus = isset($params['enable_status']) ? explode(',', $params['enable_status']) : [];
            $activityList = RtActivityService::getRtActivityList($ruleType, $activityName, $page, $count, $enableStatus);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activityList);
    }

    /**
     * rt亲友优惠券活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rtActivityInfo(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            $activityIds = $params['activity_ids'] ?? '';
            $activityList = RtActivityService::getRtActivityInfo($activityIds);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activityList);
    }

    /**
     * rt亲友优惠券活动
     * 获取活动已绑定过的优惠券批次Id
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rtActivityCouponIdList(Request $request, Response $response)
    {
        try {
            $activityList = RtActivityService::getRtActivityCouponIdList();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activityList);
    }

    /**
     * rt亲友优惠券活动
     * 获取领取Rt学员优惠券的转介绍学员数量
     * 当前Rt学员优惠券未过期+当前阶段为付费体验课（有可用的优惠券，未付费的学员）
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rtActivityCouponUserList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'assistant_ids',
                'type' => 'required',
                'error_code' => 'assistant_ids_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $info = RtActivityService::rtActivityCouponUserList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $info);
    }



    /**
     * 获取海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRtPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'app_id',
                'type'       => 'required',
                'error_code' => 'app_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['type'] = RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN;
            $activity  = RtActivityService::getPoster($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * 批量获取转介绍人数
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getReferralNums(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'referee_ids',
                'type' => 'required',
                'error_code' => 'referee_ids_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = RtActivityService::getReferralNums($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * 获取button信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function buttonInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::getHtmlButtonInfo($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 获取海报列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function posterLists(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required',
            ]

        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::getPosterLists($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 分享语列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function posterWordLists(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::getPosterWordLists($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 跑马灯数据-获取用户金叶子相关信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userRewardDetails(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::userRewardDetails();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取banner信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function bannerInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = SourceMaterialService::bannerInfo($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 添加周周领奖白名单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createWeekWhiteList(Request $request, Response $response)
    {
        $params = $request->getParams();

        $rules = [
            [
                'key'           => 'uuids',
                'type'          => 'required',
                'error_code'    => 'uuids_is_required'
            ],
            [
                'key'           => 'operator_id',
                'type'          => 'required',
                'error_code'    => 'operator_id_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $uuids = explode("\n", trim($params['uuids'],"\n"));

        $operator_id = $params['operator_id'];

        $res = WeekWhiteListService::create($uuids, $operator_id);

        if($res){
            return HttpHelper::buildResponse($response, $res);
        }
        return HttpHelper::buildErrorResponse($response, $res);
    }


    /**
     * 获取列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekWhiteList(Request $request, Response $response){
        $params = $request->getParams();

        list($page, $pageSize) = Util::formatPageCount($params);

        $list = WeekWhiteListService::getWhiteList($params, $page, $pageSize);

        return HttpHelper::buildResponse($response, $list);

    }


    /**
     * 删除记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function delWeekWhite(Request $request, Response $response){
        $params = $request->getParams();
        $rules = [
            [
                'key'           => 'id',
                'type'          => 'required',
                'error_code'    => 'id_not_exist'
            ],
            [
                'key'           => 'operator_id',
                'type'          => 'required',
                'error_code'    => 'uuids_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $res = WeekWhiteListService::del($params['id'], $params['operator_id']);

        if($res){
            return HttpHelper::buildResponse($response,[]);
        }

        return HttpHelper::buildErrorResponse($response,['del_failure']);
    }

    /**
     * 操作记录列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWeekWhiteRecord(Request $request, Response $response){
        $params = $request->getParams();

        list($page, $pageSize) = Util::formatPageCount($params);

        $list = WhiteRecordService::list($params, $page, $pageSize);

        return HttpHelper::buildResponse($response, $list);

    }

    /**
     * 获取发放记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWhiteGrantRecord(Request $request, Response $response){
        $params = $request->getParams();

        list($page, $pageSize) = Util::formatPageCount($params);

        $list = WhiteGrantRecordService::list($params, $page, $pageSize);

        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 更新发放记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateGrantRecord(Request $request, Response $response){
        $params = $request->getParams();
        $rules = [
            [
                'key'           => 'id',
                'type'          => 'required',
                'error_code'    => 'id_not_exist'
            ],
            [
                'key'           => 'remark',
                'type'          => 'required',
                'error_code'    => 'remark_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $res = WhiteGrantRecordService::updateGrantRecord($params);

        if($res['code'] != Valid::CODE_SUCCESS){
            return $response->withJson($res);
        }

        return HttpHelper::buildResponse($response, []);
    }

    public function manualGrant(Request $request, Response $response){
        $params = $request->getParams();
        $rules = [
            [
                'key'           => 'id',
                'type'          => 'required',
                'error_code'    => 'id_not_exist'
            ],
        ];

        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $res = WhiteGrantRecordService::manualGrant($params);

        if($res['code'] != Valid::CODE_SUCCESS){
            return $response->withJson($res);
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 通用红包审核列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function awardRedPackList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_task_id',
                'type' => 'required',
                'error_code' => 'event_task_id_is_required'
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
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $count) = Util::formatPageCount($params);
        // 积分兑换红包的节点
        if ($params['event_task_id'] == DssDictService::getKeyValue(DictConstants::NODE_SETTING, 'points_exchange_red_pack_id')) {
            $returnList = UserPointsExchangeOrderService::getList($params, $page, $count);
        } else {
            $returnList = ErpUserEventTaskAwardService::awardRedPackList($params, $page, $count);
        }
        return HttpHelper::buildResponse($response, $returnList);
    }
}


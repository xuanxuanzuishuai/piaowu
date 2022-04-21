<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2022/4/12
 * Time: 3:11 下午
 */

namespace App\Controllers\StudentWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\Activity\Lottery\LotteryClientService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Lottery extends ControllerBase
{
    public function activityInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'op_activity_id',
                'type' => 'required',
                'error_code' => 'op_activity_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $activeInfo = LotteryClientService::activityInfo($params);
        return HttpHelper::buildResponse($response,$activeInfo);
    }

    /**
     * 开始抽奖
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function startLottery(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $tokenInfo = $this->ci['user_info'];
        $userInfo = StudentService::getUuid($tokenInfo['app_id'],$tokenInfo['user_id']);
        $params['student_id'] = $tokenInfo['user_id'];
        $params['uuid'] = $userInfo['uuid'];

        try {
            $hitAwardInfo = LotteryClientService::hitAwardInfo($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        $data = [
            'record_id'   => $hitAwardInfo['record_id'],
            'award_id'   => $hitAwardInfo['id'],
            'award_type' => $hitAwardInfo['type'],
            'award_level' => $hitAwardInfo['level'],
            'img_url'    => $hitAwardInfo['img_url'],
            'rest_times' => $hitAwardInfo['rest_times'],
        ];
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取指定用户的中奖记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function hitRecord(Request $request, Response $response)
    {
        $params = $request->getParams();
        $tokenInfo = $this->ci['user_info'];
        $userInfo = StudentService::getUuid($tokenInfo['app_id'],$tokenInfo['user_id']);
        list($page, $pageSize) = Util::formatPageCount($params);
        $hitRecord = LotteryAwardRecordService::getHitRecord($userInfo['uuid'],$page,$pageSize);
        $data = [
            'mobile'=>Util::hideUserMobile($userInfo['mobile']),
            'hit_list'=>$hitRecord,
        ];
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取收货地址信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function getAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'record_id',
                'type'       => 'required',
                'error_code' => 'record_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $detailAddress = LotteryClientService::getAddress($params['record_id']);
        $detailAddress['record_id'] = $params['record_id'];
        return HttpHelper::buildResponse($response, $detailAddress);
    }

    /**
     * 更新奖品收货地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function modifyAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'record_id',
                'type'       => 'required',
                'error_code' => 'record_id_is_required',
            ],
            [
                'key'        => 'erp_address_id',
                'type'       => 'required',
                'error_code' => 'erp_address_id_is_required',
            ],
            [
                'key'        => 'address_detail',
                'type'       => 'required',
                'error_code' => 'address_detail_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            LotteryClientService::modifyAddress($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 查看物流信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function shippingInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'record_id',
                'type'       => 'required',
                'error_code' => 'record_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $expressDetail = LotteryClientService::getExpressDetail($params);
        return HttpHelper::buildResponse($response, $expressDetail);
    }
}
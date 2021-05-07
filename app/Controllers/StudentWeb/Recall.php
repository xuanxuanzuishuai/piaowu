<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021-03-08
 * Time: 14:39
 */

namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\DssDictService;
use App\Services\RecallLandingService;
use App\Services\UserService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;

class Recall extends ControllerBase
{
    /**
     * 获取召回页面用token
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function getToken(Request $request, Response $response)
    {
        $params = $request->getParams();
        if (empty($params['uuid'])) {
            return HttpHelper::buildResponse($response, ['token' => '']);
        }

        $appId = DssUserWeiXinModel::dealAppId($params['app_id'] ?? null);
        $userType = Constants::USER_TYPE_STUDENT;
        $openId = null;
        $token = '';
        $student = null;
        $arr = [
            Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
        ];
        $busiType = $arr[$appId] ?? Constants::SMART_WX_SERVICE;
        try {
            if (Util::isWx() && empty($params['wx_code'])) {
                throw new RunTimeException(['need_wx_code']);
            }
            if (!empty($params['wx_code'])) {
                $data = WeChatMiniPro::factory($appId, $busiType)->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);
                $wxError = null;
                if (empty($data) || empty($data['openid'])) {
                    // 修复后退/刷新获取openid错误
                    $tokenHeader = $request->getHeader('token');
                    $tokenHeader = $tokenHeader[0] ?? null;
                    if (!empty($tokenHeader)) {
                        $tokenInfo = WechatTokenService::getTokenInfo($tokenHeader);
                        if (!empty($tokenInfo['user_id']) && !empty($tokenInfo['open_id'])) {
                            $student = DssStudentModel::getById($tokenInfo['user_id']);
                            if (!empty($student['uuid']) && $student['uuid'] == $params['uuid']) {
                                $token = $tokenHeader;
                            } else {
                                $wxError = 'can_not_obtain_open_id';
                            }
                        } else {
                            $wxError = 'can_not_obtain_open_id';
                        }
                    } else {
                        $wxError = 'can_not_obtain_open_id';
                    }

                    if (!is_null($wxError)) {
                        throw new RunTimeException([$wxError]);
                    }
                }
                $openId = $data['openid'] ?? null;
            }

            $student = empty($student) ? DssStudentModel::getRecord(['uuid' => $params['uuid']]) : $student;

            if (empty($student)){
                return HttpHelper::buildResponse($response, ['token' => '']);
            }

            if (!empty($student['id']) && empty($token)) {
                $token = WechatTokenService::generateToken(
                    $student['id'],
                    $userType,
                    $appId,
                    $openId
                );
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse(
            $response,
            [
                'mobile' => $student['mobile'],
                'token' => $token,
                'uuid' => $student['uuid']
            ]
        );
    }

    /**
     * 召回页数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response)
    {
        $params = $request->getParams();
        $packageId = $params['package_id'] ?? DssDictService::getKeyValue(DictConstants::DSS_WEB_STUDENT_CONFIG, 'mini_package_id_v1');
        $uuid = $params['uuid'] ?? '';
        try {
            $student = null;
            if (!empty($uuid) && empty($student)) {
                $student = DssStudentModel::getRecord(['uuid' => $uuid]);
                $params['mobile'] = $student['mobile'];
            }
            $data = RecallLandingService::getIndexData($packageId, $student, $params);
            $data['mobile'] = $params['mobile'] ?: $student['mobile'];
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}

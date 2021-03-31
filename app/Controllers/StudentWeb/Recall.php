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
        if (empty($params['mobile'])) {
            return HttpHelper::buildResponse($response, ['token' => '']);
        }
        $appId = DssUserWeiXinModel::dealAppId($params['app_id'] ?? null);
        $userType = Constants::USER_TYPE_STUDENT;
        $channelId = $params['channel_id'] ?? Constants::CHANNEL_WE_CHAT_SCAN;
        $openId = null;
        $token = '';
        $arr = [
            Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
        ];
        $busiType = $arr[$appId] ?? Constants::SMART_WX_SERVICE;
        if (!empty($params['wx_code'])) {
            $data = WeChatMiniPro::factory($appId, $busiType)->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);
            if (empty($data) || empty($data['openid'])) {
                throw new RunTimeException(['can_not_obtain_open_id']);
            }
            $openId = $data['openid'] ?? null;
        }

        $student = DssStudentModel::getRecord(['mobile' => $params['mobile']]);
        if (empty($student)) {
            $info = UserService::studentRegisterBound($appId, $params['mobile'], $channelId, $openId, $busiType, $userType, $params["referee_id"] ?? '');
            $student['id'] = $info['student_id'];
            $student['uuid'] = $info['uuid'];
        }

        if (!empty($student['id'])) {
            $token = WechatTokenService::generateToken(
                $student['id'],
                $userType,
                $appId,
                $openId
            );
        }
        return HttpHelper::buildResponse(
            $response,
            [
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
        $mobile = $params['mobile'] ?? '';
        try {
            $studentId = $this->ci['user_info']['user_id'] ?? 0;
            $student = null;
            if (!empty($studentId)) {
                $student = DssStudentModel::getById($studentId);
            }
            if (!empty($mobile) && empty($student)) {
                $student = DssStudentModel::getRecord(['mobile' => $mobile]);
            }
            $data = RecallLandingService::getIndexData($packageId, $student);
            $data['mobile'] = $params['mobile'] ?: $student['mobile'];
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}

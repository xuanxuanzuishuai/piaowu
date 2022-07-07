<?php

namespace App\Middleware\Client;

use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Middleware\MiddlewareBase;
use App\Models\Erp\ErpStudentAppModel;
use App\Services\UserService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * 客户端登陆态验证中间件：app/wx/h5所有的客户端登陆验证功能全部由此中间件完成
 */
class ClientAuthMiddleware extends MiddlewareBase
{

    /**
     * 校验登陆态
     * @param Request $request
     * @param Response $response
     * @param $next
     * @return Response
     * @throws RunTimeException
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        //header头中携带业务线AppId和FromType参数，依据不同业务线和不同平台类型获取登陆验证标识
        $appId = (int)$request->getHeader('AppId')[0];
        $fromType = $request->getHeader('FromType')[0];
        if (empty($appId) || empty($fromType)) {
            throw new RunTimeException(['app_id_or_from_type_miss']);
        }
        //检测业务线和平台类型
        self::checkFromType($fromType);
        self::checkAppId($appId);
        //验证登陆态以及账户信息
        switch ($fromType) {
            case Constants::FROM_TYPE_SMART_STUDENT_APP:
            case Constants::FROM_TYPE_SMART_STUDENT_H5:
            case Constants::FROM_TYPE_REAL_STUDENT_APP:
            case Constants::FROM_TYPE_REAL_STUDENT_H5:
                throw new RunTimeException(['middleware_undo']);
            case Constants::FROM_TYPE_SMART_STUDENT_WX:
                self::smartStudentWxAuthCheck($request);
                break;
            case Constants::FROM_TYPE_REAL_STUDENT_WX:
                self::realStudentWxAuthCheck($request);
                break;
        }
        $this->container['from_type'] = $fromType;
        if (!isset($this->container['user_info']) || empty($this->container['user_info'])) {
            throw new RunTimeException(['user_invalid']);
        }
        return $next($request, $response);
    }

    /**
     * 检测请求平台类型
     * @param $fromType
     * @throws RunTimeException
     */
    private function checkFromType($fromType)
    {
        //判断平台类型
        if (!in_array($fromType, [
            Constants::FROM_TYPE_REAL_STUDENT_APP,
            Constants::FROM_TYPE_REAL_STUDENT_WX,
            Constants::FROM_TYPE_REAL_STUDENT_H5,
            Constants::FROM_TYPE_SMART_STUDENT_APP,
            Constants::FROM_TYPE_SMART_STUDENT_WX,
            Constants::FROM_TYPE_SMART_STUDENT_H5,
        ])) {
            throw new RunTimeException(['from_type_error']);
        }
    }

    /**
     * 检测业务线ID
     * @param $appId
     * @throws RunTimeException
     */
    private function checkAppId($appId)
    {
        //判断业务线
        if (!in_array($appId, [
            Constants::SMART_APP_ID,
            Constants::REAL_APP_ID
        ])) {
            throw new RunTimeException(['app_id_error']);
        }
    }

    /**
     * 真人学生微信公众号登陆信息校验
     * @param Request $request
     * @throws RunTimeException
     */
    private function realStudentWxAuthCheck(Request $request)
    {
        //获取header头中账户ID数据
        $userId = (int)$request->getHeader('UserId')[0];
        if (empty($userId)) {
            throw new RunTimeException(['user_id_required']);
        }
        //设置账户ID到全局容器中
        $userData = ErpStudentAppModel::getRecord(['student_id' => $userId, 'app_id' => Constants::REAL_APP_ID],
            ['student_id(user_id)', 'status', 'first_pay_time']);
        $this->container['user_info'] = $userData;
    }

    /**
     * 智能微信公众号登陆信息校验
     * @param Request $request
     * @throws RunTimeException
     */
    private function smartStudentWxAuthCheck(Request $request)
    {
        //获取header头中账户token
        $token = $request->getHeader('token')[0];
        //token为空
        if (empty($token)) {
            throw new RunTimeException(['token_required']);
        }
        //验证token存储的账户信息
        $userInfo = WechatTokenService::getTokenInfo($token);
        if (empty($userInfo)) {
            throw new RunTimeException(['token_expired']);
        }
        //当前系统对应的应用busi_type
        $arr = [
            Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
        ];
        $busiType = $arr[$userInfo['app_id']] ?? Constants::SMART_WX_SERVICE;
        if (!empty($userInfo['open_id'])) {
            $weiXinInfo = UserService::getUserWeiXinInfoAndUserId($userInfo['app_id'], $userInfo['user_id'],
                $userInfo['open_id'], $userInfo['user_type'], $busiType);
            //是否还有绑定关系
            if (empty($weiXinInfo)) {
                $weiXinInfo = (new Dss())->getWeixinInfo([
                    'user_id'   => $userInfo['user_id'],
                    'open_id'   => $userInfo['open_id'],
                    'user_type' => $userInfo['user_type'],
                    'busi_type' => $busiType
                ]);
            }
            if (empty($weiXinInfo)) {
                throw new RunTimeException(['token_expired']);
            }
            SimpleLogger::info('UserInfo: ', ["token" => $token, "userInfo" => $userInfo]);
        }
        WechatTokenService::refreshToken($token);
        $this->container['user_info'] = $userInfo;
    }
}

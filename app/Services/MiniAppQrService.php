<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/1/27
 * Time: 11:07 AM
 */

namespace App\Services;


use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\ParamMapModel;
use App\Models\UserWeiXinModel;

/**
 * 小程序二维码管理类
 * Class MiniAppQrService
 * @package App\Services
 */
class MiniAppQrService
{
    //代理商小程序转介绍票据前缀
    const AGENT_TICKET_PREFIX = 'agent_ticket_';

    /**
     * 获取用户QR码图片路径：运营系统代理商小程序(图片保存到aliOss)
     * @param $userId
     * @param array $extParams
     * @return array|mixed
     */
    public static function getAgentQRAliOss($userId, $extParams = [])
    {
        //应用ID
        $appId = UserCenter::AUTH_APP_ID_OP_AGENT;
        //二维码识别后跳转地址类型
        $landingType = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        //用户类型
        $type = ParamMapModel::TYPE_AGENT;
        //获取学生转介绍学生二维码资源数据
        $paramInfo = [
            'c' => $extParams['c'] ?? "0",//渠道ID
            'a' => $extParams['a'] ?? "0",//活动ID
            'e' => $extParams['e'] ?? "0",//员工ID
            'p' => $extParams['p'] ?? "0",//海报ID：二维码智能出现在特殊指定的海报
            'lt' => $landingType,//二维码类型
        ];
        //检测二维码是否已存在
        $res = ParamMapModel:: getQrUrl($userId, $appId, $type, $paramInfo);
        if (!empty($res['qr_url'])) {
            return $res['qr_url'];
        }
        //生成小程序码
        try {
            $userQrTicket = self::AGENT_TICKET_PREFIX . RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userId);
            $paramInfo['r'] = $userQrTicket;
            $params = array_merge($paramInfo, ['app_id' => $appId, 'type' => $type, 'user_id' => $userId]);
            $qrData =PosterService::getMiniappQrImage($appId, $params, UserWeiXinModel::BUSI_TYPE_AGENT_MINI);
            if (empty($qrData[0])) {
                return '';
            }
            //记录二维码图片地址数据
            ParamMapModel::updateParamInfoQrUrl($qrData[1], $qrData[0]);
        } catch (\Exception $e) {
            SimpleLogger::error('make agent qr image exception', [print_r($e->getMessage(), true)]);
            return '';
        }
        return $qrData[0];
    }
}
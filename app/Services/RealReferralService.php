<?php


namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Referral;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserWeiXinModel;

class RealReferralService
{

    const STATUS_NORMAL = 1;

    const NOT_LOGIN_ZH = '未登录';

    const NOT_BIND_ZH = '未绑定';

    /**
     * 解密手机号
     * @param $iv
     * @param $encryptedData
     * @param $sessionKey
     * @return mixed|null
     */
    public static function decodeMobile($iv, $encryptedData, $sessionKey)
    {
        if (empty($sessionKey)) {
            SimpleLogger::error('session key is empty', []);
            return null;
        }
        $appId    = Constants::REAL_APP_ID;
        $busiType = Constants::REAL_MINI_BUSI_TYPE;
        $weChat   = WeChatMiniPro::factory($appId, $busiType);
        $appId    = DictConstants::get(DictConstants::WECHAT_APPID, $weChat->nowWxApp);
        $w        = new WXBizDataCrypt($appId, $sessionKey);
        $code     = $w->decryptData($encryptedData, $iv, $data);

        if ($code == 0) {
            return json_decode($data, $data);
        }

        SimpleLogger::error("DECODE MOBILE ERROR", compact('iv', 'encryptedData', 'sessionKey'));
        return null;
    }

    /**
     * 小程序首页
     * @param $params
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function index($params)
    {
        //获取学生列表
        $studentLists      = self::getStudentLists();
        //查询是否绑定
        $userWeiXin = ErpUserWeiXinModel::getUserInfoByOpenId($params['open_id'], Constants::REAL_MINI_BUSI_TYPE);
        //获取转介绍相关信息
        if (!empty($params['qr_id'])) {
            $qrData = MiniAppQrService::getQrInfoById($params['qr_id'], ['user_id', 'channel_id', 'poster_id']);
            //获取推荐人相关信息
            $referrerInfo = self::getReferralIndexData($qrData['user_id']);
        }
        $data['mobile']         = $userWeiXin['mobile'] ?? 0;
        $data['poster_id']      = $qrData['poster_id'] ?? 0;
        $data['channel_id']     = $qrData['channel_id'] ?? 0;
        $data['is_bind']        = $userWeiXin['user_id'] ? true : false;
        $data['top_info']       = $studentLists;
        $data['referrer_info']  = $referrerInfo ?? [];
        return $data;
    }

    /**
     * 获取学生状态
     * @param $studentId
     * @return array|string[]
     */
    public static function getStudentStatus($studentId)
    {
        //查询是否注册
        if (empty($studentId)) {
            return ['student_status' => self::NOT_LOGIN_ZH];
        }
        //查询是否绑定
        $userWeiXin = ErpUserWeiXinModel::getRecord(['user_id' => $studentId, 'status' => self::STATUS_NORMAL]);
        if (empty($userWeiXin)) {
            return ['student_status' => self::NOT_BIND_ZH];
        }
        //获取学生状态
        $studentStatus = (new Referral())->getStudentStatus(['student_id' => $studentId]);
        return ['student_status' => $studentStatus];
    }

    /**
     * 获取学生列表
     * @return string[]
     */
    public static function getStudentLists()
    {
        $where        = [
            'ORDER' => [
                'id' => 'DESC'
            ],
            'LIMIT' => [0, 50]
        ];
        $studentLists = ErpStudentModel::getRecords($where, ['id', 'mobile']);
        $studentLists = array_map(function ($v) {
            return substr($v['mobile'], strlen($v['mobile']) - 4, 2) . '**';
        }, $studentLists);
        return $studentLists;
    }

    /**
     * 获取转介绍小程序推荐人首页数据
     * @param $refereeId
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getReferralIndexData($refereeId)
    {
        $result = [
            'nick_name'    => '',
            'thumb'        => '',
            'play_days'    => 0,
            'register_num' => 0,
        ];
        //账户数据
        $userData = ErpStudentModel::getUserInfo($refereeId);
        if (empty($userData)) {
            return $result;
        }
        $userData = end($userData);
        //微信数据
        $where  = [
            'user_id'   => $refereeId,
            'user_type' => Constants::USER_TYPE_STUDENT,
            'busi_type' => Constants::REAL_MINI_BUSI_TYPE,
            'app_id'    => Constants::REAL_APP_ID,
            'ORDER'     => [
                'id' => 'DESC'
            ],
        ];
        $openId = ErpUserWeiXinModel::getRecord($where, ['open_id']);
        if (!empty($openId)) {
            $wechat = WeChatMiniPro::factory(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE);

            $wechatInfo = $wechat->getUserInfo($openId);
            if (!empty($wechatInfo) && is_null($wechatInfo['errcode'])) {
                $result['nick_name'] = $wechatInfo['nickname'];
                $result['thumb']     = $wechatInfo['headimgurl'];
            }
        }
        if (empty($result['nick_name']) && empty($result['thumb'])) {
            $result['nick_name'] = $userData['name'];
            if (!empty($userData['thumb'])) {
                $result['thumb'] = $_ENV['QINIU_DOMAIN_ERP'] . $userData['thumb'];
            }
        }
        if (!empty($userData['first_pay_time'])) {
            $days = (strtotime(date("Y-m-d 00:00:00", time())) - strtotime(date("Y-m-d 00:00:00",
                        $userData['first_pay_time']))) / 86400;
            $result['play_days'] = $days + 1;
        }
        if (empty($result['thumb'])) {
            $thumb           = DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb');
            $result['thumb'] = AliOSS::replaceCdnDomainForDss($thumb);
        }
        if (empty($result['nick_name'])) {
            $result['nick_name'] = '你的好友';
        } else {
            $result['nick_name'] = mb_substr($result['nick_name'], 0, 7);
        }
        //注册人数
        $result['register_num'] = ErpStudentAppModel::getRegisterRoughCount();
        return $result;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/3/5
 * Time: 6:01 下午
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\MsgErrorCode;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Libs\WeChat\WXBizMsgCrypt;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssAiPlayRecordModel;
use App\Models\Dss\DssAuthModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Models\UserWeiXinModel;

class ShowMiniAppService
{
    // 用户首次进入评测3.0
    const LANDING_PAGE_PLAY_REVIEW = 'PLAY_REVIEW_LANDING_PAGE_FIRST';

    /**
     * 解析scene参数
     * @param $scene
     * @return array|void
     */
    public static function getSceneData($scene)
    {
        if (empty($scene)) {
            return [];
        }
        $scene = urldecode($scene);
        $sceneData = [];
        if (substr($scene, 0, 3) == '&r=') {
            parse_str($scene, $sceneData);
        } elseif (ctype_alnum($scene) && strlen($scene) >= 6) {
            $sceneData = MiniAppQrService::getQrInfoById($scene);
        } elseif (strpos($scene, 'param_id')) {
            parse_str($scene, $params);
            $sceneData = ReferralActivityService::getParamsInfo($params['param_id']);
            $sceneData['param_id'] = $params['param_id'] ?? 0;
        } else {
            $tmp = explode('&', $scene);
            $sceneData['r'] = $tmp[0] ?? ''; // referral ticket
            $sceneData['c'] = $tmp[1] ?? ''; // channel id
            $sceneData['a'] = $tmp[2] ?? ''; // activity id
            $sceneData['e'] = $tmp[3] ?? ''; // employee id
        }
        return $sceneData;
    }

    /**
     * @param $sceneData
     * @param $openid
     * @return array
     *
     */
    public static function getMiniAppPlayReviewData($sceneData, $openid)
    {
        $data = [];
        $data['had_purchased'] = false;
        $data['had_bind'] = false;
        $data['mobile'] = '';
        $data['openid'] = $openid;
        $data['uuid'] = '';

        // 是否是首次进入页面标记
        $redis = RedisDB::getConn();
        if ($redis->hexists(self::LANDING_PAGE_PLAY_REVIEW, $openid)) {
            $data['remaining'] = 1;
        } else {
            $data['remaining'] = rand(20, 30);
            $redis->hset(self::LANDING_PAGE_PLAY_REVIEW, $openid, time());
        }

        // 推荐人信息：
        if (!empty($sceneData['r'])) {
            $referrerUserId = Util::decryptQrTicketInfo($sceneData['r'])['user_id'] ?? '';;
        } else {
            $referrerUserId = null;
        }
        $data['referrer_info'] = self::getReferrerInfoForMinApp($referrerUserId);

        // 最近体验课购买信息
        $v1TrialPackageIdArr = DssErpPackageV1Model::getTrailPackageIds();
        $recentPurchase = DssGiftCodeModel::getRecords(
            [
                'bill_package_id' => $v1TrialPackageIdArr,
                'LIMIT'           => [0, 50],
                'ORDER'           => ['id' => 'DESC'],
            ],
            ['buyer', 'create_time']
        );
        $data['recent_purchase'] = self::formatRecentPurchase($recentPurchase);

        //检查用户绑定微信，购买体验课信息
        $mobile = DssUserWeiXinModel::getUserInfoBindWX($openid);
        if (!empty($mobile) && isset($mobile[0]['mobile'])) {
            $data['mobile'] = $mobile[0]['mobile'];
            $data['had_bind'] = true;
            $data['uuid'] = $mobile[0]['uuid'];
            $data['had_purchased'] = !empty(DssGiftCodeModel::hadPurchasePackageByType($mobile[0]['id'], DssPackageExtModel::PACKAGE_TYPE_TRIAL, false));
        }

        $reportInfo = AIPlayRecordService::getStudentAssessData($sceneData['play_id']);
        $data['report'] = [
            'audio_url'           => $reportInfo['audio_url'] ?? '',
            'thumb'               => $reportInfo['thumb'] ?? '',
            'name'                => $reportInfo['name'] ?? '',
            'lesson_name'         => $reportInfo['lesson_name'] ?? '',
            'score_final'         => $reportInfo['score_final'] ?? '',
            'score_complete'      => $reportInfo['score_complete'] ?? '',
            'score_pitch'         => $reportInfo['score_pitch'] ?? '',
            'score_rhythm'        => $reportInfo['score_rhythm'] ?? '',
            'score_speed'         => $reportInfo['score_speed'] ?? '',
            'score_speed_average' => $reportInfo['score_speed_average'] ?? '',
            'score_rank'          => $reportInfo['score_rank'] ?? '',
            'lesson_id'           => $reportInfo['lesson_id'] ?? '',
            'record_id'           => $reportInfo['record_id'] ?? '',
            'replay_token'        => $reportInfo['replay_token'] ?? '',
        ];
        $data['scene_data'] = $sceneData;
        $data['pkg'] = self::canUserReferral001Package($data['referrer_info']['has_review_course'] ?? 0, $sceneData['c']) ? PayServices::PACKAGE_1 : PayServices::PACKAGE_990;
        return $data;
    }

    /**
     * 获取推荐人信息及推荐文案
     * @param $user_id
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getReferrerInfoForMinApp($user_id)
    {
        $percentage = $accumulateDays = 0;
        $data = [
            'nickname'   => '小叶子',
            'headimgurl' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb')),
        ];
        // 推荐人不存在时返回默认数据
        if ($user_id) {
            $referrerUuid = DssStudentModel::getRecord(['id' => $user_id], ['uuid', 'has_review_course']);
            $data['uuid'] = $referrerUuid['uuid'] ?? '';
            $data['has_review_course'] = $referrerUuid['has_review_course'] ?? '';
            $referrerInfo = DssUserWeiXinModel::getRecord(
                [
                    'user_id'   => $user_id,
                    'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
                    'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
                    'status'    => DssUserWeiXinModel::STATUS_NORMAL,
                    'ORDER'     => ['id' => 'DESC'],
                ],
                ['open_id']
            );
            if (!empty($referrerInfo['open_id'])) {
                // 获取用户微信昵称和头像
                $wxData = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER)->getUserInfo($referrerInfo['open_id']);
                if (!empty($wxData['headimgurl'])) {
                    $data['nickname'] = $wxData['nickname'];
                    $data['headimgurl'] = $wxData['headimgurl'];
                }
            }
        }
        return $data;
    }

    /**
     * 处理最近购买文案
     * @param $recentPurchase
     * @return array
     */
    public static function formatRecentPurchase($recentPurchase)
    {
        $now = time();
        $data = [];
        $buyerInfo = DssStudentModel::getRecords(['id' => array_column($recentPurchase, 'buyer')], ['id', 'name']);
        $buyerInfo = array_combine(array_column($buyerInfo, 'id'), array_column($buyerInfo, 'name'));
        foreach ($recentPurchase as $item) {
            $diff = $now - $item['create_time'];
            if (!isset($buyerInfo[$item['buyer']])) {
                continue;
            }
            $buyerInfo[$item['buyer']] = self::dealShowName($buyerInfo[$item['buyer']]);
            $dict = [
                [
                    'value' => 60,
                    'label' => '<span>刚刚成功购买</span>'
                ],
                [
                    'value' => 59 * 60,
                    'label' => "<span>" . (bcdiv($diff, 60) ?: 1) . "分钟前成功购买</span>",
                ],
                [
                    'value' => 24 * 60 * 60,
                    'label' => "<span>" . (bcdiv($diff, 60 * 60) ?: 1) . "小时前成功购买</span>",
                ],
                [
                    'value' => 100 * 24 * 60 * 60,
                    'label' => "<span>" . date('m-d', $item['create_time']) . '成功购买</span>'
                ],
            ];
            foreach ($dict as $key => $value) {
                if ($diff <= $value['value'] && isset($buyerInfo[$item['buyer']])) {
                    $data[] = $buyerInfo[$item['buyer']] . " " . $value['label'];
                    break;
                }
            }
        }
        return $data;
    }

    /**
     * @param $name
     * @return string
     * 隐藏展示的用户名
     */
    public static function dealShowName($name)
    {
        $count = mb_strlen($name);
        if ($count == 1) {
            return $name . '*';
        } elseif ($count == 2) {
            return mb_substr($name, 0, 1) . '*';
        } elseif ($count > 2) {
            return mb_substr($name, 0, 1) . str_repeat('*', $count - 2) . mb_substr($name, -1, 1);
        }
    }

    /**
     * 根据推荐人和渠道判断是否可购买0.01元课包
     * @param $hasReviewCourse
     * @param $channelId
     * @return bool
     */
    public static function canUserReferral001Package($hasReviewCourse, $channelId)
    {
        // DSSCRM-1841:
        if ($hasReviewCourse == DssStudentModel::REVIEW_COURSE_1980) {
            return true;
        }
        return false;
    }

    /**
     * 注册
     * @param $openId
     * @param $iv
     * @param $encryptedData
     * @param $sessionKey
     * @param $mobile
     * @param $countryCode
     * @param $referrerId
     * @param string $channel
     * @param array $extParams
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function remoteRegister(
        $openId,
        $iv,
        $encryptedData,
        $sessionKey,
        $mobile,
        $countryCode,
        $referrerId,
        $channel = '',
        $extParams = []
    )
    {
        if (!empty($encryptedData)) {
            $jsonMobile = self::decodeMobile($iv, $encryptedData, $sessionKey);
            if (empty($jsonMobile)) {
                return [$openId, 0, null];
            }
            $mobile = $jsonMobile['purePhoneNumber'];
        }
        //检测用户是否已存在
        $studentExists = DssStudentModel::getRecord(['mobile' => $mobile], ['id']);
        if (!empty($studentExists)) {
            StudentService::studentLoginActivePushQueue(Constants::SMART_APP_ID, $studentExists['id'], Constants::DSS_STUDENT_LOGIN_TYPE_SHOW_MINI);
        }
        $userInfo = (new Dss())->studentRegisterBound([
            'mobile'       => $mobile,
            'channel_id'   => $channel ?: DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT'),
            'open_id'      => $openId,
            'busi_type'    => UserWeiXinModel::BUSI_TYPE_SHOW_MINI,
            'user_type'    => UserWeiXinModel::USER_TYPE_STUDENT,
            'referee_id'   => $referrerId,
            'country_code' => $countryCode,
            'ext_params'   => $extParams
        ]);
        $lastId = $userInfo['student_id'];
        $uuid = $userInfo['uuid'];

        $hadPurchased = !empty(DssGiftCodeModel::hadPurchasePackageByType($lastId, DssPackageExtModel::PACKAGE_TYPE_TRIAL, false));
        return [$openId, $lastId, $mobile, $uuid, $hadPurchased];
    }

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
        $appId = DictConstants::get(DictConstants::WECHAT_APPID, '8_10');
        $w = new WXBizDataCrypt($appId, $sessionKey);
        $code = $w->decryptData($encryptedData, $iv, $data);
        if ($code == 0) {
            return json_decode($data, 1);
        } else {
            SimpleLogger::error('decode mobile error:', ['code' => $code]);
            return null;
        }
    }

    /**
     * 客服消息接收及回复
     * @param $params
     * @param $postData
     * @return int
     */
    public static function miniAppNotify($params, $postData)
    {
        $appId = DictConstants::get(DictConstants::WECHAT_APPID, '8_10');
        $wx = new WXBizMsgCrypt(
            DictConstants::get(DictConstants::WECHAT_APP_PUSH_CONFIG, '8_10_token'),
            DictConstants::get(DictConstants::WECHAT_APP_PUSH_CONFIG, '8_10_encoding_aes_key'),
            $appId
        );

        $code = $wx->decryptMsg(
            $params['msg_signature'],
            $params['timestamp'],
            $params['nonce'],
            $postData,
            $msg
        );

        if ($code != MsgErrorCode::$OK) {
            $params['code'] = $code;
            SimpleLogger::error('decrypt msg error', $params);
            return "success";
        }

        SimpleLogger::info('referral minapp server msg', ['msg' => $msg]);
        $ele = simplexml_load_string($msg);
        switch (trim((string)$ele->Content)) {
            case '1':
                // 回复助教二维码
                $openid = trim((string)$ele->FromUserName);
                $userType = DssUserWeiXinModel::USER_TYPE_STUDENT;
                $status = DssUserWeiXinModel::STATUS_NORMAL;
                $busiType =  DssUserWeiXinModel::BUSI_TYPE_SHOW_MINAPP;
                $assistant = DssUserWeiXinModel::getWxQr($openid, $userType, $status, $busiType)[0];

                if (empty($assistant['wx_qr'])) {
                    SimpleLogger::error('assistant\'s wx_qr image is empty', ['student_id' => (string)$ele->FromUserName]);
                    return "success";
                }

                $wx = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,$busiType);
                if (empty($wx)) {
                    SimpleLogger::error('wx create fail', ['appId' => $appId, 'we_chat_type'=>$userType]);
                    return "success";
                }
                $data = $wx->getTempMedia('image', $assistant['wx_qr'], AliOSS::replaceCdnDomainForDss($assistant['wx_qr']));

                if (!empty($data['media_id'])) {
                    $content = "请点击二维码后，长按二维码图片添加助教微信。若无法添加微信，请将二维码保存本地后，使用微信「扫一扫」添加助教微信";
                    $wx->sendImage((string)$ele->FromUserName, $data['media_id']);
                    $wx->sendText((string)$ele->FromUserName, $content);
                }
                return "success";
        }
        //转到客服消息
        if (in_array((string)$ele->MsgType, ['text', 'image', 'link', 'miniprogrampage'])) {
            $xmlString = $wx->transfer2Server((string)$ele->FromUserName, (string)$ele->ToUserName, (string)$ele->CreateTime);
            SimpleLogger::info("transfer to server:", ['content' => $xmlString]);
            return $xmlString;
        }
        //剩下的信息都回success
        return "success";
    }

    /**
     * 生成用于访问AIBackend服务的token
     * 格式为 AI_abc123
     * @param $studentId
     * @return string
     */
    public static function genStudentToken($studentId)
    {
        $token = 'AI' . '_' . DssAuthModel::randomToken();
        $redis = RedisDB::getConn($_ENV['DSS_REDIS_DB']);
        $redis->setex($token, DssAuthModel::TOKEN_EXPIRE_HOUR, $studentId);
        return $token;
    }
}
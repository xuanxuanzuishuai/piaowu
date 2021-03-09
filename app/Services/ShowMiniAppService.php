<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/3/5
 * Time: 6:01 下午
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssAiPlayRecordModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\UserWeixinModel;

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
            $referrerInfo = DssUserQrTicketModel::getRecord(['qr_ticket' => $sceneData['r']], ['user_id']);
            $referrerUserId = $referrerInfo['user_id'];
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
            $referrerInfo = UserWeixinModel::getRecord(
                [
                    'user_id'   => $user_id,
                    'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
                    'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
                    'status'    => UserWeixinModel::STATUS_NORMAL,
                    'ORDER'     => ['id' => 'DESC'],
                ],
                ['open_id']
            );
            if (!empty($referrerInfo['open_id'])) {
                // 获取用户微信昵称和头像
                $wxData = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,UserWeixinModel::BUSI_TYPE_STUDENT_SERVER)->getUserInfo($referrerInfo['open_id']);
                if (!empty($wxData['headimgurl'])) {
                    $data['nickname'] = $wxData['nickname'];
                    $data['headimgurl'] = $wxData['headimgurl'];
                }
                // 练琴天数
                $accumulateDays = DssAiPlayRecordModel::getAccumulateDays($user_id);
                // 查询推荐者练习曲目
                [$percentage, $numbers] = self::getReferrerPlayData($user_id);
            }
        }
        //推荐文案
        if ($percentage && $accumulateDays) {
            $data['text'] = sprintf('我家宝贝在小叶子练琴<span>%d</span>天，弹奏得分提升了<span>%d%%</span>，就连老师都说进步飞快，值得推荐！', $accumulateDays, $percentage);
        } elseif ($accumulateDays >= 5) {
            $data['text'] = sprintf('我家宝贝在小叶子练琴<span>%d</span>天，共练习了<span>%d</span>首曲子，错音变少了，也学会了主动练琴，值得推荐！', $accumulateDays, $numbers);
        } else {
            $data['text'] = '自从有了小叶子，孩子错音变少了，学会主动练琴了，也给你家宝贝一个提升的机会！';
        }
        return $data;
    }

    /**
     * 获取推荐人练琴提升百分比和练琴曲目数
     * @param $referrer_id
     * @return array
     */
    public static function getReferrerPlayData($referrer_id)
    {
        // 练习曲目数量
        $numbers = DssAiPlayRecordCHModel::getStudentLessonCount($referrer_id);
        $numbers = $numbers[0]['lesson_count'] ?? 0;
        // 练琴提升百分比
        $percentage = 0;
        $minScore = 1;
        $result = DssAiPlayRecordCHModel::getStudentMaxAndMinScore($referrer_id);
        foreach ($result as $key => $value) {
            $diff = $value['max_score'] - $value['min_score'];
            if ($diff > $percentage && $diff > 10) {
                $percentage = $diff;
                $minScore = $value['min_score'];
            }
        }
        if (empty($percentage)) {
            return [$percentage, $numbers];
        }
        return [round($percentage / $minScore * 100), $numbers];
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

        $stu = DssStudentModel::getStudentInfo(null, $mobile);
        if (empty($stu)) {
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
        } else {
            $lastId = $stu['id'];
            $uuid = $stu['uuid'];
        }

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
        $w = new WXBizDataCrypt($_ENV['REFERRAL_LANDING_APP_ID'], $sessionKey);
        $code = $w->decryptData($encryptedData, $iv, $data);
        if ($code == 0) {
            return json_decode($data, 1);
        } else {
            SimpleLogger::error('decode mobile error:', ['code' => $code]);
            return null;
        }
    }
}
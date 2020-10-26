<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/21
 * Time: 2:32 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\CHDB;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\Helper;
use App\Libs\WeChat\MsgErrorCode;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Libs\WeChat\WXBizMsgCrypt;
use App\Models\AIPlayRecordModel;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\ReferralModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\UserQrTicketModel;
use App\Models\UserWeixinModel;

class ReferralService
{
    /** 转介绍奖励服务时长 */
    const REWORDS_SUB_NUM = 7;
    const REWORDS_SUB_UNITS = GiftCodeModel::CODE_TIME_DAY;

    // 用户首次进入页面记录
    const LANDING_PAGE_USER_FIRST_KEY = 'REFERRAL_LANDING_PAGE_FIRST';

    // 转介绍小程序
    const REFERRAL_MINIAPP_ID = 2;

    /**
     * 添加转介绍记录
     * @param int $referrerId 介绍人id
     * @param int $refereeId 被介绍人id
     * @param int $type 转介绍类型
     * @throws RunTimeException
     */
    public static function addReferral($referrerId, $refereeId, $type)
    {
        $id = ReferralModel::insertRecord([
            'referrer_id' => $referrerId,
            'referee_id' => $refereeId,
            'type' => $type,
            'create_time' => time(),
            'given_rewards' => Constants::STATUS_FALSE,
            'given_rewards_time' => null,
        ], false);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }
    }

    /**
     * 检查转介绍奖励
     * @param int $refereeId 被介绍人
     * @param int $type 转介绍类型
     * @return bool 是否发送奖励
     */
    public static function checkReferralRewards($refereeId, $type)
    {
        $referral = ReferralModel::getByRefereeId($refereeId, $type);
        if (empty($referral)) {
            return false;
        }
        if ($referral['given_rewards'] == Constants::STATUS_TRUE) {
            return false;
        }

        // 发送奖励失败不影响正常流程, 发送错误记录到sentry
        try {
            switch ($type) {
                case ReferralModel::REFERRAL_TYPE_WX_SHARE:
                    self::getRewordsSubDuration($referral['referrer_id']);
                    break;
                default:
                    throw new RunTimeException(['referral_type_is_invalid']);
            }

            ReferralModel::updateRecord($referral['id'], [
                'given_rewards' => Constants::STATUS_TRUE,
                'given_rewards_time' => time()
            ], false);

        } catch (RunTimeException $e) {
            // 发送奖励时记录错误，不影响正常流程
            $e->sendCaptureMessage([
                '$referral' => $referral,
            ]);

            return true;
        }

        return true;
    }

    /**
     * 发送奖励时长
     * @param $referrerId
     * @throws RunTimeException
     */
    public static function getRewordsSubDuration($referrerId)
    {
        GiftCodeService::createByStudent(
            self::REWORDS_SUB_NUM,
            self::REWORDS_SUB_UNITS,
            GiftCodeModel::BUYER_TYPE_REFERRAL,
            $referrerId,
            GiftCodeModel::CREATE_BY_SYSTEM,
            EmployeeModel::SYSTEM_EMPLOYEE_ID,
            true,
            'referral_gift',
            time()
        );
    }

    /**
     * 获取推荐列表
     * @param int $referrerId 推荐人id
     * @return array
     */
    public static function ReferralList($referrerId)
    {
        $referrals = ReferralModel::getListByReferrerId($referrerId, ReferralModel::REFERRAL_TYPE_WX_SHARE);
        if(empty($referrals)) {
            return [];
        }

        $list = [];
        foreach ($referrals as $r) {
            $list[] = [
                'name' => $r['referee_name'],
                'mobile' => Util::hideUserMobile($r['referee_mobile']),
                'reg_time' => $r['create_time'],
                'given_rewards' => $r['given_rewards'] ? 1 : 0,
            ];
        }

        return ['list' => $list];
    }

    /**
     * 获取转介绍小程序landing页面数据
     * @param $referrer_ticket
     * @param $openid
     * @return array
     */
    public static function getLandingPageData($referrer_ticket, $openid)
    {
        $data                  = [];
        $data['had_purchased'] = false;
        $data['mobile']        = '';
        $data['openid']        = $openid;
        $data['uuid']          = '';
        // 推荐人信息：
        if (!empty($referrer_ticket)) {
            $referrerInfo = UserQrTicketModel::getRecord(['qr_ticket' => $referrer_ticket], ['user_id']);
            $user_id = $referrerInfo['user_id'];
        } else {
            $user_id = null;
        }
        $data['referrer_info'] = self::getReferrerInfoForMinApp($user_id);
        // 转介绍注册时用的推荐人ticket参数
        $data['referrer_info']['ticket'] = $referrer_ticket;

        // 是否是首次进入页面标记
        $redis = RedisDB::getConn();
        $data['first_flag'] = $redis->hget(self::LANDING_PAGE_USER_FIRST_KEY, $openid) ? false : true;
        if ($data['first_flag']) {
            $redis->hset(self::LANDING_PAGE_USER_FIRST_KEY, $openid, time());
        }
        // 最近购买信息
        $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]), 'package_id');

        $recentPurchase = GiftCodeModel::getRecords(
            [
                'bill_package_id' => $packageIdArr,
                'LIMIT'           => [0, 50],
                'ORDER'           => ['id' => 'DESC'],
            ],
            ['buyer', 'create_time']
        );
        $data['recent_purchase'] = self::formatRecentPurchase($recentPurchase);
        $mobile = MysqlDB::getDB()->select(
            StudentModel::$table . ' (s) ',
            [
                '[>]' . UserWeixinModel::$table . ' (uw) ' => ['s.id' => 'user_id']
            ],
            [
                's.mobile',
                's.uuid'
            ],
            [
                'uw.open_id' => $openid
            ]
        );
        if (!empty($mobile) && isset($mobile[0]['mobile'])) {
            $data['mobile'] = $mobile[0]['mobile'];
            $data['uuid'] = $mobile[0]['uuid'];
            $data['had_purchased'] = self::hadPurchaseTrail($mobile[0]['mobile']);
        }
        return $data;
    }

    /**
     * 获取推荐人信息及推荐文案
     * @param $user_id
     * @return array
     */
    public static function getReferrerInfoForMinApp($user_id)
    {
        $defaultData = [
            'nickname'   => '小叶子',
            'headimgurl' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb')),
            'text'       => '自从有了小叶子，孩子终于会主动练琴了，也帮你领取了社群限时福利，快来体验吧！',
        ];
        // 推荐人不存在时返回默认数据
        if (empty($user_id)) {
            return $defaultData;
        }
        $referrerInfo = UserWeixinModel::getRecord(
            [
                'user_id'   => $user_id,
                'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
                'busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
                'ORDER'     => ['id' => 'DESC'],
            ],
            ['open_id']
        );
        if (empty($referrerInfo['open_id'])) {
            return $defaultData;
        }
        // 获取用户微信昵称和头像
        $data = WeChatService::getUserInfo($referrerInfo['open_id']);
        if (empty($data)) {
            $data = $defaultData;
        }
        // 练琴天数
        $accumulateDays = AIPlayRecordModel::getAccumulateDays($user_id);

        // 查询推荐者练习曲目，根据曲目数据得到推荐文案
        [$percentage, $numbers] = self::getReferrerPlayData($user_id);
        if ($percentage && $accumulateDays) {
            $data['text'] = sprintf('我家宝贝在小叶子练琴<span>%d</span>天，弹奏得分提升了<span>%d%%</span>，孩子学会了主动练琴，进步飞快！', $accumulateDays, $percentage);
        } elseif ($accumulateDays >= 5) {
            $data['text'] = sprintf('我家宝贝在小叶子练琴<span>%d</span>天，共练习了<span>%d</span>首曲子，孩子学会了主动练琴，进步飞快！', $accumulateDays, $numbers);
        } else {
            $data['text'] = '自从有了小叶子，孩子终于会主动练琴了，也帮你领取了社群限时福利，快来体验吧！';
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
        $chdb = new CHDB();
        $sql  = "
        SELECT
           Max(score_final) AS max_score,
           Min(score_final) AS min_score
        FROM
           {table}
        WHERE
           student_id = {id}
        GROUP BY
           score_id
        HAVING
           min_score > 0
        ";
        $result = $chdb->queryAll($sql, ['table' => AIPlayRecordModel::$table, 'id' => $referrer_id]);
        // 练习曲目数量
        $numbers = $chdb->queryAll("
        SELECT
           count(DISTINCT score_id)
        FROM
           {table}
        WHERE
           student_id = {id}
        ", ['table'=>AIPlayRecordModel::$table, 'id' => $referrer_id]);

        // 练琴提升百分比
        $percentage = 0;
        $minScore = 1;
        foreach ($result as $key => $value) {
            $diff = $value['max_score'] - $value['min_score'];
            if ($diff > $percentage) {
                $percentage = $diff;
                $minScore = $value['min_score'];
            }
        }
        if (empty($percentage)) {
            return [$percentage, $numbers];
        }
        return [intval($percentage / $minScore * 100), $numbers];
    }

    /**
     * 处理最近购买文案
     * @param $recentPurchase
     * @return array
     */
    public static function formatRecentPurchase($recentPurchase)
    {
        $now       = time();
        $data      = [];
        $buyerInfo = StudentModel::getRecords(['id' => array_column($recentPurchase, 'buyer')], ['id', 'name']);
        $buyerInfo = array_combine(array_column($buyerInfo, 'id'), array_column($buyerInfo, 'name'));
        foreach ($recentPurchase as $item) {
            $diff = $now - $item['create_time'];
            if (!isset($buyerInfo[$item['buyer']])) {
                continue;
            }
            $dict = [
                [
                    'value' => 60,
                    'label' => '<span>刚刚成功购买</span>'
                ],
                [
                    'value' => 59*60,
                    'label' => "<span>" . (bcdiv($diff, 60) ?: 1) . "分钟前成功购买</span>",
                ],
                [
                    'value' => 24*60*60,
                    'label' => "<span>" . (bcdiv($diff, 60*60) ?: 1) . "小时前成功购买</span>",
                ],
                [
                    'value' => 100*24*60*60,
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
     * 注册
     * @param $openId
     * @param $iv
     * @param $encryptedData
     * @param $sessionKey
     * @param $mobile
     * @param $countryCode
     * @param $referrerId
     * @return array
     */
    public static function register(
        $openId,
        $iv,
        $encryptedData,
        $sessionKey,
        $mobile,
        $countryCode,
        $referrerId
    ) {
        if (!empty($encryptedData)) {
            $jsonMobile = self::decodeMobile($iv, $encryptedData, $sessionKey);
            if (empty($jsonMobile)) {
                return [$openId, 0, null];
            }
            $mobile = $jsonMobile['purePhoneNumber'];
        }

        $stu = StudentModelForApp::getStudentInfo(null, $mobile);
        if (empty($stu)) {
            list($lastId, $isNew, $uuid) = StudentServiceForApp::studentRegister($mobile, DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT'), null, $referrerId, $countryCode);
            if (empty($lastId)) {
                SimpleLogger::error('register fail from exam', ['mobile' => $mobile]);
                return [$openId, $lastId, null];
            }
        } else {
            $lastId = $stu['id'];
            $uuid = $stu['uuid'];
        }

        //保存openid
        $user = UserWeixinModel::getRecord(
            [
                'open_id' => $openId,
                'busi_type' => UserWeixinModel::BUSI_TYPE_REFERRAL_MINAPP
            ],
            [],
            false
        );
        if (empty($user)) {
            UserWeixinModel::insertRecord([
                'user_id'   => $lastId,
                'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
                'open_id'   => $openId,
                'status'    => UserWeixinModel::STATUS_NORMAL,
                'busi_type' => UserWeixinModel::BUSI_TYPE_REFERRAL_MINAPP,
                'app_id'    => self::REFERRAL_MINIAPP_ID, // 转介绍小程序
            ], false);
        }
        $hadPurchased = self::hadPurchaseTrail($mobile);
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

    /**
     * 用户是否有购买过体验包
     * @param $mobile
     */
    public static function hadPurchaseTrail($mobile)
    {
        $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL, 'app_id' => PackageExtModel::APP_AI]), 'package_id');
        $records = MysqlDB::getDB()->select(
            GiftCodeModel::$table . ' (gc) ',
            [
                '[>]' . StudentModel::$table . ' (s) ' => ['gc.buyer' => 'id']
            ],
            [
                'gc.bill_package_id'
            ],
            [
                's.mobile'           => $mobile,
                'gc.bill_package_id' => $packageIdArr,
                'LIMIT'              => [0, 1],
            ]
        );
        return !empty($records);
    }

    /**
     * 客服消息接收及回复
     * @param $params
     * @param $postData
     * @return int
     */
    public static function miniAppNotify($params, $postData)
    {
        $wx = new WXBizMsgCrypt(
            $_ENV['REFERRAL_LANDING_APP_MESSAGE_TOKEN'],
            $_ENV['REFERRAL_LANDING_APP_ENCODING_AES_KEY'],
            $_ENV['REFERRAL_LANDING_APP_ID']
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
                $assistant = MysqlDB::getDB()->get(
                    EmployeeModel::$table,
                    [
                        '[<>]' . StudentModel::$table    => ['id' => 'assistant_id'],
                        '[<>]' . UserWeixinModel::$table => [StudentModel::$table.'.id' => 'user_id']
                    ],
                    [
                        EmployeeModel::$table . '.wx_qr'
                    ],
                    [
                        UserWeixinModel::$table . '.open_id' => trim((string)$ele->FromUserName)
                    ]
                );
                if (empty($assistant['wx_qr'])) {
                    SimpleLogger::error('assistant\'s wx_qr image is empty', ['student_id' => (string)$ele->FromUserName]);
                    return "success";
                }

                $config = [
                    'app_id'     => $_ENV['REFERRAL_LANDING_APP_ID'],
                    'app_secret' => $_ENV['REFERRAL_LANDING_APP_SECRET'],
                ];
                $wx = WeChatMiniPro::factory($config);
                if (empty($wx)) {
                    SimpleLogger::error('wx create fail', ['config' => $config, 'we_chat_type'=>UserWeixinModel::USER_TYPE_STUDENT]);
                }
                $data = $wx->getTempMedia('image', $assistant['wx_qr'], AliOSS::replaceCdnDomainForDss($assistant['wx_qr']));

                if (!empty($data['media_id'])) {
                    Helper::sendMsg(
                        [
                            'touser'  => (string)$ele->FromUserName,
                            'msgtype' => 'image',
                            'image'   => ['media_id'=>$data['media_id']]
                        ],
                        $config['app_id'],
                        $config['app_secret']
                    );
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
}

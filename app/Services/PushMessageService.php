<?php
namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssReferralActivityModel;
use App\Models\Dss\DssSharePosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserWeiXinModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\SharePosterModel;
use App\Models\WeChatConfigModel;
use App\Models\WeekWhiteListModel;

class PushMessageService
{

    const MESSAGE_TIMEOUT = 180000; // 微信消息超时时间50小时
    const APPID_BUSI_TYPE_DICT = [
        Constants::SMART_APP_ID => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
        Constants::REAL_APP_ID  => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
    ];
    /**
     * 红包奖励相关的微信消息
     * @param $awardDetailInfo
     * @param array $ext
     */
    public static function sendAwardRelateMessage($awardDetailInfo, $ext = [])
    {
        if ($awardDetailInfo['app_id'] != Constants::SMART_APP_ID) {
            return;
        }
        //当前奖励要发放的数据库的消息模板
        $baseTemId = self::getAwardRelateTemId($awardDetailInfo);
        if (empty($baseTemId)) {
            SimpleLogger::info('not found tem id', ['award' => $awardDetailInfo]);
            return;
        }
        //完成任务的用户信息
        $achieveUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['uuid']]);
        //得到奖励的用户信息
        $awardUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['get_award_uuid']]);
        // ERP和DSS名字不同步：
        $awardDetailInfo['get_award_name'] = $awardUserInfo['name'];
        //得到奖励用户的微信信息
        $getAwardUserInfo = UserService::getUserWeiXinInfoByUserId($awardDetailInfo['app_id'], $awardUserInfo['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        if (empty($getAwardUserInfo)) {
            SimpleLogger::info('not found user weixin info', ['user_id' => $awardUserInfo['id']]);
            return;
        }
        $replaceParams = self::getReplaceParams($awardDetailInfo, $achieveUserInfo, $ext);
        self::notifyUserCustomizeMessage($baseTemId, $replaceParams, $getAwardUserInfo['open_id'], $awardDetailInfo['app_id']);
    }

    /**
     * 可替换的变量
     * @param $awardDetailInfo
     * @param $achieveUserInfo
     * @param array $ext
     * @return array
     */
    public static function getReplaceParams($awardDetailInfo, $achieveUserInfo, $ext = [])
    {
        $urlArr = [
            ErpEventModel::TYPE_IS_REFERRAL => $_ENV['STUDENT_INVITED_RECORDS_URL'],
            ErpEventModel::DAILY_UPLOAD_POSTER => $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/poster"
        ];
        $url = $urlArr[$awardDetailInfo['type']] ?? '';
        $activityName  = '';
        if ($awardDetailInfo['type'] == ErpEventModel::DAILY_UPLOAD_POSTER) {
            $activityId = !empty($ext['activity_id']) ? $ext['activity_id'] : DssSharePosterModel::getRecord(['award_id' => $awardDetailInfo['award_id']], 'activity_id');

            $activityName = DssReferralActivityModel::getRecord(['id' => $activityId], 'name');
        }
        // 任务达成条件
        $taskCondition = json_decode($awardDetailInfo['condition'], true);
        $total = 0;
        if (in_array($awardDetailInfo['type'], [ErpEventModel::TYPE_IS_REFERRAL])) {
            // 所有转介绍任务ID:
            $event = ErpEventModel::getRecord(['type' => ErpEventModel::TYPE_IS_REFERRAL]);
            $taskId = ErpEventTaskModel::getRecords(['event_id' => $event['id']]);
            // 累计转介绍奖励总额：
            $total = ErpUserEventTaskAwardModel::getUserTotal($awardDetailInfo['get_award_uuid'], array_column($taskId, 'id'));
            $total = $total[0]['totalAmount'] ?? 0;
        }
        $awardType = '红包';
        $remark = '，请点击红包领取';

        if ($awardDetailInfo['award_type'] == ErpUserEventTaskAwardModel::AWARD_TYPE_DURATION) {
            $awardType = '时长';
            $unit = '天';
            $remark = '';
            $duration = $awardDetailInfo['award_amount'].$unit;
        } else {
            $unit = '元';
            $duration = $awardDetailInfo['award_amount'] / 100 . $unit;
        }
        return [
            'mobile'       => Util::hideUserMobile($achieveUserInfo['mobile']),
            'url'          => $url,
            'awardValue'   => $awardDetailInfo['award_amount'] / 100,
            'activityName' => $activityName,
            'day'          => $taskCondition['total_days'] ?? 0,
            'userName'       => $awardDetailInfo['get_award_name'], // 当前用户
            'totalAward'     => ($total / 100),
            'referralMobile' => Util::hideUserMobile($awardDetailInfo['mobile']), // 被推荐人手机号
            'awardType' => $awardType,
            'duration' => $duration,
            'remark' => $remark,
        ];
    }

    /**
     * 当前发放奖励对应的wechat config表的基础id
     * @param $awardDetailInfo
     * @return int|mixed
     */
    public static function getAwardRelateTemId($awardDetailInfo)
    {
        $baseArr = [
            ErpEventModel::TYPE_IS_DURATION_POSTER => 11,
            ErpEventModel::TYPE_IS_REISSUE_AWARD => 240,
            ErpEventModel::DAILY_UPLOAD_POSTER => 259
        ];

        $endDate = DictConstants::get(DictConstants::WEEK_WHITE_TERM_OF_VALIDITY, 'term_of_validity');
        $endDate = strtotime($endDate);
        if($endDate >= time() && $awardDetailInfo['award_node'] == ErpUserEventTaskAwardGoldLeafModel::WEEK_WHITE_WEEK_AWARD && (WeekWhiteListModel::getCount(['status'=>WeekWhiteListModel::NORMAL_STATUS,'uuid'=>$awardDetailInfo['uuid']]))){
            //如果是周周领奖白名单用户
            $temId = 712;
        }else{
            $temId = $baseArr[$awardDetailInfo['type']] ?? NULL;
        }

        if (empty($temId)) {
            $to = $awardDetailInfo['uuid'] == $awardDetailInfo['get_award_uuid'] ? ErpEventTaskModel::AWARD_TO_BE_REFERRER : ErpEventTaskModel::AWARD_TO_REFERRER;
            $temId = WeChatConfigModel::getRecord(['event_task_id' => $awardDetailInfo['event_task_id'], 'to' => $to] , 'id');
        }
        return $temId;
    }

    /**
     * 微信发送自定义消息
     * @param $id
     * @param $replaceParams
     * @param $openId
     * @param $appId
     * @return array|bool|false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function notifyUserCustomizeMessage($id, $replaceParams, $openId, $appId)
    {
        $weChatConfigInfo = WeChatConfigModel::getById($id);
        SimpleLogger::info("send wx red pack notifyUserCustomizeMessage config info >>> ", ['info' => $weChatConfigInfo]);
        if ($weChatConfigInfo['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEMPLATE) {
            //模版消息
            $templateConfig = json_decode($weChatConfigInfo['content'], true);
            //根据关键标志替换模板内容
            foreach ($templateConfig['vars'] as &$tcv) {
                $tcv['value'] = Util::pregReplaceTargetStr(Util::textDecode($tcv['value']), $replaceParams);
            }
            $url = $replaceParams['url'] ?? $templateConfig["url"];
            $res = self::notifyUserWeixinTemplateInfo($appId, $openId, $templateConfig["template_id"], $templateConfig['vars'], $url);
            //返回数据
            return $res;
        } elseif ($weChatConfigInfo['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) {
            //客服消息 - 文本消息
            $content = Util::pregReplaceTargetStr(Util::textDecode($weChatConfigInfo['content']), $replaceParams);
            $res = self::notifyUserWeixinTextInfo($appId, $openId, $content);
            SimpleLogger::info("send wx red pack notifyUserCustomizeMessage res>>>", ['res' => $res]);
            return $res;
        }
    }

    /**
     * @param $appId
     * @param $openid
     * @param $templateId
     * @param $content
     * @param string $url
     * @return array|bool|mixed
     */
    public static function notifyUserWeixinTemplateInfo($appId, $openid, $templateId, $content, $url = '')
    {
        //组织数据
        $body = [
            'touser' => $openid,
            'template_id' => $templateId,
        ];
        if (!empty($url)) {
            $body['url'] = $url;
        }
        $body['data'] = $content;
        return WeChatMiniPro::factory($appId, self::APPID_BUSI_TYPE_DICT[$appId])->sendTemplateMsg($body);
    }

    /**
     * 发送客服文本消息
     * @param $appId
     * @param $openid
     * @param $content
     * @return false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function notifyUserWeixinTextInfo($appId, $openid, $content)
    {
        return WeChatMiniPro::factory($appId, self::APPID_BUSI_TYPE_DICT[$appId])->sendText($openid, $content);
    }

    /**
     * 检查openid最新活跃时间
     * @param $openId
     * @param int $timeout
     * @return bool
     */
    public static function checkLastActiveTime($openId, $timeout = self::MESSAGE_TIMEOUT)
    {
        if (empty($openId)) {
            return false;
        }
        $redisKey = Constants::DSS_OPENID_LAST_ACTIVE;
        $redis = RedisDB::getConn($_ENV['DSS_REDIS_DB']);
        $lastActive = (int)$redis->hget($redisKey, $openId);
        $now = time();
        if (!empty($lastActive) && $now - $lastActive <= $timeout) {
            return true;
        }
        return false;
    }

    /**
     * 发送任务奖励积分消息
     * @param $awardDetailInfo
     * @param array $ext
     */
    public static function sendAwardPointsMessage($awardDetailInfo, $ext = [])
    {
        if ($awardDetailInfo['app_id'] != Constants::SMART_APP_ID) {
            return;
        }
        //当前奖励要发放的数据库的消息模板
        $baseTemId = self::getAwardRelateTemId($awardDetailInfo);
        if (empty($baseTemId)) {
            SimpleLogger::info('not found tem id', ['award' => $awardDetailInfo]);
            return;
        }
        //完成任务的用户信息
        // $achieveUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['uuid']]);
        //得到奖励的用户信息
        $awardUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['uuid']]);
        // ERP和DSS名字不同步：
        $awardDetailInfo['get_award_name'] = $awardUserInfo['name'];
        //得到奖励用户的微信信息
        $getAwardUserInfo = UserService::getUserWeiXinInfoByUserId($awardDetailInfo['app_id'], $awardUserInfo['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        if (empty($getAwardUserInfo)) {
            SimpleLogger::info('not found user weixin info', ['user_id' => $awardUserInfo['id']]);
            return;
        }
        $replaceParams = self::getAwardPointsReplaceParams($awardDetailInfo, $ext);
        self::notifyUserCustomizeMessage($baseTemId, $replaceParams, $getAwardUserInfo['open_id'], $awardDetailInfo['app_id']);
    }

    /**
     * 获取积分奖励可替换的变量
     * @param $awardDetailInfo
     * @param $achieveUserInfo
     * @param array $ext
     * @return array
     */
    public static function getAwardPointsReplaceParams($awardDetailInfo, $ext = [])
    {
        $urlArr = [
            ErpEventModel::TYPE_IS_REFERRAL => $_ENV['STUDENT_AWARD_POINTS_URL'],
            ErpEventModel::DAILY_UPLOAD_POSTER => $_ENV["REFERRAL_FRONT_DOMAIN"] . DictConstants::get(DictConstants::REFERRAL_CONFIG, 'week_activity_url'),
        ];
        $url = $urlArr[$awardDetailInfo['type']] ?? '';
        $activityName  = '';
        if ($awardDetailInfo['type'] == ErpEventModel::DAILY_UPLOAD_POSTER) {
            $activityId = !empty($ext['activity_id']) ? $ext['activity_id'] : SharePosterModel::getRecord(['points_award_id' => $awardDetailInfo['id']], 'activity_id');
            $activityName = OperationActivityModel::getRecord(['id' => $activityId], 'name');
        }

        return [
            'url'          => $url,
            'awardValue'   => $awardDetailInfo['award_num'],
            'activityName' => $activityName,
        ];
    }


    /**
     * 发放金叶子推送消息
     *
     * @param array $info
     */
    public static function sendTaskGoldLeafMessage(array $info)
    {

        $appId = Constants::SMART_APP_ID;
        //得到奖励用户的微信信息
        $userInfo = UserService::getUserWeiXinInfoByUserId($appId, $info['student_id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        if (empty($userInfo)) {
            SimpleLogger::info('not found user weixin info', $info);
            return;
        }

        if (!empty($info['wechat_config_id'])) {
            $configId = $info['wechat_config_id'];
        } else {
            $configId = DictConstants::get(DictConstants::STUDENT_TASK_DEFAULT, 'message_config_id');
        }

        self::notifyUserCustomizeMessage($configId, $info, $userInfo['open_id'], $appId);
    }

    /**
     * 真人 - 消息推送
     * @param array $awardDetailInfo
     * @param array $ext
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function realSendMessage(array $awardDetailInfo, array $ext = [])
    {
        // 只限真人
        if ($awardDetailInfo['app_id'] != Constants::REAL_APP_ID) {
            return false;
        }

        // 获取奖励id
        $baseTemId = self::realGetWechatConfigId(array_merge($awardDetailInfo, ['award_prize_type' => $ext['award_prize_type']]));
        if (empty($baseTemId)) {
            SimpleLogger::info('not found tem id', ['award' => $awardDetailInfo]);
            return false;
        }

        // 获取用户绑定的微信信息
        $userBindWxInfo = ErpUserWeiXinModel::getStudentWxInfo($awardDetailInfo['user_id']);
        if (empty($userBindWxInfo)) {
            SimpleLogger::info('not found user weixin info', [$awardDetailInfo]);
            return false;
        }
        // 发送微信消息 - 关键字替换
        self::notifyUserCustomizeMessage($baseTemId, $ext, $userBindWxInfo['open_id'], $awardDetailInfo['app_id']);
        // 返回结果
        return true;
    }

    /**
     * 真人 - 获取奖励消息id
     * @param $awardInfo
     * @return int
     */
    public static function realGetWechatConfigId($awardInfo)
    {
        //当前奖励要发放的数据库的消息模板
        return RealDictConstants::get(RealDictConstants::REAL_SHARE_POSTER_CONFIG, $awardInfo['verify_status'] .'-'. $awardInfo['award_prize_type']);
    }

    /**
     * 发送客服消息
     * @param $msgBody
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function sendUserWxMsg($msgBody)
    {
        $appId = $msgBody['app_id'] ?? 0;
        $userId = $msgBody['user_id'] ?? 0;
        $wechatConfigId = $msgBody['wechat_config_id'] ?? 0;
        $replaceParams = $msgBody['replace_params'] ?? [];
        //得到奖励用户的微信信息
        $userInfo = UserService::getUserWeiXinInfoByUserId($appId, $userId, DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        if (empty($userInfo)) {
            SimpleLogger::info('not found user wechat info', [$msgBody]);
            return;
        }
        self::notifyUserCustomizeMessage($wechatConfigId, $replaceParams, $userInfo['open_id'], $userInfo['app_id']);
    }
}

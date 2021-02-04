<?php
namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssReferralActivityModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\WeChatConfigModel;

class PushMessageService
{
    const APPID_BUSI_TYPE_DICT = [
        Constants::SMART_APP_ID => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
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
            $activityName = DssReferralActivityModel::getRecord(['id' => $ext['activity_id']], 'name');
        }
        // 任务达成条件
        $taskCondition = json_decode($awardDetailInfo['condition'], true);
        $total = 0;
        $referralMobile = '';
        if (in_array($awardDetailInfo['type'], [ErpEventModel::TYPE_IS_REFERRAL])) {
            // 所有转介绍任务ID:
            $event = ErpEventModel::getRecord(['type' => ErpEventModel::TYPE_IS_REFERRAL]);
            $taskId = ErpEventTaskModel::getRecords(['event_id' => $event['id']]);
            // 累计转介绍奖励总额：
            $total = ErpUserEventTaskAwardModel::getUserTotal($awardDetailInfo['get_award_uuid'], array_column($taskId, 'id'));
            $total = $total[0]['totalAmount'] ?? 0;
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
        $temId = $baseArr[$awardDetailInfo['type']] ?? NULL;
        if (empty($temId)) {
            $to = $awardDetailInfo['uuid'] == $awardDetailInfo['get_award_uuid'] ? ErpEventTaskModel::AWARD_TO_BE_REFERRER : ErpEventTaskModel::AWARD_TO_REFERRER;
            $temId = WeChatConfigModel::getRecord(['event_task_id' => $awardDetailInfo['event_task_id'], 'to' => $to] , 'id');
        }
        return $temId;
    }

    /**
     * 微信发送自定义消息
     * @param $id
     * @param array $replaceParams      动态参数
     * @param $openId
     * @param $appId
     * @return array|bool|mixed
     */
    public static function notifyUserCustomizeMessage($id, $replaceParams, $openId, $appId)
    {
        $weChatConfigInfo = WeChatConfigModel::getById($id);
        if($weChatConfigInfo['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEMPLATE) {
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

    public static function notifyUserWeixinTextInfo($appId, $openid, $content)
    {
        return WeChatMiniPro::factory($appId, self::APPID_BUSI_TYPE_DICT[$appId])->sendText($openid, $content);
    }
}
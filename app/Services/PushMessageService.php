<?php
namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\WeChatConfigModel;

class PushMessageService
{
    public static function sendAwardRelateMessage($awardDetailInfo)
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
        //得到奖励用户的微信信息
        $getAwardUserInfo = UserService::getUserWeiXinInfoByUserId($awardDetailInfo['app_id'], $awardUserInfo['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        if (empty($getAwardUserInfo)){
            SimpleLogger::info('not found user weixin info', ['user_id' => $awardUserInfo['id']]);
            return;
        }
        $replaceParams = [
            'mobile' => Util::hideUserMobile($achieveUserInfo['mobile']),
            'url' => $awardDetailInfo['type'] == ErpEventModel::TYPE_IS_REFERRAL ? $_ENV['STUDENT_INVITED_RECORDS_URL'] : '',
            'awardValue' => $awardDetailInfo['award_amount'] / 100
        ];
        self::notifyUserCustomizeMessage($baseTemId, $replaceParams, $getAwardUserInfo['open_id'], $awardDetailInfo['app_id']);
    }

    /**
     * 当前发放奖励对应的wechat config表的基础id
     * @param $awardDetailInfo
     * @return int|mixed
     */
    public static function getAwardRelateTemId($awardDetailInfo)
    {
        $baseArr = [
            ErpEventModel::TYPE_IS_DURATION_POSTER => 5,
            ErpEventModel::TYPE_IS_REISSUE_AWARD => 240
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
        //系统应用对应的微信factory的信息
        $arr = [
            Constants::SMART_APP_ID => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
        ];
        return WeChatMiniPro::factory($appId, $arr[$appId])->sendTemplateMsg($body);
    }
}
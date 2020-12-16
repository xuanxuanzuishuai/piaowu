<?php
namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeChatConfigModel;

class PushMessageService
{
    public static function sendAwardRelateMessage($awardDetailInfo)
    {
        if ($awardDetailInfo['app_id'] != Constants::SMART_APP_ID) {
            return;
        }
        if ($awardDetailInfo['task_type'] == ErpEventTaskModel::COMMUNITY_DURATION_POSTER) {
            $baseTemId = 5; //社群审核通过
        } else {
            $baseTemId = $awardDetailInfo['event_task_id'];
        }
        $achieveUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['uuid']]);
        $awardUserInfo = DssStudentModel::getRecord(['uuid' => $awardDetailInfo['get_award_uuid']]);
        $getAwardUserInfo = UserService::getUserWeiXinInfoByUserId($awardDetailInfo['app_id'], $awardUserInfo['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        $replaceParams = [
            'mobile' => Util::hideUserMobile($achieveUserInfo['mobile']),
            'url' => in_array($awardDetailInfo['task_type'], [ErpEventTaskModel::COMMUNITY_DURATION_POSTER,ErpEventTaskModel::REISSUE_AWARD]) ? '' : $_ENV['STUDENT_INVITED_RECORDS_URL'],
            'awardValue' => $awardDetailInfo['award_amount'] / 100
        ];
        self::notifyUserCustomizeMessage($baseTemId, $replaceParams, $getAwardUserInfo['open_id'], $awardDetailInfo['app_id']);
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
        $weChatConfigInfo = WeChatConfigModel::getRecord(['id' => $id]);
        if($weChatConfigInfo['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEMPLATE)
            //模版消息
            $templateConfig = json_decode($weChatConfigInfo['content'], true);
            //根据关键标志替换模板内容
            foreach ($templateConfig['vars'] as &$tcv){
                $tcv['value'] = Util::pregReplaceTargetStr(Util::textDecode($tcv['value']),$replaceParams);
            }
            $url = $replaceParams['url'] ?? $templateConfig["url"];
            $res = self::notifyUserWeixinTemplateInfo($appId, $openId, $templateConfig["template_id"], $templateConfig['vars'], $url);
        //返回数据
        return $res;
    }

    /**
     * @param $app_id int, 在UserCenter中定义
     * @param $openid
     * @param $templateId
     * @param $content
     * @param string $url
     * @return array|bool|mixed
     */
    public static function notifyUserWeixinTemplateInfo($app_id, $openid, $templateId, $content, $url = '')
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
        $arr = [
            Constants::SMART_APP_ID => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER
        ];
        return WeChatMiniPro::factory($app_id, $arr[$app_id])->sendTemplateMsg($body);
    }
}
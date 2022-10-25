<?php
/**
 * 清晨推送消息
 * author: qingfeng.lian
 * date: 2022/6/24
 */

namespace App\Services\Morning;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\MorningDictConstants;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\MessagePushRulesModel;
use App\Models\WeChatConfigModel;
use App\Services\MessageService;
use App\Services\MiniAppQrService;
use App\Services\SharePosterService;

class MorningPushMessageService
{
    // 推送用户要结合appid进行区分，不同appid下值代表的用户群体是不一样的
    const MORNING_PUSH_USER_ALL      = 1;    // 清晨非年卡、体验卡用户
    const MORNING_PUSH_USER_TRAIL    = 2;    // 清晨体验卡用户
    const MORNING_PUSH_USER_NORMAL   = 3;    // 清晨年卡用户
    const MORNING_PUSH_USER_CLOCK_IN = 4;    // 清晨体验营打卡返现活动用户

    /**
     * 获取目标用户的中文
     * @param $targetUserId
     * @return array|mixed|null
     */
    public static function getTargetUserDict($targetUserId)
    {
        return MorningDictConstants::get(MorningDictConstants::MORNING_PUSH_USER_GROUP, $targetUserId);
    }

    /**
     * 5日打卡 - day0 - 推送开班通知 - 开班通知发送公众号消息给学生
     * @param array $msgBody
     * @return void
     */
    public static function eventWechatPushMsgToStudent(array $msgBody)
    {
        try {
            $openid = $msgBody['openid'] ?? '';
            $studentName = $msgBody['student_name'] ?? '';
            // 获取推送文案
            $message = MessagePushRulesModel::getRuleInfoByEnName(Constants::QC_APP_ID, 'morning_clock_in_collection_day1', MorningPushMessageService::MORNING_PUSH_USER_CLOCK_IN);
            if (empty($msgBody)) {
                return;
            }
            // 发送微信客服消息
            MessageService::pushCustomMessage($message, [
                'open_id' => $openid,
                'name'    => $studentName,
            ], Constants::QC_APP_ID, Constants::QC_APP_BUSI_WX_ID, false);
        } catch (RunTimeException $e) {
            SimpleLogger::info(['eventWechatPushMsgToStudent_wechat_push_custom_message_error'], [$msgBody, $e->getMessage()]);
        }
    }

    /**
     * 5日打卡 -  day1~day3 - 邀请达标用户参与互动 - 微信公众号消息
     * @param array $msgBody
     * @return void
     */
    public static function eventWechatPushMsgJoinStudent(array $msgBody)
    {

        try {
            $openid = $msgBody['openid'] ?? '';
            $day = $msgBody['day'] ?? 0;
            $uuid = $msgBody['uuid'] ?? '';
            $studentName = $msgBody['student_name'] ?? '';
            // 生成海报
            $message = MorningClockActivityService::generateClockActivityRuleMsgPoster($uuid, $day, $msgBody);
            // 发送微信客服消息
            MessageService::pushCustomMessage($message, [
                'open_id' => $openid,
                'name'    => $studentName,
            ], Constants::QC_APP_ID, Constants::QC_APP_BUSI_WX_ID, false);
        } catch (RunTimeException $e) {
            SimpleLogger::info(['eventWechatPushMsgJoinStudent_wechat_push_custom_message_error'], [$msgBody, $e->getMessage()]);
        }
    }

    /**
     * 生成5日打卡海报
     * @param       $uuid
     * @param array $posterInfo
     * @param array $params
     * @return array
     * @throws RunTimeException
     */
    public static function generate5DaySharePoster($uuid, array $posterInfo, array $params)
    {
        // 知识点
        $report = $params['lesson']['report'];
        $userInfo = (new Morning())->getStudentInfo([$uuid])[$uuid] ?? [];
        // 上传清晨头像到oss
        $thumbOssPath = self::updateMorningThumbToOss($userInfo['thumb']);
        // 头像
        $water[] = SharePosterService::generatePosterImgWater($thumbOssPath, MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_thumb');
        // 昵称
        $water[] = SharePosterService::generatePosterTextWater($userInfo['name'], MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_nickname');
        // 知识点
        $knowledgeConfig = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_knowledge'), true);
        $knowledgeConfig = $report['knowledge'] >= 10 ? $knowledgeConfig['two'] : $knowledgeConfig['one'];
        $water[] = SharePosterService::generatePosterTextWater($report['knowledge'], [], '', $knowledgeConfig);
        // 音准
        $_config = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_intonation'), true);
        $_config = $report['read_count'] >= 10 ? $_config['two'] : $_config['one'];
        $water[] = SharePosterService::generatePosterTextWater($report['read_count'], [], '', $_config);
        // 节奏
        $_config = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_rhythm'), true);
        $_config = $report['rhythm_count'] >= 10 ? $_config['two'] : $_config['one'];
        $water[] = SharePosterService::generatePosterTextWater($report['rhythm_count'], [], '', $_config);
        // 曲目
        $water[] = SharePosterService::generatePosterTextWater('解锁曲目 :《' . $params['lesson']['lesson_name'] . '》', MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_lesson_name');
        // 小程序码
        $qrInfo = MiniAppQrService::getUserMiniAppQr(
            Constants::QC_APP_ID,
            Constants::QC_APP_BUSI_MINI_APP_ID,
            0,
            Constants::USER_TYPE_STUDENT,
            MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_channel') ?? 0,
            DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            [
                'poster_id'   => $posterInfo['poster_id'] ?? 0,
                'user_uuid'   => $uuid,
                'user_status' => $userInfo['status'] ?? 0,
            ]
        );
        $water[] = SharePosterService::generatePosterImgWater($qrInfo['qr_path'], MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_water_poster_qr');

        // 海报打水印
        $posterConfig = json_decode(MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_poster_w_h'), true);
        $poster = SharePosterService::generateSharePoster($posterInfo['path'], $posterConfig, $water);
        return is_array($poster) ? $poster : [];
    }

    /**
     * 上传头像都oss
     * @param $headImageUrl
     * @return string
     */
    public static function updateMorningThumbToOss($headImageUrl)
    {
        $fileName = md5($headImageUrl);
        $thumb = $_ENV['ENV_NAME'] . '/morning/student/head/' . $fileName . '.jpg';
        if (!AliOSS::doesObjectExist($thumb)) {
            SimpleLogger::info("upload file start", []);
            AliOSS::putObject($thumb, $headImageUrl);
        }
        return $thumb;
    }
}
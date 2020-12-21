<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssAIPlayRecordCHModel;
use App\Models\Dss\DssStudentModel;
use App\Libs\Util;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\StudentInviteModel;

class ReferralService
{
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getReferralList($params)
    {
        list($records, $total) = DssStudentModel::getInviteList($params);
        foreach ($records as &$item) {
            $item = self::formatStudentInvite($item);
        }
        return [$records, $total[0]['total'] ?? 0];
    }

    public static function formatStudentInvite($item)
    {
        $hasReviewCourseSet = DictConstants::getSet(DictConstants::HAS_REVIEW_COURSE);
        $item['student_mobile_hidden']  = Util::hideUserMobile($item['mobile']);
        $item['referrer_mobile_hidden'] = Util::hideUserMobile($item['referral_mobile']);
        $item['has_review_course_show'] = $hasReviewCourseSet[$item['has_review_course']];
        $item['create_time_show']       = date('Y-m-d H:i', $item['create_time']);
        $item['register_time']          = $item['create_time'];
        return $item;
    }

    /**
     * 某个用户的推荐人信息
     * @param $appId
     * @param $studentId
     * @return mixed
     */
    public static function getReferralInfo($appId, $studentId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentInviteModel::getRecord(
                ['student_id' => $studentId, 'app_id' => $appId]
            );
        }
        return NULL;
    }

    /**
     * @param $appId
     * @param $studentId
     * @param $refereeType
     * @return mixed
     * 当前这个人推荐过来的所有用户
     */
    public static function getRefereeAllUser($appId, $studentId, $refereeType)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentInviteModel::getRecords(
                ['referee_id' => $studentId, 'app_id' => $appId, 'referee_type' => $refereeType]
            );
        }
        return NULL;
    }

    /**
     * 获取推送消息文案和图片
     * @param $day
     * @param $studentInfo
     * @return array
     */
    public static function getCheckinSendData($day, $studentInfo)
    {
        $configData = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_'.$day);
        if (empty($configData)) {
            SimpleLogger::error('EMPTY CONFIG', [$day]);
            return [];
        }
        $configData = json_decode($configData, true);
        $content1 = Util::textDecode($configData['content1'] ?? '');
        $content2 = Util::textDecode($configData['content2'] ?? '');
        $basePoster = $configData['poster_path'] ?? '';
        $posterImage = self::genCheckinPoster($basePoster, $day, $studentInfo);
        return [$content1, $content2, $posterImage];
    }

    /**
     * 生成学生打卡海报
     * @param $poster
     * @param $day
     * @param $studentInfo
     * @return array
     */
    public static function genCheckinPoster($poster, $day, $studentInfo)
    {
        if (empty($poster)) {
            return [];
        }
        if (empty($day)) {
            return ['poster_save_full_path' => AliOSS::signUrls($poster), 'unique' => md5($studentInfo['id'] . $day . $poster) . ".jpg"];
        }
        $headImageUrl = $studentInfo['wechat']['headimgurl'];
        $name = self::getPosterName($studentInfo['wechat']['nickname']);
        $percent = self::getRandScore($studentInfo['duration_sum']);
        $posterConfig = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'poster_config');
        if (!empty($posterConfig)) {
            $posterConfig = json_decode($posterConfig, true);
        }
        
        $thumb = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/'.md5($studentInfo['open_id']) . '.jpg';
        if (!AliOSS::doesObjectExist($thumb)) {
            $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/thumb_temp.jpg";
            chmod($tmpFileFullPath, 0755);
            file_put_contents($tmpFileFullPath, file_get_contents($headImageUrl));
            if (file_exists($tmpFileFullPath)) {
                AliOSS::uploadFile($thumb, $tmpFileFullPath);
            }
        }
        $userQrPath  = DssUserQrTicketModel::getUserQrURL(
            $studentInfo['id'],
            self::getChannelByDay($day),
            null,
            null,
            null,
            DssUserQrTicketModel::LANDING_TYPE_MINIAPP
        );
        $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($thumb."?x-oss-process=image/resize,w_90,h_90/circle,r_100/format,png"));
        $waterMark = [];
        $waterMark[] = [
            "image_" . $waterImgEncode,
            "x_" . $posterConfig['thumb_x'],
            "y_" . $posterConfig['thumb_y'],
            "g_nw",
        ];
        $waterMark[] = [
            "image_" . str_replace(["+", "/"], ["-", "_"], base64_encode($userQrPath."?x-oss-process=image/resize,w_".$posterConfig['qr_w'].",h_".$posterConfig['qr_h'])),
            "x_" . $posterConfig['qr_x'],
            "y_" . $posterConfig['qr_y'],
            "g_nw",
        ];
        $waterMark[] = self::getTextWaterMark($name, ['x' => $posterConfig['name_x'], 'y' => $posterConfig['name_y'], 'g' => 'nw', 's' => 30]);
        $waterMark[] = self::getTextWaterMark($studentInfo['lesson_count'], self::getTextConfig($studentInfo['lesson_count'], 'lesson'));
        $waterMark[] = self::getTextWaterMark($studentInfo['duration_sum'], self::getTextConfig($studentInfo['duration_sum'], 'duration'));
        $waterMark[] = self::getTextWaterMark($percent.'%', self::getTextConfig($percent, 'percent'));
        $waterMark[] = self::getTextWaterMark('分钟', self::getTextConfig($studentInfo['duration_sum'], 'minute'));
        $waterMark[] = self::getTextWaterMark('首', self::getTextConfig($studentInfo['lesson_count'], 'qu'));

        $waterMarkStr = [];
        foreach ($waterMark as $wm) {
            $waterMarkStr[] = implode(",", $wm);
        }
        $imgSize = [
            "w_" . $posterConfig['width'],
            "h_" . $posterConfig['height'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        $resImgFile = AliOSS::signUrls($poster, "", "", "", false, $waterMarkStr, $imgSizeStr);
        return ['poster_save_full_path' => $resImgFile, 'unique' => md5($studentInfo['id'] . $day . $poster) . ".jpg"];
    }

    /**
     * 获取文字水印
     * @param $string
     * @param $config
     * @return string[]
     */
    public static function getTextWaterMark($string, $config)
    {
        $r = [
            "text_" . str_replace(["+", "/"], ["-", "_"], base64_encode($string)),
            "type_d3F5LW1pY3JvaGVp", // 文泉微米黑
            "x_" . $config['x'],
            "y_" . $config['y'],
            "g_" . $config['g']
        ];
        if (!empty($config['s'])) {
            $r[] = "size_" . $config['s'];
        }
        return $r;
    }

    /**
     * 获取海报渠道
     * @param $day
     * @return string
     */
    public static function getChannelByDay($day)
    {
        $config = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_channel');
        $config = json_decode($config, true);
        return $config[$day] ?? '';
    }

    /**
     * 获取文字水印参数
     * @param $string
     * @param $configKey
     * @return array
     */
    public static function getTextConfig($string, $configKey)
    {
        $len = strlen($string);
        if (empty($len) || $len > 3) {
            $len = 3;
        }
        $config = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'text_position');
        $configArray = json_decode($config, true);
        if (empty($configArray)) {
            SimpleLogger::error("EMPTY TEXT POSITION CONFIG", [$config]);
        }
        return array_merge(['g' => 'nw'], $configArray[$len][$configKey]);
    }

    /**
     * 生成获取学生打卡文案所需的数据
     * @param $studentId
     * @return array|null
     * @throws \Exception
     */
    public static function getUserInfoForSendData($studentId)
    {
        if (empty($studentId)) {
            return [];
        }
        $studentInfo = DssStudentModel::getRecord(['id' => $studentId], ['collection_id']);
        $sendData = DssStudentModel::getByCollectionId($studentInfo['collection_id'], true);
        $sendData = $sendData[0] ?? [];
        if (empty($sendData)) {
            return [];
        }
        $now = time();
        $today = new \DateTime(date('Y-m-d', $now));
        $startDay = new \DateTime(date('Y-m-d', $sendData['teaching_start_time']));
        $dayDiff = $today->diff($startDay)->format('%a');
        if ($dayDiff <0 || $dayDiff > 5) {
            SimpleLogger::error("WRONG DAY DATA", [$sendData]);
            return [];
        }
        $day = date("Y-m-d", strtotime("-".$dayDiff." days", $now));
        $playInfo = DssAIPlayRecordCHModel::getStudentBetweenTimePlayRecord($studentInfo['id'], strtotime($day), strtotime($day.' 23:59:59'));
        $sendData['lesson_count'] = $playInfo[0]['lesson_count'] ?? 0;
        $sendData['duration_sum'] = $playInfo[0]['duration_sum'] ?? 0;
        $sendData['score_final'] = $playInfo[0]['score_final'] ?? 0;
        $sendData['wechat'] = self::getWechatInfoForPush($studentInfo);
        $sendData['day'] = $dayDiff;
        return $sendData;
    }

    /**
     * 获取推送用户信息
     * @param $studentInfo
     * @return array
     */
    public static function getWechatInfoForPush($studentInfo)
    {
        if (!empty($studentInfo['thumb'])) {
            return [
                'nickname'   => $studentInfo['name'],
                'headimgurl' => AliOSS::replaceCdnDomainForDss($studentInfo['thumb'])
            ];
        }
        $defaultData = [
            'nickname'   => '小叶子',
            'headimgurl' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'))
        ];
        $wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
        $data = $wechat->getUserInfo($studentInfo['open_id']);
        if (empty($data['headimgurl'])) {
            $data = $defaultData;
        }
        return $data;
    }

    /**
     * 根据分数生成排行百分比
     * @param $duration
     * @return int
     */
    public static function getRandScore($duration)
    {
        if ($duration < 10) {
            return rand(60, 70);
        }
        if ($duration < 30) {
            return rand(70, 80);
        }
        return rand(80, 99);
    }

    /**
     * 格式化海报名字长度
     * @param $name
     * @return string
     */
    public static function getPosterName($name)
    {
        $len = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'max_name_length');
        if (strlen($name) > $len) {
            return substr($name, 0, $len) . '...的宝贝';
        }
        return $name . '的宝贝';
    }

    /**
     * 获取不展示任务节点
     * @return false|string[]
     */
    public static function getNotDisplayWaitGiveTask()
    {
        $value = DictConstants::get(DictConstants::NODE_SETTING, 'not_display_wait');
        return explode(',', $value);
    }

    /**
     * 获取展示节点
     * @param $source
     * @return array
     */
    public static function getAwardNode($source)
    {
        $awardNodeArr = [
            'referee' => DictConstants::COMMON_CASH_NODE,
            'reissue_award' => DictConstants::REISSUE_CASH_NODE
        ];
        $type = empty($awardNodeArr[$source]) ? DictConstants::REFEREE_CASH_NODE : $awardNodeArr[$source];
        return DictConstants::getSet($type);
    }
}

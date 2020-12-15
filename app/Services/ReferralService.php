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
use App\Models\StudentInviteModel;

class ReferralService
{
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
     * 生成学生打开海报
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
        $name = $studentInfo['wechat']['nickname'];
        $percent = self::getRandScore($studentInfo['score_final']);
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
        $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($thumb. "?x-oss-process=image/resize,w_90,h_90/circle,r_100/format,png"));
        $waterMark = [];
        $waterMark[] = [
            "image_" . $waterImgEncode,
            "x_" . $posterConfig['thumb_x'],
            "y_" . $posterConfig['thumb_y'],
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
        if (!empty($config)) {
            $config = json_decode($config, true);
        }
        
        return array_merge(['g' => 'nw'], $config[$len][$configKey]);
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
        if (empty($sendData)) {
            return [];
        }
        $today = new \DateTime(date('Y-m-d', time()));
        $startDay = new \DateTime(date('Y-m-d', $sendData['teaching_start_time']));
        $dayDiff = $today->diff($startDay)->format('%a');
        if ($dayDiff <0 || $dayDiff > 5) {
            return [];
        }
        $day = date("Y-m-d", strtotime("-".$dayDiff." days", $sendData['teaching_start_time']));
        $playInfo = DssAIPlayRecordCHModel::getStudentPlayInfoByDate([$studentInfo['id']], $day);
        $sendData['lesson_count'] = $playInfo[0]['lesson_count'] ?? 0;
        $sendData['duration_sum'] = $playInfo[0]['duration_sum'] ?? 0;
        $sendData['score_final'] = $playInfo[0]['score_final'] ?? 0;
        $sendData['wechat'] = self::getWechatInfoForPush($studentInfo);
        $sendData['day'] = $day;
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
     * @param $score
     * @return int
     */
    public static function getRandScore($score)
    {
        if ($score < 80) {
            return rand(80, 85);
        }
        if ($score < 90) {
            return rand(86, 90);
        }
        return rand(91, 100);
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:08 PM
 */

namespace App\Services;

use App\Models\ReferralActivityModel;
use App\Models\UserQrTicketModel;
use App\Models\StudentModel;
use App\Models\CollectionModel;
use App\Libs\Util;
use App\Libs\AliOSS;

class ReferralActivityService
{
    /**
     * 获取活动信息
     * @param $studentId
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getReferralActivityTipInfo($studentId)
    {
        //获取学生当前状态
        $studentInfo = StudentService::studentStatusCheck($studentId);
        $data = [];
        if (empty($studentInfo)) {
            return $data;
        }
        $data['student_status'] = $studentInfo['student_status'];
        if ($studentInfo['student_status'] == StudentModel::STATUS_BUY_TEST_COURSE) {
            //付费体验课
            if ($studentInfo['student_info']['collection_id']) {
                $wechatQr = CollectionModel::getById($studentInfo['student_info']['collection_id'])['wechat_qr'];
            } else {
                $wechatQr = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC], ['wechat_qr'], false)['wechat_qr'];
            }
            $data['oss_wechat_qr'] = AliOSS::signUrls($wechatQr);
        } elseif ($studentInfo['student_status'] == StudentModel::STATUS_BUY_NORMAL_COURSE) {
            //付费正式课
            $activityInfo = self::getStudentActivityPoster($studentId);
            $data['activity_info'] = $activityInfo;
            if (!empty($activityInfo)) {
                //获取当前活动学生最新上传记录
                $data['activity_info']['upload_record'] = [];
                $uploadRecord = SharePosterService::getLastUploadRecord($studentId, $data['activity_info']['id'], ['reason', 'remark', 'status', 'img_url']);
                if (!empty($uploadRecord)) {
                    $data['activity_info']['upload_record']["status"] = $uploadRecord["status"];
                    $data['activity_info']['upload_record']["status_name"] = $uploadRecord["status_name"];
                    $data['activity_info']['upload_record']["img_oss_url"] = $uploadRecord["img_oss_url"];
                    $data['activity_info']['upload_record']["reason_str"] = $uploadRecord["reason_str"];
                }
                unset($data['activity_info']['poster_url']);
            }
        }
        //返回数据
        return $data;
    }

    /**
     * 获取当前有效活动分享海报
     * @param $studentId
     * @return array|mixed
     */
    public static function getStudentActivityPoster($studentId)
    {
        //获取当前有效的活动:如果存在多个则按创建时间倒叙去第一个
        $time = time();
        $data = [];
        $activityWhere = [
            'status' => ReferralActivityModel::STATUS_ENABLE,
            'start_time[<=]' => $time,
            'end_time[>=]' => $time,
            'ORDER' => ['create_time' => 'DESC'],
        ];
        $activityInfo = ReferralActivityModel::getRecord($activityWhere, ['id', 'end_time', 'start_time', 'name', 'guide_word', 'share_word', 'poster_url'], false);
        if (empty($activityInfo)) {
            return $data;
        }
        //生成带二维码的分享海报
        $settings = ReferralActivityModel::$studentWXActivityPosterConfig;
        $posterImgFile = UserService::addQrWaterMarkAliOss(
            $studentId,
            $activityInfo['poster_url'],
            UserQrTicketModel::STUDENT_TYPE,
            $settings['poster_width'],
            $settings['poster_height'],
            $settings['qr_width'],
            $settings['qr_height'],
            $settings['qr_x'],
            $settings['qr_y']);
        if (empty($posterImgFile)) {
            return $data;
        }
        $activityInfo['poster_oss_url'] = $posterImgFile;
        $formatActivityInfo = self::formatData([$activityInfo]);
        return $formatActivityInfo[0];
    }

    /**
     * 格式化信息
     * @param $data
     * @return mixed
     */
    public static function formatData($data)
    {
        foreach ($data as $dk => &$dv) {
            $dv['start_time'] = date('Y-m-d', $dv['start_time']);
            $dv['end_time'] = date('Y-m-d', $dv['end_time']);
            $dv['guide_word'] = Util::textDecode($dv['guide_word']);
            $dv['share_word'] = Util::textDecode($dv['share_word']);
        }
        return $data;
    }

    /**
     * 检测活动是否有效
     * @param $activityId
     * @return array
     */
    public static function checkActivityIsEnable($activityId)
    {
        $time = time();
        $activityWhere = [
            'id' => $activityId,
            'status' => ReferralActivityModel::STATUS_ENABLE,
            'start_time[<=]' => $time,
            'end_time[>=]' => $time
        ];
        $activityInfo = ReferralActivityModel::getRecord($activityWhere, ['id', 'event_id', 'task_id'], false);
        return $activityInfo;
    }
}
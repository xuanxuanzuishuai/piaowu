<?php

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ActivityExtModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealWeekActivityModel;
use App\Models\TemplatePosterModel;

class RealActivityService
{
    /**
     * 获取周周领奖和月月有奖活动列表
     * @param int $studentId 学生ID
     * @param int $type 活动类型:周周 月月
     * @param int $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function getWeekOrMonthActivityData($studentId, $type, $fromType)
    {
        switch ($type) {
            case OperationActivityModel::TYPE_MONTH_ACTIVITY:
                $data = self::monthActivityData($studentId, $type, $fromType);
                break;
            case OperationActivityModel::TYPE_WEEK_ACTIVITY:
                $data = self::weekActivityData($studentId, $type, $fromType);
                break;
            default:
                throw new RunTimeException(["activity_type_is_error"]);
        }
        return $data;
    }

    /**
     * 获取周周领奖活动
     * @param $studentId
     * @param $type
     * @param $fromType
     * @return array
     */
    /**
     * @param $studentId
     * @param $type
     * @param $fromType
     * @return array
     * @throws RunTimeException
     */
    private static function weekActivityData($studentId, $type, $fromType)
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
        ];
        //获取学生信息
        $userDetail = ErpStudentModel::getStudentInfoById($studentId);
        $data['student_info']['student_status'] = $userDetail['status'];
        $data['student_info']['student_status_zh'] = ErpStudentAppModel::$statusMap[$userDetail['status']];
        //周周领奖学生必须已付费
        if ($data['student_status'] != ErpStudentAppModel::STATUS_PAID) {
            return $data;
        }
        $data['student_info'] = [
            'uuid' => $userDetail['uuid'],
            'nickname' => $userDetail['name'] ?? '',
            'headimgurl' => ErpUserService::getStudentThumbUrl($userDetail['thumb']),
        ];
        //查询当前有效并且创建时间最早的活动
        $time = time();
        $activityData = RealWeekActivityModel::getRecord(
            [
                'start_time[<]' => $time,
                'end_time[>]' => $time,
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
            ],
            [
                'id',
                'activity_id',
                'poster_order',
            ]);
        if (empty($activityData)) {
            return $data;
        }
        //海报定位参数配置
        $posterConfig = PosterService::getPosterConfig();
        //查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityData);
        if (empty($posterList)) {
            return $data;
        }
        $typeColumn = array_column($posterList, 'type');
        $activityPosterIdColumn = array_column($posterList, 'activity_poster_id');
        //周周领奖:海报排序处理
        if ($activityData['poster_order'] == TemplatePosterModel::POSTER_ORDER) {
            array_multisort($typeColumn, SORT_DESC, $activityPosterIdColumn, SORT_ASC, $posterList);
        }
        //获取渠道ID配置
        $channel = PosterTemplateService::getChannel($type, $fromType);
        $extParams = [
            'user_status' => $userDetail['status'],
            'activity_id' => $activityData['activity_id'],
        ];
        //获取小程序二维码
        $userQrParams = [];
        foreach ($posterList as &$item) {
            $_tmp = $extParams;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentId;
            $_tmp['user_type'] = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['qr_sign'] = QrInfoService::createQrSign($_tmp,Constants::REAL_APP_ID,Constants::REAL_MINI_BUSI_TYPE);
            $userQrParams[] = $_tmp;
            $item['qr_sign'] = $_tmp['qr_sign'];
        }
        unset($item);
        $userQrArr = MiniAppQrService::getUserMiniAppQrList(Constants::REAL_APP_ID,Constants::REAL_MINI_BUSI_TYPE,$userQrParams);
        //处理数据
        foreach ($posterList as &$item) {
            $extParams['poster_id'] = $item['poster_id'];
            $item = PosterTemplateService::formatPosterInfo($item);
            //个性化海报只需获取二维码，不用合成海报
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = AliOSS::replaceCdnDomainForDss($userQrArr[$item['qr_sign']]['qr_path']);
                continue;
            }
            // 海报图：
            $poster = PosterService::generateQRPoster(
                $item['poster_path'],
                $posterConfig,
                $studentId,
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams,
                $userQrArr[$item['qr_sign']] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        $activityData['ext'] = ActivityExtModel::getActivityExt($activityData['activity_id']);
        $data['list'] = $posterList;
        $data['activity'] = $activityData;
        return $data;
    }


    /**
     * 获取月月领奖活动
     * @param $studentId
     * @param $type
     * @param $fromType
     * @return array
     */
    private static function monthActivityData($studentId, $type, $fromType)
    {
        //todo
        return [];
    }
}

<?php

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Erp\ErpCourseModel;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealMonthActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Models\TemplatePosterModel;
use App\Services\Queue\QueueService;

class RealActivityService
{
    /**
     * 获取周周领奖活动
     * @param int $studentId 学生ID
     * @param int $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function weekActivityData($studentId, $fromType)
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
            'can_upload' => false
        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        $data['student_info'] = [
            'uuid' => $studentDetail['uuid'],
            'nickname' => $studentDetail['name'] ?? '',
            'headimgurl' => ErpUserService::getStudentThumbUrl($studentDetail['thumb']),
            'student_status' => $studentDetail['status'],
            'student_status_zh' => ErpStudentAppModel::$statusMap[$studentDetail['status']],
        ];
        //周周领奖学生必须已付费
        if ($data['student_info']['student_status'] != ErpStudentAppModel::STATUS_PAID) {
            return $data;
        }
        //检测当前学生是否可以上传周周领奖截图
        $data['can_upload'] = empty(self::getCanParticipateWeekActivityIds($studentDetail, 2)) ? false : true;
        list($data['list'], $data['activity']) = self::initWeekOrMonthActivityData(OperationActivityModel::TYPE_WEEK_ACTIVITY, $fromType, $studentDetail);
        return $data;
    }


    /**
     * 获取月月领奖活动
     * @param int $studentId 学生ID
     * @param int $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function monthActivityData($studentId, $fromType)
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        $data['student_info'] = [
            'uuid' => $studentDetail['uuid'],
            'nickname' => $studentDetail['name'] ?? '',
            'headimgurl' => ErpUserService::getStudentThumbUrl($studentDetail['thumb']),
            'student_status' => $studentDetail['status'],
            'student_status_zh' => ErpStudentAppModel::$statusMap[$studentDetail['status']],
        ];
        list($data['list'], $data['activity']) = self::initWeekOrMonthActivityData(OperationActivityModel::TYPE_MONTH_ACTIVITY, $fromType, $studentDetail);
        return $data;
    }


    /**
     * 初始化周周月月活动的基础数据
     * @param $activityType
     * @param $fromType
     * @param $studentDetail
     * @return array
     * @throws RunTimeException
     */
    private static function initWeekOrMonthActivityData($activityType, $fromType, $studentDetail)
    {
        //查询当前有效的活动
        $activityData = [];
        $time = time();
        if ($activityType == OperationActivityModel::TYPE_WEEK_ACTIVITY) {
            $activityData = RealWeekActivityModel::getStudentCanSignWeekActivity(1, $time);
        } elseif ($activityType == OperationActivityModel::TYPE_MONTH_ACTIVITY) {
            $activityData = RealMonthActivityModel::getStudentCanSignMonthActivity(1);
        }
        if (empty($activityData)) {
            return [[], []];
        } else {
            $activityData = $activityData[0];
        }
        //海报定位参数配置
        $posterConfig = PosterService::getPosterConfig();
        //查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityData);
        if (empty($posterList)) {
            return [[], []];
        }
        $typeColumn = array_column($posterList, 'type');
        $activityPosterIdColumn = array_column($posterList, 'activity_poster_id');
        //海报排序处理
        if (isset($activityData['poster_order']) && ($activityData['poster_order'] == TemplatePosterModel::POSTER_ORDER)) {
            array_multisort($typeColumn, SORT_DESC, $activityPosterIdColumn, SORT_ASC, $posterList);
        }
        //获取渠道ID配置
        $channel = PosterTemplateService::getChannel($activityType, $fromType);
        $extParams = [
            'user_status' => $studentDetail['status'],
            'activity_id' => $activityData['activity_id'],
        ];
        //获取小程序二维码
        $userQrParams = [];
        foreach ($posterList as &$item) {
            $_tmp = $extParams;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentDetail['id'];
            $_tmp['user_type'] = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['qr_sign'] = QrInfoService::createQrSign($_tmp, Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE);
            $userQrParams[] = $_tmp;
            $item['qr_sign'] = $_tmp['qr_sign'];
        }
        unset($item);
        $userQrArr = MiniAppQrService::getUserMiniAppQrList(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $userQrParams);
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
                $studentDetail['id'],
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams,
                $userQrArr[$item['qr_sign']] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        $activityData['ext'] = ActivityExtModel::getActivityExt($activityData['activity_id']);
        return [$posterList, $activityData];
    }

    /**
     * 检测当前学生是否可以上传周周领奖截图
     * @param $studentData
     * @param $limitActivity
     * @return array
     */
    public static function getCanParticipateWeekActivityIds($studentData, $limitActivity)
    {
        //获取最新两个最新可参与的周周领奖活动
        $activityData = RealWeekActivityModel::getStudentCanSignWeekActivity($limitActivity, time());
        if (empty($activityData)) {
            return [];
        }
        $canJoinActivityIds = [];
        //学生第一次付费时间必须的活动结束时间之前
        foreach ($activityData as $val) {
            if ($val['end_time'] >= $studentData['first_pay_time']) {
                $canJoinActivityIds[] = $val['activity_id'];
            }
        }
        if (empty($canJoinActivityIds)) {
            return [];
        }
        //查看学生相对可参与活动的状态
        $haveQualifiedActivityIds = RealSharePosterModel::getRecords([
            'student_id' => $studentData['id'],
            'activity_id' => $canJoinActivityIds,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
        ], 'activity_id');
        //计算差集
        $diffActivityIds = array_diff($canJoinActivityIds, $haveQualifiedActivityIds);
        if (empty($diffActivityIds)) {
            return [];
        }
        $result = [];
        $activityData = array_column($activityData, null, 'activity_id');
        foreach ($diffActivityIds as $aid) {
            $result[] = [
                'activity_id' => $aid,
                'name' => $activityData[$aid]['name'],
            ];
        }
        return $result;
    }

    /**
     * 周周有奖活动海报截图上传
     * @param $studentId
     * @param $activityId
     * @param $imagePath
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function weekActivityPosterScreenShotUpload($studentId, $activityId, $imagePath)
    {
        //资格检测
        $checkRes = self::weekActivityPScreenUploadCCheck($studentId);
        if (empty($checkRes)) {
            throw new RunTimeException(['student_status_disable']);
        }
        //审核通过不允许上传截图
        $uploadRecord = RealSharePosterModel::getRecord([
            'student_id' => $studentId,
            'activity_id' => $activityId,
            'ORDER' => ['id' => 'DESC']
        ], ['verify_status', 'id']);
        if (!empty($uploadRecord) && ($uploadRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        $time = time();
        $data = [
            'student_id' => $studentId,
            'type' => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'activity_id' => $activityId,
            'image_path' => $imagePath,
            'create_time' => $time,
            'update_time' => $time,
        ];
        if (empty($uploadRecord) || $uploadRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            $res = RealSharePosterModel::insertRecord($data);
        } else {
            unset($data['create_time']);
            $count = RealSharePosterModel::updateRecord($uploadRecord['id'], $data);
            if (empty($count)) {
                throw new RunTimeException(['update_fail']);
            }
            $res = $uploadRecord['id'];
        }
        if (empty($res)) {
            throw new RunTimeException(['share_poster_add_fail']);
        }
        //系统自动审核
        QueueService::checkPoster(['id' => $res, 'app_id' => Constants::REAL_APP_ID]);
        return $res;
    }

    /**
     * 周周有奖活动海报截图上传资格检测
     * @param $studentId
     * @return bool
     */
    private static function weekActivityPScreenUploadCCheck($studentId)
    {
        //检测学生是否可以参加上传截图：正式课付费并且剩余课程数量大于0
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        //周周领奖学生必须已付费
        if ($studentDetail['status'] != ErpStudentAppModel::STATUS_PAID) {
            return false;
        }
        //付费用户剩余课程数量
        $norCourseRemainNum = ErpCourseService::getUserRemainCourseNum($studentId, ErpCourseModel::TYPE_NORMAL);
        if ($norCourseRemainNum <= 0) {
            return false;
        }
        return true;
    }
}

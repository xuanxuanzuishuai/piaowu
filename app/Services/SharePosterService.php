<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:07 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\ReferralActivityModel;
use App\Models\SharePosterModel;
use App\Models\StudentModel;
use App\Libs\Erp;

class SharePosterService
{
    //社群截图审核成功 微信配置的id
    const COMMUNITY_POSTER_APPROVE_TEMPLATE_ID = 5;
    //社群截图审核拒绝 微信配置的id
    const COMMUNITY_POSTER_REFUSED_TEMPLATE_ID = 6;

    /**
     * 上传分享截图
     * @param $activityId
     * @param $imgUrl
     * @param $studentId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function uploadSharePoster($activityId, $imgUrl, $studentId)
    {
        //获取学生信息
        $studentDetail = StudentService::studentStatusCheck($studentId);
        if ($studentDetail['student_status'] != StudentModel::STATUS_BUY_NORMAL_COURSE) {
            throw new RunTimeException(['student_status_disable']);
        }
        $studentInfo = $studentDetail['student_info'];
        //检查活动是否有效
        $activityInfo = ReferralActivityService::checkActivityIsEnable($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['activity_is_disable']);
        }
        //未上传/审核不通过允许上传截图
        $type = SharePosterModel::TYPE_UPLOAD_IMG;
        $uploadRecord = SharePosterModel::getRecord(['activity_id' => $activityId, 'student_id' => $studentId, 'type' => $type, 'ORDER' => ['create_time' => 'DESC']], ['status', 'award_id'], false);
        if (!empty($uploadRecord) && $uploadRecord['status'] != SharePosterModel::STATUS_UNQUALIFIED) {
            throw new RunTimeException(['stop_repeat_upload']);
        }
        //远程调用erp接口添加学生事件任务:只在第一次创建任务时调用
        $awardId = $uploadRecord['award_id'];
        if (empty($awardId)) {
            $erp = new Erp();
            $taskResult = $erp->updateTask($studentInfo['uuid'], $activityInfo['task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            if (empty($taskResult['data'])) {
                throw new RunTimeException(['erp_create_user_event_task_award_fail']);
            }
            $awardId = implode(',', $taskResult['data']['user_award_ids']);
        }
        $time = time();
        $insertId = SharePosterModel::insertRecord(
            [
                'student_id' => $studentId,
                'activity_id' => $activityId,
                'img_url' => $imgUrl,
                'create_time' => $time,
                'update_time' => $time,
                'award_id' => $awardId
            ],
            false);
        if (empty($insertId)) {
            throw new RunTimeException(['share_poster_add_fail']);
        }
        return $insertId;
    }

    /**
     * 获取学生最新截图审核记录
     * @param $studentId
     * @param $activityId
     * @param $field
     * @return array|mixed
     */
    public static function getLastUploadRecord($studentId, $activityId, $field = [])
    {
        //获取学生参加活动的信息
        $activityInfo = SharePosterModel::getRecord(['student_id' => $studentId, 'activity_id' => $activityId, 'ORDER' => ['create_time' => 'DESC']], $field, false);
        $data = [];
        if (empty($activityInfo)) {
            return $data;
        }
        //格式化信息
        $formatData = self::formatData([$activityInfo]);
        return $formatData[0];
    }

    /**
     * 格式化信息
     * @param $formatData
     * @return mixed
     */
    public static function formatData($formatData)
    {
        // 获取dict数据
        $dictMap = DictService::getTypesMap([Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON, Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS]);
        foreach ($formatData as $dk => &$dv) {
            $dv['img_oss_url'] = AliOSS::signUrls($dv['img_url']);
            $dv['status_name'] = $dictMap[Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS][$dv['status']]['value'];
            $reasonStr = [];
            if (!empty($dv['reason'])) {
                $dv['reason'] = explode(',', $dv['reason']);
                array_map(function ($reasonId) use ($dictMap, &$reasonStr) {
                    $reasonStr [] = $dictMap[Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON][$reasonId]['value'];
                }, $dv['reason']);
            }
            if ($dv['remark']) {
                $reasonStr [] = $dv['remark'];
            }
            $dv['reason_str'] = implode('/', $reasonStr);

        }
        return $formatData;
    }

    /**
     * 获取学生参加活动的记录列表
     * @param $studentId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function joinRecordList($studentId, $page, $limit)
    {
        //获取学生已参加活动列表
        $data = ['count' => 0, 'list' => []];
        $queryWhere = ['student_id' => $studentId, 'type' => SharePosterModel::TYPE_UPLOAD_IMG];
        $count = SharePosterModel::getCount($queryWhere);
        if (empty($count)) {
            return $data;
        }
        $data['count'] = $count;
        //查询起始数据量超出数据总量，直接返回
        $offset = ($page - 1) * $limit;
        if ($offset > $count) {
            return $data;
        }
        $queryWhere['ORDER'] = ['create_time' => 'DESC'];
        $queryWhere['LIMIT'] = [$offset, $limit];
        $activityList = SharePosterModel::getRecords($queryWhere, ['activity_id', 'status', 'create_time', 'img_url', 'reason', 'remark'], false);
        if (empty($activityList)) {
            return $data;
        }
        //获取活动信息
        $activityInfo = array_column(ReferralActivityModel::getRecords(['id' => array_unique(array_column($activityList, 'activity_id'))], ['name', 'id', 'task_id', 'event_id'], false), null, 'id');
        //获取奖励信息:审核通过的截图才会发放奖励
        $qualifiedRecord = $awardListInfo = [];
        array_map(function ($record) use (&$qualifiedRecord) {
            if ($record['status'] == SharePosterModel::STATUS_QUALIFIED) {
                $qualifiedRecord[] = $record['activity_id'];
            }
        }, $activityList);
        if (!empty($qualifiedRecord)) {
            $awardListInfo = self::getEventTaskInfo(array_column($activityInfo, 'event_id'));
        }
        //格式化信息
        $activityList = self::formatData($activityList);
        foreach ($activityList as $k => $v) {
            $activityEventId = $activityInfo[$v['activity_id']]['event_id'];
            $activityEventTaskId = $activityInfo[$v['activity_id']]['task_id'];
            $data['list'][$k]['name'] = $activityInfo[$v['activity_id']]['name'];
            $data['list'][$k]['status'] = $v['status'];
            $data['list'][$k]['status_name'] = $v['status_name'];
            $data['list'][$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
            $data['list'][$k]['award'] = ($v['status'] == SharePosterModel::STATUS_QUALIFIED) ? $awardListInfo[$activityEventId][$activityEventTaskId]['amount'] : '-';
            $data['list'][$k]['img_oss_url'] = $v['img_oss_url'];
            $data['list'][$k]['reason_str'] = $v['reason_str'];
        }
        return $data;
    }

    /**
     * 获取事件任务配置信息
     * @param $eventIdArr
     * @return array
     */
    private static function getEventTaskInfo($eventIdArr)
    {
        //访问erp获取数据
        $awardListInfo = [];
        $erp = new Erp();
        $eventTask = $erp->eventTaskList($eventIdArr);
        if (empty($eventTask['data'])) {
            return $awardListInfo;
        }
        $eventList = array_column($eventTask['data'], 'tasks');
        foreach ($eventList as $ak => $taskList) {
            array_map(function ($taskInfo) use (&$awardListInfo) {
                $awardInfo = json_decode($taskInfo['award'], true);
                if ($awardInfo['awards'][0]['type'] == 1) {
                    //金钱单位：分
                    $awardInfo['awards'][0]['amount'] = ($awardInfo['awards'][0]['amount'] / 100) . '元';
                } elseif ($awardInfo['awards'][0]['type'] == 2) {
                    //时间单位：天
                    $awardInfo['awards'][0]['amount'] = $awardInfo['awards'][0]['amount'] . '天';
                }
                $awardListInfo[$taskInfo['event_id']][$taskInfo['id']] = $awardInfo['awards'][0];
            }, $taskList);
        }
        return $awardListInfo;
    }

    /**
     * 上传截图列表
     * @param $params
     * @return array
     */
    public static function sharePosterList($params)
    {

        list($posters, $totalCount) = SharePosterModel::posterList($params);

        if (!empty($posters)) {
            $imgSizeH = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'img_size_h');

            foreach ($posters as &$poster) {
                $poster['mobile'] = Util::hideUserMobile($poster['mobile']);
                $poster['img_url'] = AliOSS::signUrls($poster['img_url'], "", "", "", false, "", $imgSizeH);
                $poster['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS, $poster['poster_status']);
                $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
                $poster['check_time'] = !empty($poster['check_time']) ? date('Y-m-d H:i', $poster['check_time']) : '';

                $reasonStr = [];
                if (!empty($poster['reason'])) {
                    $reason = explode(',', $poster['reason']);
                    foreach ($reason as $reasonId) {
                       $reasonStr[] = DictService::getKeyValue(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON, $reasonId);
                    }
                }
                if (!empty($poster['remark'])) {
                    $reasonStr[] = $poster['remark'];
                }
                $poster['reason_str'] = implode('/', $reasonStr);
            }
        }

        return [$posters, $totalCount];
    }

    /**
     * 审核通过、发放奖励
     * @param $posterIds
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function approval($posterIds, $employeeId)
    {
        $erp = new Erp();

        $posters = SharePosterModel::getPostersByIds($posterIds);
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $time = time();
        $status = SharePosterModel::STATUS_QUALIFIED;
        foreach ($posters as $poster) {

            // 一次任务，多个奖励
            $awardIds = explode(',', $poster['award_id']);
            $response = $erp->updateAward($awardIds, ErpReferralService::AWARD_STATUS_GIVEN, $employeeId);
            if (empty($response) || $response['code'] != 0) {
                SimpleLogger::error('update award error', ['award_id' => $awardIds, 'error' => $response['errors']]);
                $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
                throw new RunTimeException([$errorCode]);
            }

            $eventTaskId = $response['data']['event_task_id'];
            if ($eventTaskId > 0) {
                SharePosterModel::updateRecord($poster['id'], [
                    'status' => $status,
                    'check_time' => $time,
                    'update_time' => $time,
                    'operator_id' => $employeeId,
                ]);

                // 发送审核通过模版消息
                self::sendTemplate($poster['open_id'], $poster['activity_name'], $status);
            }
        }
        return true;
    }

    /**
     * @param $posterIds
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     * 社群截图通过，获得返现资格
     */
    public static function communityApproval($posterIds, $employeeId)
    {
        $erp = new Erp();

        $posters = SharePosterModel::getCommunityPostersByIds($posterIds);
        if (count($posters) != count($posterIds)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $time = time();
        $status = SharePosterModel::STATUS_QUALIFIED;
        foreach ($posters as $poster) {
            $awardId = $poster['award_id'];
            //审核通过在此认为此符合获取截图返现资格
            if (!empty($poster['task_id']) && empty($poster['award_id'])) {
                $taskResult = $erp->updateTask($poster['uuid'], $poster['task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
                if (empty($taskResult['data'])) {
                    throw new RunTimeException(['erp_create_user_event_task_award_fail']);
                }
                $awardId = implode(',', $taskResult['data']['user_award_ids']);
            }
            SharePosterModel::updateRecord($poster['id'], [
                'status' => $status,
                'award_id' => $awardId,
                'check_time' => $time,
                'update_time' => $time,
                'operator_id' => $employeeId,
            ]);
            // 发送审核通过模版消息
            WeChatService::notifyUserCustomizeMessage(
                $poster['mobile'],
                self::COMMUNITY_POSTER_APPROVE_TEMPLATE_ID,
                [
                    'url' => DictConstants::get(DictConstants::COMMUNITY_CONFIG, 'COMMUNITY_UPLOAD_POSTER_URL')
                ]
            );
        }
        return true;
    }

    /**
     * 审核不通过
     * @param $posterId
     * @param $employeeId
     * @param $reason
     * @param $remark
     * @return int|null
     * @throws RunTimeException
     */
    public static function refused($posterId, $employeeId, $reason, $remark)
    {
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }

        $posters = SharePosterModel::getPostersByIds([$posterId]);
        $poster = $posters[0];
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $status = SharePosterModel::STATUS_UNQUALIFIED;
        $time = time();
        $update = SharePosterModel::updateRecord($poster['id'], [
            'status' => $status,
            'check_time' => $time,
            'update_time' => $time,
            'operator_id' => $employeeId,
            'reason' => implode(',', $reason),
            'remark' => $remark
        ]);

        // 审核不通过, 发送模版消息
        if ($update > 0) {
            self::sendTemplate($poster['open_id'], $poster['activity_name'], $status);
        }

        return $update > 0;
    }

    /**
     * @param $posterId
     * @param $employeeId
     * @param $reason
     * @param $remark
     * @return bool
     * @throws RunTimeException
     * 社群截图审核不通过
     */
    public static function communityRefused($posterId, $employeeId, $reason, $remark)
    {
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }

        $posters = SharePosterModel::getCommunityPostersByIds([$posterId]);
        $poster = reset($posters);
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }

        $status = SharePosterModel::STATUS_UNQUALIFIED;
        $time = time();
        $update = SharePosterModel::updateRecord($poster['id'], [
            'status' => $status,
            'check_time' => $time,
            'update_time' => $time,
            'operator_id' => $employeeId,
            'reason' => implode(',', $reason),
            'remark' => $remark
        ]);

        // 审核不通过, 发送模版消息
        WeChatService::notifyUserCustomizeMessage(
            $poster['mobile'],
            self::COMMUNITY_POSTER_REFUSED_TEMPLATE_ID,
            [
                'url' => DictConstants::get(DictConstants::COMMUNITY_CONFIG, 'COMMUNITY_UPLOAD_POSTER_URL')
            ]
        );

        return $update > 0;
    }

    /**
     * 发送审核结果推送
     * @param $openId
     * @param $activityName
     * @param $status
     */
    public static function sendTemplate($openId, $activityName, $status)
    {
        $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/referral?tag=2";

        $keyword3 = "已通过。奖励已发放";
        $remark = "【点此消息】查看更多任务记录";
        $color = "#868686";
        if ($status == SharePosterModel::STATUS_UNQUALIFIED) {
            $keyword3 = "未通过";
            $remark = "【点此消息】查看更多任务记录，或进入“当前活动”重新上传";
            $color = "#FF0000";
        }


        $content = [
            'first' => [
                'value' => "您上传的截图审核结束，详情如下"
            ],
            'keyword1' => [
                'value' => "上传截图领奖",
            ],
            'keyword2' => [
                'value' => $activityName
            ],
            'keyword3' => [
                'value' => $keyword3,
                'color' => $color
            ],
            'remark' => [
                'value' => $remark
            ]
        ];
        WeChatService::notifyUserWeixinTemplateInfo(
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            $openId,
            $_ENV['WECHAT_TEMPLATE_CHECK_SHARE_POSTER'],
            $content,
            $url);
    }

    /**
     * 返现活动截图图片上传
     * @param $imgUrl
     * @param $studentId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function uploadReturnCashPoster($imgUrl, $studentId)
    {
        //绑定状态
        $studentStatus = StudentService::studentStatusCheck($studentId);
        if ($studentStatus['student_status'] == StudentModel::STATUS_UNBIND) {
            throw new RunTimeException(['review_student_need_bind_wx']);
        }
        //资格检测
        $checkResult = ReferralActivityService::returnCashActivityPlayRecordCheck($studentId);
        //未上传/审核不通过允许上传截图
        $type = SharePosterModel::TYPE_RETURN_CASH;
        $uploadRecord = SharePosterModel::getRecord(['activity_id' => $checkResult['collection_id'], 'student_id' => $studentId, 'type' => $type, 'ORDER' => ['create_time' => 'DESC']], ['status'], false);
        if (!empty($uploadRecord) && $uploadRecord['status'] != SharePosterModel::STATUS_UNQUALIFIED) {
            throw new RunTimeException(['stop_repeat_upload']);
        }
        $time = time();
        $insertId = SharePosterModel::insertRecord(
            [
                'student_id' => $studentId,
                'activity_id' => $checkResult['collection_id'],
                'img_url' => $imgUrl,
                'create_time' => $time,
                'update_time' => $time,
                'type' => $type,
            ],
            false);
        if (empty($insertId)) {
            throw new RunTimeException(['share_poster_add_fail']);
        }
        return $insertId;
    }
}

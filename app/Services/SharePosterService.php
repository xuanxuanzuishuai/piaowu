<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:07 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Models\ReferralActivityModel;
use App\Models\SharePosterModel;
use App\Models\StudentModel;
use App\Libs\Erp;

class SharePosterService
{
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
        $uploadRecord = SharePosterModel::getRecord(['activity_id' => $activityId, 'student_id' => $studentId, 'ORDER' => ['create_time' => 'DESC']], ['status', 'award_id'], false);
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
        $queryWhere = ['student_id' => $studentId];
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

}
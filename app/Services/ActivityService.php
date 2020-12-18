<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 11:37 AM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\SharePosterModel;

class ActivityService
{

    /**
     * 打卡活动上传截图
     * @param $studentId
     * @param $nodeId
     * @param $imagePath
     * @return bool
     * @throws RunTimeException
     */
    public static function signInUpload(int $studentId, $nodeId, $imagePath)
    {
        //检测可以参与的活动数据
        $time = time();
        $activityData = self::checkSignInActivityQuality($studentId, $time);
        //检测当前节点是否可以上传截图
        $nodeData = self::signInNodeData($studentId, $activityData, $time)['list'];
        if (!in_array($nodeData[$nodeId]['node_status'], [SharePosterModel::NODE_STATUS_VERIFY_UNQUALIFIED, SharePosterModel::NODE_STATUS_ING])) {
            throw new RunTimeException(['node_status_stop_upload']);
        }
        //保存截图数据
        if (empty($nodeData[$nodeId]['image_path'])) {
            $insertData = [
                'student_id' => $studentId,
                'activity_id' => $activityData['event_id'],
                'image_path' => $imagePath,
                //节点id，节点序号，有效期
                'ext' => json_encode(['node_order' => $nodeData[$nodeId]['node_order'], 'node_id' => $nodeId, 'valid_time' => $nodeData[$nodeId]['node_end']]),
                'create_time' => $time,
            ];
            $dbRes = SharePosterModel::insertRecord($insertData);
        } else {
            $updateData = [
                'image_path' => $imagePath,
                'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT,
                'update_time' => $time,
            ];
            $dbRes = SharePosterModel::updateRecord($nodeData[$nodeId]['poster_id'], $updateData);
        }
        if (empty($dbRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        return true;
    }

    /**
     * 检测可以参与的活动数据
     * @param $studentId
     * @param $time
     * @return array
     * @throws RunTimeException
     */
    private static function checkSignInActivityQuality($studentId, $time)
    {
        $studentData = DssStudentModel::getById($studentId);
        if (empty($studentData['collection_id'])) {
            throw new RunTimeException(['student_collection_is_empty']);
        }
        $activityData = self::signInActivityData($studentData['collection_id'], $time);
        if (empty($activityData)) {
            throw new RunTimeException(['sign_in_activity_empty']);
        }
        if ($activityData['activity_end_time'] < $time) {
            throw new RunTimeException(['sign_in_activity_end']);
        }
        if ($activityData['activity_start_time'] > $time) {
            throw new RunTimeException(['sign_in_activity_un_start']);
        }
        return $activityData;
    }


    /**
     * 获取打卡数据
     * @param $studentId
     * @return array
     */
    public static function getSignInData(int $studentId)
    {
        $data = [
            'status' => false,
            'days' => 0,
            'activity' => [],
            'node' => [],
            'award' => [],
        ];
        $studentData = DssStudentModel::getById($studentId);
        if (empty($studentData['collection_id'])) {
            return $data;
        }
        //活动基础数据
        $time = time();
        $activityData = self::signInActivityData($studentData['collection_id'], $time);
        if (empty($activityData)) {
            return $data;
        }
        $data['status'] = true;
        $data['activity'] = $activityData;
        $data['activity'] = [
            "activity_start_time" => $activityData['activity_start_time'],
            "activity_end_time" => $activityData['activity_end_time'],
        ];
        //活动节点数据
        $nodeData = self::signInNodeData($studentId, $activityData, $time);
        $data['node'] = array_values($nodeData['list']);
        //活动奖励数据
        $data['award'] = self::taskAwardCompleteStatus($studentData['uuid'], $activityData['event_id']);
        //打卡天数
        $data['days'] = $nodeData['days'];
        return $data;
    }

    /**
     * 打卡任务完成奖励状态
     * @param $studentUuId
     * @param $eventId
     * @return array
     */
    private static function taskAwardCompleteStatus($studentUuId, $eventId)
    {
        $erpStudentData = ErpStudentModel::getRecord(['uuid' => $studentUuId], ['id']);
        $awardData = ErpEventTaskModel::checkUserTaskAwardStatus($erpStudentData['id'], $eventId);
        $awardCompleteData = [];
        foreach ($awardData as $award) {
            if (is_null($award['user_id'])) {
                //未达标：用户未达到领取红包条件
                $awardStatus = ErpUserEventTaskAwardModel::STATUS_DISABLED;
            } elseif ($award['award_status'] != ErpUserEventTaskAwardModel::STATUS_GIVE) {
                //待发放：用户已达到领取红包条件，系统未发放红包
                $awardStatus = ErpUserEventTaskAwardModel::STATUS_WAITING;
            } else {
                $awardStatus = ErpUserEventTaskAwardModel::STATUS_GIVE;
            }
            $awardCompleteStatus['task_name'] = $award['task_name'];
            $awardCompleteStatus['task_desc'] = $award['task_desc'];
            $awardCompleteData[] = [
                'award_status' => $awardStatus,
                'task_name' => $award['task_name'],
                'task_desc' => $award['task_desc'],
            ];
        }
        return $awardCompleteData;
    }

    /**
     * 打卡活动基础数据
     * @param $collectionId
     * @param $time
     * @return array
     */
    private static function signInActivityData($collectionId, $time)
    {
        $activityData = [];
        //获取学生班级信息
        $collectionData = DssCollectionModel::getById($collectionId);
        //9.9班级
        if (empty($collectionData) || ($collectionData['trial_type'] != DssPackageExtModel::TRIAL_TYPE_9)) {
            return $activityData;
        }
        //班级打卡是否在有效期
        $activityEndTime = strtotime("+7 day", $collectionData['teaching_start_time']);
        if (($time > $activityEndTime) || ($time < $collectionData['teaching_start_time'])) {
            SimpleLogger::error("activity status error ", ['teaching_start_time' => $collectionData['teaching_start_time']]);
        } else {
            $activityData = [
                'teaching_start_time' => $collectionData['teaching_start_time'],
                'teaching_end_time' => $collectionData['teaching_end_time'],
                'activity_start_time' => $collectionData['teaching_start_time'],
                'activity_end_time' => $activityEndTime,
                'event_id' => $collectionData['event_id'],
            ];
        }
        return $activityData;
    }


    /**
     * 获取打卡签到节点基础数据
     * @param $studentId
     * @param $activityData
     * @param $time
     * @return array
     */
    public static function signInNodeData($studentId, $activityData, $time)
    {
        //节点时间
        $nodes = range($activityData['teaching_start_time'] + Util::TIMESTAMP_ONEDAY, $activityData['teaching_end_time'] + Util::TIMESTAMP_ONEDAY, Util::TIMESTAMP_ONEDAY);
        $nodeOrder = 1;
        array_map(function ($nodeTime) use (&$nodeData, &$nodeOrder) {
            $nodeId = date("Ymd", $nodeTime);
            $nodeData[$nodeId] = [
                'node_id' => $nodeId,
                'node_start' => strtotime("+9 hours", $nodeTime),//节点解锁时间
                'node_end' => strtotime("+33 hours", $nodeTime),//节点截止时间
                'node_order' => $nodeOrder,//节点序号
                'node_play_date' => date("Y-m-d", $nodeTime - Util::TIMESTAMP_ONEDAY),//节点练琴数据统计日期
            ];
            $nodeOrder++;
        }, $nodes);
        $nodeSignData = self::checkNodeStatus($nodeData, $studentId, $time);
        return $nodeSignData;
    }

    /**
     * 检测每个节点状态
     * @param $nodeData
     * @param $studentId
     * @param $time
     * @return mixed
     */
    private static function checkNodeStatus($nodeData, $studentId, $time)
    {
        $nodeSignData = [
            'list' => [],
            'days' => 0,
        ];
        //练琴数据
        $playRecordStartTime = strtotime(min(array_column($nodeData, 'node_play_date')));
        $playRecordEndTime = strtotime(max(array_column($nodeData, 'node_play_date'))) + Util::TIMESTAMP_ONEDAY;
        $playRecordData = array_column(DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord($studentId, $playRecordStartTime, $playRecordEndTime), null, 'create_date');
        //截图上传数据
        $nodeIdList = array_column($nodeData, 'node_id');
        $posterData = array_column(SharePosterModel::signInNodePoster($studentId, implode(',', $nodeIdList)), null, 'node_id');
        $nodeSignData['days'] = count($posterData);
        if (!empty($posterData)) {
            $posterData = self::posterCheckReason($posterData);
        }
        array_map(function ($node) use (&$nodeSignData, $time, $playRecordData, $posterData) {
            if ($node['node_start'] > $time) {
                //待解锁
                $node['node_status'] = SharePosterModel::NODE_STATUS_LOCK;
            } elseif (($node['node_start'] <= $time) && ($node['node_end'] >= $time)) {
                //进行中
                if (empty($playRecordData[$node['node_play_date']])) {
                    $node['node_status'] = SharePosterModel::NODE_STATUS_UN_PLAY;
                } elseif (empty($posterData[$node['node_id']])) {
                    $node['node_status'] = SharePosterModel::NODE_STATUS_ING;
                } elseif ($posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
                    $node['node_status'] = SharePosterModel::NODE_STATUS_VERIFY_ING;
                } elseif ($posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
                    $node['node_status'] = SharePosterModel::NODE_STATUS_HAVE_SIGN;
                } elseif ($posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                    $node['node_status'] = SharePosterModel::NODE_STATUS_VERIFY_UNQUALIFIED;
                }
            } else {
                //已过期
                $node['node_status'] = SharePosterModel::NODE_STATUS_EXPIRED;
            }
            //节点上传截图数据
            $node['poster_id'] = $node['verify_time'] = 0;
            $node['image_path'] = $node['verify_reason'] = '';
            if (!empty($posterData[$node['node_id']])) {
                $node['node_end'] = $posterData[$node['node_id']]['valid_time'];//重新计算节点有效期
                $node['poster_id'] = $posterData[$node['node_id']]['id'];
                $node['image_path'] = AliOSS::signUrls($posterData[$node['node_id']]['image_path']);
                $node['verify_time'] = $posterData[$node['node_id']]['verify_time'];
                $node['verify_reason'] = $posterData[$node['node_id']]['verify_reason'];
            }
            $nodeSignData['list'][$node['node_id']] = $node;
        }, $nodeData);
        return $nodeSignData;
    }

    /**
     * 获取海报审核不通过原因
     * @param $posterData
     * @return array
     */
    private static function posterCheckReason($posterData)
    {
        $dictReason = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
        $posterReason = array_map(function (&$val) use ($dictReason) {
            if (($val['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) && !empty($val['verify_reason'])) {
                $val['verify_reason'] = implode('、', array_intersect_key($dictReason, array_flip(explode(',', $val['verify_reason']))));
            }
            return $val;
        }, $posterData);
        return $posterReason;
    }

    /**
     * 打卡活动文案&海报
     * @param $studentId
     * @return array
     */
    public static function signInCopyWriting($studentId)
    {
        $studentInfo = ReferralService::getUserInfoForSendData($studentId);
        list($content1, $content2, $poster) = ReferralService::getCheckinSendData($studentInfo['day'], $studentInfo);
        return ['text' => $content2, 'poster' => $poster['poster_save_full_path']];
    }
}
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
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\WeekActivityModel;
use I18N\Lang;

class ActivityService
{

    const FROM_TYPE_APP  = 'app'; //智能app
    const FROM_TYPE_WX   = 'wx'; //智能微信
    const FROM_TYPE_REAL_STUDENT_APP = 'real_student_app'; //真人app
    const FROM_TYPE_REAL_STUDENT_WX = 'real_student_wx'; //真人学生微信
    const FROM_TYPE_PUSH = 'push'; //push

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
                'ext' => json_encode(['node_order' => $nodeData[$nodeId]['node_order'], 'node_id' => $nodeId]),
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
        $data['collection_id'] = $studentData['collection_id']; //班级id
        $data['collection_status'] = $activityData['collection_status'] ?? 0; //班期状态
        $data['teaching_start_time'] = date('Y-m-d H:i:s', $activityData['teaching_start_time']);
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
        //获取学生数据
        $erpStudentData = ErpStudentModel::getRecord(['uuid' => $studentUuId], ['id']);
        //获取活动task列表
        $taskIds = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'task_ids');
        $taskIds = json_decode($taskIds, true);
        $awardData = ErpEventTaskModel::checkUserTaskAwardStatus($erpStudentData['id'], $eventId, $taskIds);
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
        //9.9班级 关联5日打卡任务活动
        if (empty($collectionData) ||
            ($collectionData['event_id'] != DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'collection_event_id'))) {
            return $activityData;
        }
        //班期状态
        $collectionStatus = WechatService::getCollectionTeachingStatus($collectionData);
        //班级打卡是否已开始:由于2021.3.2规则改动不再考虑截止时间，但是之前的活动使用旧规则依然考虑活动截止时间
        $dividingLineTime = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG,'new_old_rule_dividing_line_time');
        $activityEndTime = strtotime("+7 day", $collectionData['teaching_start_time'] - 1);
        if (($time > $activityEndTime && $activityEndTime <= $dividingLineTime) || ($time < $collectionData['teaching_start_time'])) {
            SimpleLogger::error("activity status error ", ['teaching_start_time' => $collectionData['teaching_start_time']]);
        } else {
            $activityData = [
                'teaching_start_time' => $collectionData['teaching_start_time'],
                'teaching_end_time' => $collectionData['teaching_end_time'],
                'activity_start_time' => $collectionData['teaching_start_time'],
                'activity_end_time' => $activityEndTime,
                'event_id' => $collectionData['event_id'],
                'collection_status' => $collectionStatus
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
        $nodes = range($activityData['teaching_start_time'] + Util::TIMESTAMP_ONEDAY, $activityData['teaching_start_time'] + 5 * Util::TIMESTAMP_ONEDAY, Util::TIMESTAMP_ONEDAY);
        $nodeOrder = 1;
        array_map(function ($nodeTime) use (&$nodeData, &$nodeOrder) {
            $nodeId = date("Ymd", $nodeTime);
            $nodeData[$nodeId] = [
                'node_id' => $nodeId,
                'node_start' => strtotime("+9 hours", $nodeTime),//节点解锁时间
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
        //$playRecordData = array_column(DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord($studentId, $playRecordStartTime, $playRecordEndTime), null, 'create_date');
        $playRecordData = array_column(AprViewStudentModel::getStudentBetweenTimePlayRecord($studentId, $playRecordStartTime, $playRecordEndTime), null, 'create_date');
        //截图上传数据
        $nodeIdList = array_column($nodeData, 'node_id');
        $posterData = array_column(SharePosterModel::signInNodePoster($studentId, implode(',', $nodeIdList)), null, 'node_id');
        $nodeSignData['days'] = 0;
        if (!empty($posterData)) {
            $posterData = self::posterCheckReason($posterData);
        }
        array_map(function ($node) use (&$nodeSignData, $time, $playRecordData, $posterData) {
            //节点上传截图数据
            $node['poster_id'] = $node['verify_time'] = 0;
            $node['image_path'] = $node['verify_reason'] = '';
            if (!empty($posterData[$node['node_id']])) {
                $node['poster_id'] = $posterData[$node['node_id']]['id'];
                $node['image_path'] = AliOSS::signUrls($posterData[$node['node_id']]['image_path']);
                $node['verify_time'] = $posterData[$node['node_id']]['verify_time'];
                $node['verify_reason'] = $posterData[$node['node_id']]['verify_reason'];
            }
            if ($node['node_start'] > $time) {
                //待解锁
                $node['node_status'] = SharePosterModel::NODE_STATUS_LOCK;
            } elseif ((!empty($posterData[$node['node_id']])) && $posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
                //已打卡
                $node['node_status'] = SharePosterModel::NODE_STATUS_HAVE_SIGN;
                $nodeSignData['days'] += 1;
            } elseif ((!empty($posterData[$node['node_id']])) && $posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
                //审核中
                $node['node_status'] = SharePosterModel::NODE_STATUS_VERIFY_ING;
            } else {
                if (empty($playRecordData[$node['node_play_date']])) {
                    //未练琴
                    $node['node_status'] = SharePosterModel::NODE_STATUS_UN_PLAY;
                } elseif (empty($posterData[$node['node_id']])) {
                    //进行中
                    $node['node_status'] = SharePosterModel::NODE_STATUS_ING;
                } elseif ($posterData[$node['node_id']]['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                    //审核失败
                    $node['node_status'] = SharePosterModel::NODE_STATUS_VERIFY_UNQUALIFIED;
                }
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
                $verifyReason =  array_intersect_key($dictReason, array_flip(explode(',', $val['verify_reason'])));
            }
            if (!empty($val['remark'])) {
                $verifyReason[] = $val['remark'];
            }
            $val['verify_reason'] = implode('、', $verifyReason);
            return $val;
        }, $posterData);
        return $posterReason;
    }

    /**
     * 打卡活动文案&海报
     * @param $studentId
     * @param $nodeId
     * @return array
     */
    public static function signInCopyWriting($studentId, $params)
    {
        $studentInfo = ReferralService::getUserInfoForSendData($studentId, strtotime($params['node_id']));
        list($content1, $content2, $poster) = ReferralService::getCheckinSendData($studentInfo['day'], $studentInfo, $params);
        return ['text' => $content2, 'poster' => $poster['poster_save_full_path'], 'channel_id' => $poster['channel_id'] ?? 0];
    }

    /**
     * 获取活动信息
     * @param $type
     * @param int $activityId
     * @return array|mixed
     */
    public static function getByTypeAndId($type, $activityId = 0)
    {
        $activity = OperationActivityModel::getActiveActivity($type, $activityId);
        if (empty($activity)) {
            return [];
        }
        return self::formatData($activity);
    }

    /**
     * 活动信息格式化
     * @param $activity
     * @return mixed
     */
    public static function formatData($activity)
    {
        // 图片路径转URL
        $imageList = [
            'banner',
            'share_button_img',
            'award_detail_img',
            'upload_button_img',
            'strategy_img',
            'make_poster_button_img',
            'award_detail_img',
            'create_poster_button_img',
            'personality_poster_button_img',
            'poster_make_button_img',
        ];
        foreach ($imageList as $key) {
            $activity[$key . '_url'] = '';
            if (!empty($activity[$key])) {
                $activity[$key . '_url'] = AliOSS::replaceCdnDomainForDss($activity[$key]);
            }
        }
        // 文字内容解码
        $textList = [
            'make_poster_tip_word',
            'share_poster_tip_word',
            'guide_word',
            'share_word',
            'poster_prompt',
            'share_poster_prompt',
            'retention_copy',
        ];
        foreach ($textList as $key) {
            if (!empty($activity[$key])) {
                $activity[$key] = Util::textDecode($activity[$key]);
            }
        }
        return $activity;
    }

    /**
     * 周周有奖可选活动列表
     * @param array $params
     * @return mixed
     */
    public static function getWeekActivityList($params = [])
    {
        $list = WeekActivityModel::getSelectList($params);
        if (empty($list)) {
            return [];
        }
        $userId = $params['user_info']['user_id'];
        $available = false;
        //获取账户首次付费年卡时间
        $lastPayInfo = DssGiftCodeModel::getUserFirstPayInfo($userId, DssCategoryV1Model::DURATION_TYPE_NORMAL, 'asc');
        foreach ($list as $key => &$activity) {
            $activity['is_show'] = Constants::STATUS_TRUE;
            $where = [
                'student_id' => $userId,
                'activity_id' => $activity['activity_id'],
                'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED
            ];
            $shareRecord = SharePosterModel::getRecord($where);
            if (!empty($shareRecord) || $lastPayInfo['buy_time'] > $activity['end_time']) {
                $activity['is_show'] = Constants::STATUS_FALSE;
            } else {
                $available = true;
            }
        }
        // 没有活动可选
        $error = '';
        if (!$available) {
            $error = Lang::getWord('wait_for_next_event');
        }
        return ['error' => $error, 'list' => $list, 'available' => $available];
    }

    /**
     * 删除活动缓存
     * @param $activityId
     * @param array $delKeyPrefix
     * @param array $extData
     * @return bool|int
     */
    public static function delActivityCache($activityId, array $delKeyPrefix, array $extData = [])
    {
        // 没有要删除的key返回false
        if (empty($delKeyPrefix)) {
            return false;
        }
        $delKeys = [];
        foreach ($delKeyPrefix as $cachePrefix) {
            switch ($cachePrefix) {
                // 删除活动关联的海报缓存
                case ActivityPosterModel::KEY_ACTIVITY_POSTER:
                    $status = $extData[ActivityPosterModel::KEY_ACTIVITY_POSTER . '_status'] ?? ActivityPosterModel::NORMAL_STATUS;
                    $isDel = $extData[ActivityPosterModel::KEY_ACTIVITY_POSTER . '_is_del'] ?? ActivityPosterModel::IS_DEL_FALSE;
                    $delKeys[] = ActivityPosterModel::KEY_ACTIVITY_POSTER . implode('_', [$activityId, $status, $isDel]);
                    break;
                // 删除活动扩展信息缓存
                case ActivityExtModel::KEY_ACTIVITY_EXT:
                    $delKeys[] = ActivityExtModel::KEY_ACTIVITY_EXT . $activityId;
                    break;
                // 删除正在进行的活动信息缓存
                case OperationActivityModel::KEY_CURRENT_ACTIVE:
                    $posterType = $extData[OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type'] ?? "";
                    if (!empty($posterType)) {
                        $delKeys[] = OperationActivityModel::KEY_CURRENT_ACTIVE . $posterType;
                    }
                    break;
                default:
                    break;
            }
        }
        // 没有要删除的key返回false
        if (empty($delKeys)) {
            return false;
        }
        return RedisDB::getConn()->del($delKeys);
    }
}

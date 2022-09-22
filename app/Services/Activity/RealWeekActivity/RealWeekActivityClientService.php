<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022/04/28
 * Time: 11:34
 */

namespace App\Services\Activity\RealWeekActivity;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealStudentCanJoinActivityHistoryModel;
use App\Models\RealWeekActivityModel;
use App\Models\RealWeekActivityRuleVersionAbModel;
use App\Services\Activity\RealWeekActivity\TraitService\RealWeekActivityCURDService;
use App\Services\RealWeekActivityService;
use App\Services\StudentServices\ErpStudentService;
use App\Services\UserService;
use App\Libs\Exceptions\RunTimeException;

class RealWeekActivityClientService
{
    use RealWeekActivityCURDService;

    /**
     * 创建一个新的版本
     * @param $activityId
     * @param $abPosterInfo
     * @param $employeeId
     * @return int
     */
    public static function createVersion($activityId, $abPosterInfo, $employeeId)
    {
        return self::TraitAddOne($activityId, $abPosterInfo, $employeeId);
    }

    /**
     * 获取当前版本
     * @param $activityId
     * @return int
     */
    public static function getCurrentVersion($activityId)
    {
        $version = RealWeekActivityRuleVersionAbModel::getRecord(['activity_id' => $activityId, 'ORDER' => ['id' => 'DESC']]);
        return $version['id'] ?? 0;
    }

    /**
     * 获取审核截图搜索条件中下拉框的活动列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getVerifySharePosterActivityList($page, $count, $params)
    {
        $returnData = [
            'total_count' => 0,
            'list'        => [],
        ];
        $searchWhere = [
            'enable_status' => [OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_OFF],
        ];
        // 根据名称模糊(不支持分词) 、 活动id
        $searchName = $params['search_name'] ?? '';
        if (!empty($searchName)) {
            if (is_numeric($searchName)) {
                $searchWhere['OR'] = [
                    'AND #one' => ['w.activity_id' => intval($searchName)],
                    'AND #two' => ['w.name[~]' => trim($searchName)],
                ];
            } else {
                $searchWhere['name'] = $searchName;
            }
        }
        // 分享截图的审核状态
        if (!empty($params['share_poster_verify_status']) && $params['share_poster_verify_status'] == RealSharePosterModel::VERIFY_STATUS_WAIT) {
            $searchWhere['share_poster_verify_status'] = $params['share_poster_verify_status'];
        }
        // 分页
        (!empty($page) && !empty($count)) && $searchWhere['LIMIT'] = [($page - 1) * $count, $count];
        list($returnData['total_count'], $returnData['list']) = self::TraitGetVerifySharePosterActivityList($searchWhere);
        return $returnData;
    }

    /**
     * 检查学生是否可以参加周周领奖活动
     * @param $studentId
     * @param $studentUUID
     * @return bool false:可以参加， true:不可以参加
     */
    public static function checkIsAllowJoinWeekActivity($studentId, $studentUUID)
    {
        // 获取学生首次付费时间
        $studentCourseData = ErpStudentService::getStudentCourseData($studentUUID);
        if (empty($studentCourseData['is_valid_pay'])) {
            // 不是付费有效用户， 直接返回不能参与
            return true;
        }
        // 检查首次付费时间是2021.10.26号零点前(不包含零点)，检查用户是否有2021.10.26号零点前的订单是否消耗完成，消耗完不能再参加周周领奖活动
        if ($studentCourseData['is_first_pay_time_20211025'] && empty($studentCourseData['first_pay_time_20211025_remainder_num'])) {
            // 时间小于2021.10.26号零点前，并且没有改时间点之前未消耗完的订单
            return true;
        }
        // 检查2021.10.26零点前的订单，如果没有消耗完继续往下走
        return false;
    }

    /**
     * 检查学生是否能够上传周周领奖分享截图
     * @param $studentId
     * @param $activityId
     * @return bool
     */
    public static function checkStudentIsUploadPoster($studentId, $activityId)
    {
        // 获取活动信息
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            return false;
        }
        $time = time();
        // 活动进行中
        if ($activityInfo['enable_status'] = OperationActivityModel::ENABLE_STATUS_ON && $activityInfo['start_time'] <= $time && $activityInfo['end_time'] >= $time) {
            $studentInfo = ErpStudentModel::getRecord(['id' => $studentId]);
            // 检查用户命中的活动是否一致
            $activityList = RealWeekActivityService::getStudentCanPartakeWeekActivityList($studentInfo);
            $canPartakeActivityId = $activityList[0]['activity_id'] ?? 0;
            if (empty($canPartakeActivityId) || $canPartakeActivityId != $activityId) {
                return false;
            }
        } else {
            // 结束的活动-补卡
            SimpleLogger::info("checkStudentIsUploadPoster", ['msg' => "supplement_activity", $studentId, $activityId]);
        }
        return true;
    }

    /**
     * 获取学生参与活动历史记录
     * @param $studentUuid
     * @param $params
     * @return array
     */
    public static function getStudentJoinActivityHistory($studentUuid, $params)
    {
        $returnData = [
            'total_count' => 0,
            'list'        => [],
        ];
        if (empty($studentUuid)) {
            return $returnData;
        }
        $where = [
            'activity_type'      => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK,
            'activity_id'        => $params['activity_id'] ?? 0,
            'join_progress'      => $params['join_progress'] ?? 0,
            'last_verify_status' => $params['last_verify_status'] ?? 0,
            'page'               => $params['page'] ?? 1,
            'count'              => $params['count'] ?? 0,
            'ORDER'              => ['activity_status' => 'ASC', 'activity_create_time' => 'DESC']
        ];
        $data = RealStudentCanJoinActivityHistoryModel::getStudentJoinActivityHistory($studentUuid, $where);
        $returnData['total_count'] = $data['total_count'];
        if (!empty($data['list'])) {
            $activityIds = array_column($data['list'], 'activity_id');
            $activityList = RealWeekActivityModel::getActivityFirstAward($activityIds);
            $activityList = array_column($activityList, null, 'activity_id');
            foreach ($data['list'] as &$item) {
                $item['activity_name'] = $activityList[$item['activity_id']]['activity_name'];
                $item['award_prize_type'] = $activityList[$item['activity_id']]['award_prize_type'];
                $item['first_award_amount'] = $activityList[$item['activity_id']]['first_award_amount'];
                $item['award_prize_type_zh'] = OperationActivityModel::formatAwardPrizeType($item['award_prize_type']);
                $item['last_verify_status_zh'] = OperationActivityModel::formatVerifyStatus($item['last_verify_status']) ?? '';
                $item['activity_status_zh'] = OperationActivityModel::formatActivityStatus($item['activity_status']) ?? '';
                $item['format_activity_end_time'] = date("Y-m-d", $item['activity_end_time']);
                $item['format_activity_start_time'] = date("Y-m-d", $item['activity_start_time']);
            }
            unset($item);
        }
        $returnData['list'] = $data['list'];
        return $returnData;
    }

    /**
     * 获取学生命中活动的参与记录
     * @param $studentUuid
     * @param $activityId
     * @param $page
     * @param $count
     * @return array
     */
    public static function getStudentActivityJoinRecords($studentUuid, $activityId, $page, $count)
    {
        $returnData = [
            'total_count' => 0,
            'list'        => [],
        ];
        $activityHistory = RealStudentCanJoinActivityHistoryModel::getStudentWeekActivityHistory($studentUuid, $activityId);
        if (empty($activityHistory)) {
            return $returnData;
        }
        list($list, $returnData['total_count']) = RealSharePosterModel::getPosterList([
            'activity_id' => $activityId,
            'uuid'        => $studentUuid,
        ]);
        if (!empty($list)) {
            foreach ($list as $item) {
                $returnData['list'][] = [
                    'activity_count_task'     => $activityHistory['task_num'],
                    'join_num'                => $activityHistory['join_num'],
                    'task_num'                => $item['task_num'],
                    'activity_id'             => $item['activity_id'],
                    'student_id'              => $item['student_id'],
                    'verify_status'           => $item['poster_status'],
                    'operator_name'           => $item['operator_name'],
                    'reason'                  => $item['reason'],
                    'verify_status_zh'        => OperationActivityModel::formatVerifyStatus($item['poster_status']),
                    'reason_str'              => OperationActivityModel::formatVerifyReason($item['reason']),
                    'remark'                  => $item['remark'],
                    'format_create_time'      => date("Y-m-d H:i:s", $item['create_time']),
                    'format_verify_time'      => date("Y-m-d H:i:s", $item['check_time']),
                    'format_share_poster_url' => AliOSS::replaceCdnDomainForDss($item['img_url'])
                ];
            }
            unset($item);
        }
        return $returnData;
    }

    /**
     * 获取学生可参与活动历史记录列表（活动名称+活动id）
     * 下拉选项列表
     * @param $studentUuid
     * @return array
     */
    public static function getStudentJoinActivityHistoryList($studentUuid)
    {
        $newList = [];
        if (empty($studentUuid)) {
            return $newList;
        }
        $list = RealStudentCanJoinActivityHistoryModel::getRecords(['student_uuid' => $studentUuid], ['activity_id']);
        if (empty($list)) {
            return $newList;
        }
        $activityList = RealWeekActivityModel::getRecords(['activity_id' => array_column($list, 'activity_id')], ['activity_id', 'name']);
        $activityList = array_column($activityList, null, 'activity_id');
        foreach ($list as $item) {
            $newList[] = [
                'activity_id' => $item['activity_id'],
                'name'        => $activityList[$item['activity_id']]['name'] ?? ''
            ];
        }
        unset($item);
        return $newList;
    }
}

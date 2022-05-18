<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022/04/28
 * Time: 11:34
 */

namespace App\Services\Activity\RealWeekActivity;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterTaskListModel;
use App\Models\RealWeekActivityModel;
use App\Models\RealWeekActivityRuleVersionAbModel;
use App\Services\Activity\RealWeekActivity\TraitService\RealWeekActivityCURDService;
use App\Services\RealWeekActivityService;
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
                    'AND #one' => ['activity_id' => intval($searchName)],
                    'AND #two' => ['name[~]' => trim($searchName)],
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
     * @return bool true:可以参加， false:不可以参加
     */
    public static function checkIsAllowJoinWeekActivity($studentId, $studentUUID)
    {
        // 获取学生首次付费时间
        $studentIdAttribute = UserService::getStudentIdentityAttributeById(Constants::REAL_APP_ID, $studentId, $studentUUID);
        if (!UserService::checkRealStudentIdentityIsNormal($studentId, $studentIdAttribute)) {
            // 不是付费有效用户， 直接返回不能参与
            return false;
        }
        // 检查首次付费时间是2021.10.26号零点前(不包含零点)，检查用户是否有2021.10.26号零点前的订单是否消耗完成，消耗完不能再参加周周领奖活动
        $firstPayTimeNode20211025 = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'first_pay_time_node_20211025');
        if ($studentIdAttribute['first_pay_time'] < $firstPayTimeNode20211025 && !self::isHasFirstPayTimeNode20211025Course($studentUUID)) {
            // 时间小于2021.10.26号零点前，并且没有改时间点之前未消耗完的订单
            return false;
        }
        // 检查2021.10.26零点前的订单，如果没有消耗完继续往下走
        return true;
    }

    /**
     * 获取学生2021.10.26号零点之前付费订单是否都已经消耗完
     * @param $studentUUID
     * @return bool  true:存在， false:不存在
     */
    public static function isHasFirstPayTimeNode20211025Course($studentUUID)
    {
        // 获取用户所有付费订单
        try {
            $studentCourseList = (new Erp())->getStudentCourses($studentUUID);
            if (empty($studentCourseList)) {
                // 没有查到付费订单
                SimpleLogger::info("isHasFirstPayTimeNode20211025Course", ['msg' => 'erp_student_course_empty', $studentUUID, $studentCourseList]);
                return false;
            }
            $firstPayTimeNode20211025 = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'first_pay_time_node_20211025');
            foreach ($studentCourseList as $course) {
                foreach ($course as $item) {
                    // 未退费， 并且时间是节点之前， 并且剩余课程数量大于0
                    if ($item['is_refund'] == 1 && $item['create_time'] < $firstPayTimeNode20211025 && $item['remain_num'] > 0) {
                        $isHas = true;
                        break;
                    }
                }
            }
        } catch (RunTimeException $e) {
            SimpleLogger::info("isHasFirstPayTimeNode20211025Course_runtimeException", [$studentUUID, $studentCourseList, $e]);
            return false;
        }
        return !empty($isHas);
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
}

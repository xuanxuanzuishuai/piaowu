<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/31
 */

namespace App\Services\SyncTableData;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\OperationActivityModel;
use App\Models\RealStudentCanJoinActivityModel;
use App\Models\RealWeekActivityModel;

class CheckStudentIsCanActivityService
{

    /**
     * 检查学生是否能命中活动列表中的某个活动
     * 注：这里不会检查学生身份，所以这里的学生必须是付费有效 （有付费时间并且有剩余正式课数量）
     * @param $studentInfo
     * @param $weekActivityList
     * @return void
     */
    public static function checkWeekActivity($studentInfo, $weekActivityList)
    {
        if (empty($weekActivityList) || !is_array($weekActivityList)) {
            return;
        }
        $studentCountryCode = $studentInfo['country_code'] ?? '';
        $hitActivity = [];
        // 处理每个学生与所有正在运行的活动命中关系
        foreach ($weekActivityList as $_activity) {
            // 判断投放区域
            try {
                OperationActivityModel::checkWeekActivityCountryCode(['country_code' => $studentCountryCode], $_activity, Constants::REAL_APP_ID);
            } catch (RunTimeException $e) {
                continue;
            }
            // 检测用户首次付费时间与活动结束时间大小关系， 首次付费时间前结束的活动不可参与
            if ($studentInfo['first_pay_time'] > $_activity['end_time']) {
                continue;
            }
            // 检查首次付费时间
            if (!self::checkStudentIsTargetUser($studentInfo, $_activity)) {
                continue;
            }
            // 一个用户同一天只能命中一个活动，所以找到命中直接退出
            $hitActivity = $_activity;
            break;
        }
        unset($_activity);
        // 更新用户命中活动和历史参与记录
        RealStudentCanJoinActivityModel::updateStudentHitWeekActivity($studentInfo, $hitActivity);
    }

    /**
     * 检查学生是否在活动的目标用户中
     * 注：这里不会检查学生身份，所以这里的学生必须是付费有效 （有付费时间并且有剩余正式课数量）
     * @param $studentInfo
     * @param $activityInfo
     * @return bool
     */
    public static function checkStudentIsTargetUser($studentInfo, $activityInfo)
    {
        // 清退用户
        if ($studentInfo['clean_time'] > 0) {
            // 活动清退用户可参与
            if ($activityInfo['clean_is_join'] == RealWeekActivityModel::CLEAN_IS_JOIN_YES) {
                /**
                 * 检查清退用户是否可参与周周领奖
                 * 清退再续费用户定义：清退用户&首次清退后再续费&当前付费有效
                 * 优先级：清退再续费用户 》活动对象：
                 * 1。活动此选项选择"是"：可以参与
                 * 2。活动此选项选择"否"：不可参与
                 */
                // 考虑通过es读取清退用户信息
                $studentIdAttribute = [];
                // 清退用户是否是清退后再购买
                if (isset($studentIdAttribute['buy_after_clean']) && $studentIdAttribute['buy_after_clean'] == Constants::STATUS_TRUE) {
                    return true;
                }
                // 清退后未购买，不可参与
            }
            return false;
        }
        // 全部用户，只要用户是付费有效直接返回可参与
        if ($activityInfo['target_user_type'] == OperationActivityModel::TARGET_USER_ALL) {
            return true;
        }
        // 部分用户 - 首次付费时间小于活动圈定的时间不可参与
        if ($studentInfo['first_pay_time'] <= $activityInfo['target_user_first_pay_time_start']) {
            return false;
        }
        // 部分用户 - 首次付费时间超出活动圈定的时间不可参与
        if ($studentInfo['first_pay_time'] > $activityInfo['target_user_first_pay_time_end']) {
            return false;
        }
        return true;
    }
}
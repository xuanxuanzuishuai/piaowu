<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentModel;

class ErpStudentService
{
    private static $studentIdentityCourse = []; // 学生购买课程和付费信息

    /**
     * 获取学生基础信息
     * @param $uuids
     * @return array
     */
    public static function getStudentByUuid($uuids): array
    {
        return array_column(ErpStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'mobile']), null, 'uuid');
    }

    /**
     * 获取学生所有的付费订单列表、付费状态、购课情况 - 真人学生转介绍学生
     * @param $studentUUID
     * @return array
     * @throws RunTimeException
     */
    public static function getStudentCourseData($studentUUID)
    {
        if (empty($studentUUID)) {
            return [];
        }

        $key = 'student_identity_course_' . Constants::REAL_APP_ID . '-' . $studentUUID;
        if (!isset(self::$studentIdentityCourse[$key])) {
            $orderCourseData = (new Erp())->getStudentCourses($studentUUID);
            $orderCourseList = $orderCourseData['courses'] ?? [];
            $otherCourses = !empty($orderCourseData['other_courses']) ? array_column($orderCourseData['other_courses'], null, 'student_course_type') : [];
            // 陪练课-没有订单课程数量
            $giveAdderPlayerCourse = $otherCourses[2] ?? [];
            // 主课-没有订单课程数量
            $giveFormalCourse = $otherCourses[1] ?? [];
            SimpleLogger::info("getStudentCourseData", ['msg' => 'erp_student_course_data', $studentUUID, $orderCourseData]);
            if (empty($orderCourseList)) {
                // 没有查到用户身份订单
                return [];
            }
            $returnData = [
                'first_pay_time'                        => 0,  // 首次付费时间
                'is_real_person_paid'                   => 0,  // 付费情况 - 未付费
                'paid_course_remainder_num'             => 0,  // 剩余有效付费课程数量
                'course_count_num'                      => 0,  // 剩余有效课程总数量包含赠送
                'first_pay_time_20211025_remainder_num' => 0,  // 2021.10.26零点前付费并且未消耗完的订单剩余课程总数
                'is_first_pay_time_20211025'            => 0,  // 首次付费时间是不是在2021.10.26零点前
                'remain_num'                            => [],  // 用户购课剩余课程数量列表
                'is_valid_pay'                          => 0,  // 是否是付费有效用户（不包含非付费课）
            ];
            $firstPayTimeNode20211025 = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'first_pay_time_node_20211025');
            /** 没有订单号的赠课 */
            // 陪练课-赠课剩余数量
            $giveAdderPlayerCourseNum = !empty($giveAdderPlayerCourse) ? ($giveAdderPlayerCourse['remain_num'] + $giveAdderPlayerCourse['free_num']) : 0;
            $returnData['remain_num'][Constants::REAL_REFEREE_BUY_LADDER_PLAYER_GIVE] = $giveAdderPlayerCourseNum;
            // 主课-赠课剩余数量
            $giveFormalCourseNum = !empty($giveFormalCourse) ? ($giveFormalCourse['remain_num'] + $giveFormalCourse['free_num']) : 0;
            $returnData['remain_num'][Constants::REAL_REFEREE_BUY_FORMAL_GIVE] += $giveFormalCourseNum;
            // 所有剩余课程数量需要加上没有订单的数量
            $returnData['course_count_num'] += $giveAdderPlayerCourseNum + $giveFormalCourseNum;
            /** 有订单号的课包 */
            foreach ($orderCourseList as $_orderId => $_orderList) {
                foreach ($_orderList as $item) {
                    // 过滤不需要的sub_type
                    if (!in_array($item['sub_type'], array_merge(...array_values(Constants::REAL_REFEREE_ID_CONTRAST_SUB_TYPE)))) {
                        continue;
                    }
                    /** 不关心是否退费 */
                    // 剩余有效课程总数量包含赠送 - 不关系是不是退费， 退费的剩余课程应该也是0
                    $returnData['course_count_num'] += $item['remain_num'] + ($item['free_num'] ?? 0);
                    // 陪练课-赠课剩余数量
                    $returnData['remain_num'][Constants::REAL_REFEREE_BUY_LADDER_PLAYER_GIVE] += self::getSubTypeRemainNum(Constants::REAL_REFEREE_BUY_LADDER_PLAYER_GIVE, $item);
                    // 陪练课-体验课剩余数量
                    $returnData['remain_num'][Constants::REAL_REFEREE_BUY_LADDER_PLAYER_TRAIL] += self::getSubTypeRemainNum(Constants::REAL_REFEREE_BUY_LADDER_PLAYER_TRAIL, $item);
                    // 主课-赠课剩余数量
                    $returnData['remain_num'][Constants::REAL_REFEREE_BUY_FORMAL_GIVE] += self::getSubTypeRemainNum(Constants::REAL_REFEREE_BUY_FORMAL_GIVE, $item);
                    // 陪练课剩余数量
                    $returnData['remain_num'][Constants::REAL_REFEREE_BUY_LADDER_PLAYER] += self::getSubTypeRemainNum(Constants::REAL_REFEREE_BUY_LADDER_PLAYER, $item);
                    // 主课剩余数量
                    $returnData['remain_num'][Constants::REAL_REFEREE_BUY_FORMAL] += self::getSubTypeRemainNum(Constants::REAL_REFEREE_BUY_FORMAL, $item);
                    if ($item['pay_source'] == Constants::REAL_COURSE_YES_PAY) {
                        // 已付费 - 不关心是不是退费
                        $returnData['is_real_person_paid'] = Erp::USER_IS_PAY_YES;
                    }
                    // 首次付费时间
                    ($item['create_time'] < $returnData['first_pay_time'] || $returnData['first_pay_time'] <= 0) && $returnData['first_pay_time'] = $item['create_time'];
                    // 检查是否是2021。10.26零点前订单的订单
                    if ($item['create_time'] < $firstPayTimeNode20211025) {
                        $returnData['is_first_pay_time_20211025'] = true;        // 代表用户是2021.10.26零点前的付费用户
                    }
                    // 退费的不算
                    if ($item['is_refund'] != 1) {
                        continue;
                    }
                    /** 关心退费 */
                    if ($item['pay_source'] == Constants::REAL_COURSE_YES_PAY) {
                        // 剩余有效付费课程数量
                        $returnData['paid_course_remainder_num'] += $item['remain_num'];
                    }
                    // 非退费订单才计算有效剩余课程数量和订单
                    $item['create_time'] < $firstPayTimeNode20211025 && $returnData['first_pay_time_20211025_remainder_num'] += $item['remain_num'];   // 2021.10.26零点前付费并且未消耗完的订单剩余课程总数
                }
                unset($item);
            }
            unset($_orderId, $_orderList);
            // 确定用户购课身份 和 是否是 付费有效
            list($returnData['buy_course_type']) = self::getUserIdentity($returnData['remain_num']);
            if ($returnData['paid_course_remainder_num']) {
                $returnData['is_valid_pay'] = Erp::USER_IS_PAY_YES;
            }
            // 课包清理过 0 未清理过
            $returnData['is_cleaned'] = $orderCourseData['is_cleaned'] ?? 0;
            // 清理时间，is_cleaned = 1 时，该值有意义且一定>0，is_cleaned = 0 时，该值 = 0，该值覆盖了 is_cleaned 含义，为了兼容保留原来 is_cleaned
            $returnData['clean_time'] = $orderCourseData['clean_time'] ?? 0;
            // [clean_time > 0 时生效，清理后有购买则为1, 清理后无购买则该值为0; clean_time = 0 时，该值无意义]
            $returnData['buy_after_clean'] = $orderCourseData['buy_after_clean'] ?? 0;
            // 设置用户购课信息
            self::$studentIdentityCourse[$key] = $returnData;
        }
        SimpleLogger::info("getStudentCourseData", ['msg' => 'return_data', $studentUUID, $returnData ?? [], self::$studentIdentityCourse[$key]]);
        return self::$studentIdentityCourse[$key];
    }

    /**
     * 根据sub_type 返回剩余课程数
     * @param $courseSubType
     * @param $courseOrderInfo
     * @return int|mixed
     */
    protected static function getSubTypeRemainNum($courseSubType, $courseOrderInfo)
    {
        $remainNum = 0;
        if (in_array($courseOrderInfo['sub_type'], Constants::REAL_REFEREE_ID_CONTRAST_SUB_TYPE[$courseSubType]) && $courseOrderInfo['remain_num'] > 0) {
            $remainNum = $courseOrderInfo['remain_num'];
        }
        return $remainNum;
    }

    /**
     * 获取用户身份
     * 根据用户剩余课包判断用户购课情况
     * 双重身份：有主课，有陪练课 不区分课包类型都认为是双重身份
     * 付费有效：必须是购买了主课或陪练课不包括赠课和体验课
     * @param $remainNumList
     * @return array
     */
    protected static function getUserIdentity($remainNumList)
    {
        $userIdentity = 0;
        if (empty($remainNumList)) {
            return [$userIdentity];
        }
        $hasIdList = array_keys(array_diff($remainNumList, [0]));
        $formalCourse = $ladderPlayerCourse = [];
        foreach ($hasIdList as $item) {
            if (in_array($item, [Constants::REAL_REFEREE_BUY_LADDER_PLAYER, Constants::REAL_REFEREE_BUY_LADDER_PLAYER_GIVE, Constants::REAL_REFEREE_BUY_LADDER_PLAYER_TRAIL])) {
                $ladderPlayerCourse[] = $item;
            }
            if (in_array($item, [Constants::REAL_REFEREE_BUY_FORMAL, Constants::REAL_REFEREE_BUY_FORMAL_GIVE])) {
                $formalCourse[] = $item;
            }
        }
        unset($item);
        if (!empty($formalCourse) && !empty($ladderPlayerCourse)) {
            // 双重身份
            $userIdentity = Constants::REAL_REFEREE_BUY_FORMAL_AND_LADDER_PLAYER;
        } elseif (!empty($ladderPlayerCourse)) {
            // 陪练课身份
            $userIdentity = Constants::REAL_REFEREE_BUY_LADDER_PLAYER;
        } elseif (!empty($formalCourse)) {
            // 主课身份
            $userIdentity = Constants::REAL_REFEREE_BUY_FORMAL;
        }
        return [$userIdentity];
    }
}
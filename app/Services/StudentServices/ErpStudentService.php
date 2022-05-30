<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentModel;
use App\Services\UserService;

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
     */
    public static function getStudentCourseData($studentUUID)
    {
        if(empty($studentUUID)) {
            return [];
        }

        $key = 'student_identity_course_'. Constants::REAL_APP_ID . '-' . $studentUUID;
        if (!isset(self::$studentIdentityCourse[$key])) {
            $orderCourseList = (new Erp())->getStudentCourses($studentUUID);
            SimpleLogger::info("getStudentCourseData", ['msg' => 'erp_student_course_data', $studentUUID, $orderCourseList]);
            if (empty($orderCourseList)) {
                // 没有查到用户身份订单
                return [];
            }
            $returnData['first_pay_time'] = 0;  // 首次付费时间
            $returnData['is_real_person_paid'] = Erp::USER_IS_PAY_YES;  // 已付费
            $returnData['paid_course_remainder_num'] = 0;               // 剩余有效课程数量
            $returnData['first_pay_time_20211025_remainder_num'] = 0;   // 2021.10.26零点前付费并且未消耗完的订单剩余课程总数
            $returnData['first_pay_time_20211025_order_id'] = [];       // 2021.10.26零点前付费并且未消耗完的订单id
            $returnData['is_first_pay_time_20211025'] = false;          // 首次付费时间是不是在2021.10.26零点前
            $firstPayTimeNode20211025 = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'first_pay_time_node_20211025');
            foreach ($orderCourseList as $_orderId => $_orderList) {
                foreach ($_orderList as $item) {
                    // 过滤不需要的sub_type
                    if (!in_array($item['sub_type'], array_merge(...array_values(Constants::REFEREE_ID_CONTRAST_SUB_TYPE)))) {
                        continue;
                    }
                    // 首次付费时间
                    ($item['create_time'] < $returnData['first_pay_time'] || $returnData['first_pay_time'] <= 0) && $returnData['first_pay_time'] = $item['create_time'];
                    // 检查2021。10.26零点前订单是否有未消耗完的订单
                    if ($item['create_time'] < $firstPayTimeNode20211025) {
                        $returnData['is_first_pay_time_20211025'] = true;        // 2021.10.26零点前付费并且未消耗完的订单id
                        // 非退费订单才计算有效剩余课程数量和订单
                        if ($item['is_refund'] == 1) {
                            $returnData['first_pay_time_20211025_remainder_num'] += $item['remain_num'];   // 2021.10.26零点前付费并且未消耗完的订单剩余课程总数
                            $item['remain_num'] > 0 && $returnData['first_pay_time_20211025_order_id'][] = $_orderId;        // 2021.10.26零点前付费并且未消耗完的订单id
                        }
                    }

                    // 购买过陪练课，并且有剩余课程数量的订单
                    if (in_array($item['sub_type'], Constants::REFEREE_ID_CONTRAST_SUB_TYPE[Constants::REFEREE_BUY_LADDER_PLAYER]) && $item['remain_num'] > 0) {
                        // 如果字段不存在初始化必要字段
                        if (empty($returnData[Constants::REFEREE_BUY_LADDER_PLAYER])) {
                            $returnData[Constants::REFEREE_BUY_LADDER_PLAYER]['remain_num'] = 0;
                        }
                        // 计算
                        $returnData[Constants::REFEREE_BUY_LADDER_PLAYER]['remain_num'] += $item['remain_num']; // 总剩余课时
                        $returnData[Constants::REFEREE_BUY_LADDER_PLAYER]['list'][$_orderId][] = $item;   // 有剩余课时的订单列表 list
                        continue;
                    }
                    // 购买过正式课，并且有剩余课程数量的订单
                    if (in_array($item['sub_type'], Constants::REFEREE_ID_CONTRAST_SUB_TYPE[Constants::REFEREE_BUY_FORMAL]) && $item['remain_num'] > 0) {
                        // 如果字段不存在初始化必要字段
                        if (empty($returnData[Constants::REFEREE_BUY_FORMAL])) {
                            $returnData[Constants::REFEREE_BUY_FORMAL]['remain_num'] = 0;
                        }
                        // 计算
                        $returnData[Constants::REFEREE_BUY_FORMAL]['remain_num'] += $item['remain_num']; // 总剩余课时
                        $returnData[Constants::REFEREE_BUY_FORMAL]['list'][$_orderId][] = $item;   // 有剩余课时的订单列表 list
                        continue;
                    }

                }
                unset($item);
            }
            unset($_orderId, $_orderList);
            // 确定用户购课身份
            if (isset($returnData[Constants::REFEREE_BUY_LADDER_PLAYER]) && isset($returnData[Constants::REFEREE_BUY_FORMAL])) {
                $returnData['buy_course_type'] = Constants::REFEREE_BUY_FORMAL_AND_LADDER_PLAYER;
            } elseif (isset($returnData[Constants::REFEREE_BUY_LADDER_PLAYER])) {
                $returnData['buy_course_type'] = Constants::REFEREE_BUY_LADDER_PLAYER;
            } elseif (isset($returnData[Constants::REFEREE_BUY_FORMAL])) {
                $returnData['buy_course_type'] = Constants::REFEREE_BUY_FORMAL;
            }
            if (!empty($returnData['buy_course_type'])) {
                $returnData['is_valid_pay'] = Erp::USER_IS_PAY_YES;       // 付费有效
            }

            // 获取用户其他属性
            $studentInfo = ErpStudentModel::getRecord(['uuid' => $studentUUID], ['id']);
            $studentAttr = UserService::getStudentIdentityAttributeById(Constants::REAL_APP_ID, $studentInfo['id'], $studentUUID);
            $returnData['is_cleaned'] = $studentAttr['is_cleaned'] ?? 0;
            $returnData['buy_after_clean'] = $studentAttr['buy_after_clean'] ?? 0;
            $returnData['clean_is_join'] = $studentAttr['clean_is_join'] ?? 0;
            self::$studentIdentityCourse[$key] = $returnData;
        }
        SimpleLogger::info("getStudentCourseData", ['msg' => 'return_data', $studentUUID, $returnData]);
        return self::$studentIdentityCourse[$key];
    }
}
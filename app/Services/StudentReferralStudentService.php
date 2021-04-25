<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 19:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;

class StudentReferralStudentService
{

    /**
     * 检测是否可以建立学生转介绍学生绑定关系
     * @param $studentId
     * @return bool
     */
    public static function checkBindReferralCondition($studentId)
    {
        //不可以建立绑定关系的条件:1存在真人转介绍关系 2已购买过年卡（智能年卡，真人送智能年卡）
        //存在真人转介绍关系
        $erpReferralUserRefereeData = [];
        $dssStudentData = DssStudentModel::getRecord(['id' => $studentId], ['uuid', 'mobile']);
        $erpStudentData = ErpStudentModel::getListByUuidAndMobile($dssStudentData['uuid'], [], ['id']);
        if (!empty($erpStudentData)) {
            $erpReferralUserRefereeData = ErpReferralUserRefereeModel::getRecord(['user_id' => $erpStudentData[0]['id']], ['id']);
        }
        if (!empty($erpReferralUserRefereeData)) {
            SimpleLogger::info('has erp referral relation', ['erp_referral_info' => $erpReferralUserRefereeData]);
            return false;
        }
        //已购买过年卡
        $normalPackage = DssGiftCodeModel::getUserFirstPayInfo($studentId);
        if (!empty($normalPackage)) {
            SimpleLogger::info('has buy normal package', ['normal_package' => $normalPackage]);
            return false;
        }
        return true;
    }


    /**
     * 购买体验卡数据记录
     * @param $studentId
     * @param $qrTicketIdentityData
     * @param $extParams
     * @param $time
     * @return bool
     */
    public static function trailReferralRecord($studentId, $qrTicketIdentityData, $extParams, $time)
    {
        //检测当前学生是否可以建立绑定关系：已存在绑定关系的老数据跳过检测
        $bindReferralInfo = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $studentId], ['student_id', 'id', 'last_stage']);
        if (empty($bindReferralInfo)) {
            //新绑定逻辑条件检测
            $conditionRes = self::checkBindReferralCondition($studentId);
        } else {
            $conditionRes = true;
        }
        if (empty($conditionRes)) {
            return false;
        }
        //学生转介绍学生绑定关系
        $registerStageData = StudentReferralStudentDetailModel::getRecord(
            [
                'student_id' => $studentId,
                'stage' => StudentReferralStudentStatisticsModel::STAGE_REGISTER
            ],
            ['id']);
        if (empty($registerStageData)) {
            $batchInsertData[] = [
                'student_id' => $studentId,
                'stage' => StudentReferralStudentStatisticsModel::STAGE_REGISTER,
                'create_time' => DssStudentModel::getRecord(['id' => $studentId], ['create_time'])['create_time']
            ];
        }
        $batchInsertData[] = [
            'student_id' => $studentId,
            'stage' => StudentReferralStudentStatisticsModel::STAGE_TRIAL,
            'create_time' => $time
        ];
        StudentReferralStudentDetailModel::batchInsert($batchInsertData);
        //判断是否记录统计数据
        $statisticsId = true;
        if (empty($bindReferralInfo)) {
            //插入
            $statisticsId = StudentReferralStudentStatisticsModel::insertRecord(
                [
                    'student_id' => $studentId,
                    'referee_id' => $qrTicketIdentityData['user_id'],
                    'last_stage' => StudentReferralStudentStatisticsModel::STAGE_TRIAL,
                    'create_time' => $time,
                    'referee_employee_id' => $extParams['e'] ?? 0,
                    'activity_id' => $extParams['a'] ?? 0,
                ]
            );
        } elseif ($bindReferralInfo['last_stage'] < StudentReferralStudentStatisticsModel::STAGE_TRIAL) {
            //修改学生最新的节点数据为体验卡
            $statisticsId = StudentReferralStudentStatisticsModel::updateRecord($bindReferralInfo['id'],
                [
                    'last_stage' => StudentReferralStudentStatisticsModel::STAGE_TRIAL,
                ]
            );
        }
        if (empty($statisticsId)) {
            SimpleLogger::info('bind referral record fail', []);
            return false;
        }
        return true;
    }

    /**
     * 购买年卡数据记录
     * @param $studentId
     * @param $time
     * @return bool
     */
    public static function normalReferralRecord($studentId, $time)
    {
        //检测当前学生是否存在有效的绑定关系:必须存在有效的体验课绑定关系
        $bindReferralInfo = StudentReferralStudentStatisticsModel::getRecord(
            [
                'student_id' => $studentId,
                'last_stage[>=]' => StudentReferralStudentStatisticsModel::STAGE_TRIAL
            ],
            ['student_id', 'last_stage', 'id']);
        if (empty($bindReferralInfo)) {
            SimpleLogger::info('not bind referral relation', []);
            return false;
        }
        if ($bindReferralInfo['last_stage'] == StudentReferralStudentStatisticsModel::STAGE_FORMAL) {
            return true;
        }
        //记录年卡完成节点数据
        $batchInsertData[] = [
            'student_id' => $studentId,
            'stage' => StudentReferralStudentStatisticsModel::STAGE_FORMAL,
            'create_time' => $time
        ];
        StudentReferralStudentDetailModel::insertRecord($batchInsertData);
        //更新学生最新的节点数据为年卡
        $updateRes = StudentReferralStudentStatisticsModel::updateRecord($bindReferralInfo['id'],
            [
                'last_stage' => StudentReferralStudentStatisticsModel::STAGE_FORMAL,
            ]
        );
        if (empty($updateRes)) {
            SimpleLogger::info('update student normal stage fail', []);
            return false;
        }
        return true;
    }
}
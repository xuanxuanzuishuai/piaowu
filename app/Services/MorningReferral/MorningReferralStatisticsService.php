<?php
/**
 * 清晨转介绍
 * author: qingfeng.lian
 * date: 2022/6/24
 */

namespace App\Services\MorningReferral;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\MorningReferralDetailModel;
use App\Models\MorningReferralStatisticsModel;
use App\Models\QrInfoOpCHModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\Queue\Track\CommonTrackConsumerService;
use Exception;

class MorningReferralStatisticsService
{
    /**
     * 创建清晨转介绍关系
     * @param $data
     * @return bool|false
     */
    public static function createReferral($data)
    {
        SimpleLogger::info("create morning referral params:", $data);
        if (empty($data)) {
            return false;
        }
        // 获取用户信息
        $uuid = $data['uuid'] ?? '';
        if (empty($uuid)) {
            SimpleLogger::info("create morning referral uuid is not found", [$uuid]);
            return false;
        }
        // 获取学生状态
        $studentInfo = (new Morning())->getStudentList([$uuid])[0] ?? [];
        if (empty($studentInfo)) {
            SimpleLogger::info("create morning referral get user info is empty", [$uuid, $studentInfo]);
            return false;
        }
        // 读取用户是否存在转介绍关系
        list($isHasReferral, $hasReferralInfo) = self::checkStudentReferral($uuid);
        if ($isHasReferral) {
            SimpleLogger::info("create morning referral student is has referral", [$isHasReferral, $hasReferralInfo]);
            // 存在转介绍关系，不应再创建清晨转介绍关系
            // 如果是清晨存在了转介绍关系，那么判断是否是系统课，如果是系统课并且当前转介绍关系进度是未购买系统课则更新成购买系统课
            if (!empty($paramsData['msg_body']['order_type']) && $paramsData['msg_body']['order_type'] == CommonTrackConsumerService::ORDER_TYPE_NORMAL) {
                if (!empty($hasReferralInfo['morning_referral'])) {
                    if (!empty($hasReferralInfo['morning_referral']['last_stage']) && $hasReferralInfo['morning_referral']['last_stage'] == MorningReferralDetailModel::STAGE_TRIAL) {
                        $updateReferralStage = self::updateReferralStage($uuid);
                        SimpleLogger::info("update morning referral student stage res:", [$isHasReferral, $updateReferralStage]);
                    }
                }
            }
            return false;
        }
        // 接收qr_id
        $qrId = $data['metadata']['scene'] ?? '';
        if (empty($qrId)) {
            SimpleLogger::info("create morning referral scene is empty", $data);
            return false;
        }
        // 获取qr_id信息
        $qrInfo = QrInfoOpCHModel::getQrInfoById($qrId);
        if (empty($qrInfo)) {
            SimpleLogger::info("create morning referral qr info is not found", [$qrInfo]);
            return false;
        }
        // 只有学生是注册用户,体验课用户可以创建转介绍关系
        if (empty($studentInfo['status']) || !in_array($studentInfo['status'], [Constants::MORNING_STUDENT_STATUS_REGISTE, Constants::MORNING_STUDENT_STATUS_TRAIL])) {
            SimpleLogger::info("create morning referral student status not error", [$studentInfo]);
            return false;
        }
        // 读取推荐人信息
        $refUuid = json_decode($qrInfo['qr_data'], true)['student_uuid'] ?? '';
        $referralInfo = (new Morning())->getStudentList([$refUuid])[0] ?? [];
        if (empty($referralInfo)) {
            SimpleLogger::info("create morning referral student is empty", [$qrInfo, $referralInfo]);
            return false;
        }
        // 创建转介绍进度
        $createReferral = self::createStudentReferral($uuid, $referralInfo);
        if (empty($createReferral)) {
            SimpleLogger::info("create morning referral create student referral fail", [$createReferral]);
            return false;
        }
        return true;
    }

    /**
     * 检查用户是否在各个业务线存在转介绍关系
     * @param $studentUuid
     * @return array
     */
    public static function checkStudentReferral($studentUuid)
    {
        // 清晨是否有转介绍关系
        $morningHasReferral = MorningReferralStatisticsModel::getRecord(['student_uuid' => $studentUuid]);
        if (!empty($morningHasReferral)) {
            SimpleLogger::info("student referral is has morning.", [$morningHasReferral]);
            return [true, ['morning_referral' => $morningHasReferral]];
        }
        // 智能是否有转介绍关系
        $aiStudentInfo = DssStudentModel::getRecord(['uuid' => $studentUuid], ['id']);
        if (!empty($aiStudentInfo)) {
            $aiHasReferral = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $aiStudentInfo['id']]);
            if (!empty($aiHasReferral)) {
                SimpleLogger::info("student referral is has dss.", [$aiHasReferral]);
                return [true, ['ai_referral' => $aiHasReferral]];
            }
        }
        // 真人是否有转介绍关系
        $realStudentInfo = ErpStudentModel::getStudentInfoByUuid($studentUuid);
        if (!empty($realStudentInfo)) {
            $realHasReferral = ErpReferralUserRefereeModel::getStudentReferral($realStudentInfo['id']);
            if (!empty($realHasReferral)) {
                SimpleLogger::info("student referral is has real.", [$realHasReferral]);
                return [true, ['real_referral' => $realHasReferral]];
            }
        }
        return [false, []];
    }

    /**
     * 创建学生转介绍关系
     * @param $studentUuid
     * @param $referralInfo
     * @param int $stage
     * @param array $extraParams
     * @return bool
     */
    public static function createStudentReferral($studentUuid, $referralInfo, $stage = MorningReferralDetailModel::STAGE_TRIAL, $extraParams = [])
    {
        $time = time();
        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            // 只有是购买体验课才记录转介绍关系，否则只是记录进度
            if ($stage == MorningReferralDetailModel::STAGE_TRIAL) {
                $referralData = [
                    'student_uuid'         => $studentUuid,
                    'last_stage'           => $stage,
                    'referee_student_uuid' => $referralInfo['uuid'],
                    'buy_channel'          => $extraParams['channel_id'] ?? 0,
                    'create_type'          => $extraParams['create_type'] ?? 0,
                    'order_id'             => $extraParams['order_id'] ?? '',
                    'create_time'          => $time,
                ];
                $refRes = MorningReferralStatisticsModel::insertRecord($referralData);
                if (empty($refRes)) {
                    SimpleLogger::info("create student referral fail.", [$studentUuid, $referralData]);
                    throw new RunTimeException(['create student referral fail.']);
                }
                // 补全注册进度信息
                $detailData[] = [
                    'student_uuid' => $studentUuid,
                    'stage'        => MorningReferralDetailModel::STAGE_REGISTER,
                    'create_time'  => ErpStudentModel::getRecord(['uuid' => $studentUuid], ['create_time'])['create_time'] ?? 0,
                ];
            } elseif ($stage == MorningReferralDetailModel::STAGE_FORMAL) {
                // 购买年卡（系统课）
                $updateData = [
                    'last_stage' => $stage,
                ];
                $where = [
                    'student_uuid' => $studentUuid,
                ];
                $refRes = MorningReferralStatisticsModel::batchUpdateRecord([], $where);
                if (empty($refRes)) {
                    SimpleLogger::info("update student referral stage fail.", [$studentUuid, $where, $updateData]);
                    throw new RunTimeException(['update student referral stage fail.']);
                }
            }

            // 记录当前进度
            $detailData[] = [
                'student_uuid' => $studentUuid,
                'stage'        => $stage,
                'create_time'  => $time,
            ];
            $detailRes = MorningReferralDetailModel::batchInsert($detailData);
            if (empty($detailRes)) {
                SimpleLogger::info("create student referral detail fail.", [$studentUuid, $detailData]);
                throw new RunTimeException(['create student referral detail fail.']);
            }
            $db->commit();
        } catch (Exception $e) {
            SimpleLogger::info("create student referral fail.", [$studentUuid, $e->getMessage()]);
            $db->rollBack();
        }
        return true;
    }

    /**
     * 更新转介绍关系进度为购买年卡
     * @param $studentUuid
     * @return bool
     */
    public static function updateReferralStage($studentUuid)
    {
        return self::createStudentReferral($studentUuid, [], MorningReferralDetailModel::STAGE_FORMAL);
    }
}
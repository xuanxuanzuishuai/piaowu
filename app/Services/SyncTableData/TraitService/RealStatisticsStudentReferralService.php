<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData\TraitService;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpGenericWhitelistModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentAttributeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealStudentReferralInfoModel;
use App\Services\StudentServices\ErpStudentService;
use ClickHouseDB\Exception\QueryException;

class RealStatisticsStudentReferralService extends StatisticsStudentReferralBaseAbstract
{
    /**
     * 更新推荐的学员购买体验卡和年卡的数量
     * @param $studentUuid
     * @return bool
     */
    public function updateStudentReferralStatistics($studentUuid): bool
    {
        $info = RealStudentReferralInfoModel::getRecord(['student_uuid' => $studentUuid]);
        // 不存在转介绍关系-创建转介绍关系
        if (empty($info)) {
            SimpleLogger::info('RealStatisticsStudentReferralService::updateStudentReferralStatistics', [
                'msg'          => 'not found referral',
                'info'         => $info,
                'student_uuid' => $studentUuid,
            ]);
            try {
                RealStudentReferralInfoModel::insertRecord([
                    'student_uuid' => $studentUuid,
                ]);
            } catch (QueryException $e) {
                SimpleLogger::info('RealStatisticsStudentReferralService::updateStudentReferralStatistics', [
                    'msg' => 'create real student referral info error',
                    'err' => $e->getMessage(),
                ]);
            }
        }
        $update = self::computeStudentReferralStatisticsInfo(['uuid' => $studentUuid]);
        // where 条件不更新
        unset($update['student_uuid']);
        RealStudentReferralInfoModel::batchUpdateRecord($update, ['student_uuid' => $studentUuid]);
        return true;
    }

    /**
     * 获取真人周周领奖白明单
     * @param int $isPayStatus
     * @param bool $isRemainNum
     * @param int $studentId
     * @return array
     */
    public function getRealWhiteList($isPayStatus = Constants::REAL_COURSE_YES_PAY, $isRemainNum = true, $studentId = 0)
    {
        $sql = 'SELECT s.id as student_id,s.uuid,s.country_code, sp.first_pay_time,w.scene_value FROM ' . ErpGenericWhitelistModel::getTableNameWithDb() . ' as w ' .
            ' inner join ' . ErpStudentModel::getTableNameWithDb() . ' as s on s.uuid=w.scene_key' .
            ' inner join ' . ErpStudentAppModel::getTableNameWithDb() . ' as sp on sp.app_id=' . Constants::REAL_APP_ID . ' and sp.student_id=s.id' .
            ' WHERE w.app_id=' . Constants::REAL_APP_ID . ' and w.enabled=' . Constants::STATUS_TRUE;
        if (!empty($studentId)) {
            $sql .= ' and s.id=' . $studentId;
        }
        $list = MysqlDB::getDB()->queryAll($sql);
        foreach ($list as $key => &$item) {
            $whiteInfo = json_decode($item['scene_value'], true);
            foreach ($whiteInfo['order_courses'] as $_course) {
                foreach ($_course as $_w) {
                    // 要求付费用户 - 非付费的移除
                    if ($isPayStatus == Constants::REAL_COURSE_YES_PAY && empty($_w['pay_source'])) {
                        unset($list[$key]);
                        continue;
                    }
                    // 要求有剩余数量 - 没有剩余数量移除
                    if ($isRemainNum && empty($_w['remain_num'])) {
                        unset($list[$key]);
                        continue;
                    }
                    // 是付费，并且有课程数
                    break 2;
                }
            }
            unset($_w);

            $item['scene_value'] = $whiteInfo;
            $item['first_pay_time'] = ErpStudentService::computeFirstTime($item['first_pay_time'], $whiteInfo);
        }
        return $list;
    }

    /**
     * 计算学生转介绍统计信息
     * 推荐人的uuid 一定不能为空
     * @param $refereeInfo
     * @return array
     */
    public static function computeStudentReferralStatisticsInfo($refereeInfo)
    {
        $db = MysqlDB::getDB();
        $refTable = ErpReferralUserRefereeModel::getTableNameWithDb();
        $stuTable = ErpStudentModel::getTableNameWithDb();
        $stuAttrTable = ErpStudentAttributeModel::getTableNameWithDb();
        $_insertData = [
            'student_uuid'            => $refereeInfo['uuid'],
            'referral_num'            => 0,
            'now_referral_trail_num'  => 0,
            'now_referral_normal_num' => 0,
        ];
        $refereeUuid = $refereeInfo['uuid'] ?? '';
        $refereeId = $refereeInfo['id'] ?? 0;
        if (empty($refereeUuid)) {
            return $_insertData;
        }
        if (empty($refereeId['id'])) {
            $refereeId = ErpStudentModel::getRecord(['uuid' => $refereeUuid], ['id'])['id'] ?? 0;
        }
        $stuAttrList = ErpStudentAttributeModel::getStudentAttrTrailFinishedAndNormal();
        // 查询推荐人下所有被推荐人信息和生命周期
        $attrSql = 'select r.user_id,s.uuid as student_uuid,a.attribute_id' .
            ' from ' . $refTable . ' as r' .
            ' left join ' . $stuTable . ' as s on s.id=r.user_id' .
            ' left join ' . $stuAttrTable . ' as a on a.user_uuid=s.uuid and a.attribute_id in (' . implode(',', $stuAttrList) . ')' .
            ' where r.referee_id=' . $refereeId;
        $list = $db->queryAll($attrSql);
        $_insertData['referral_num'] = count($list);
        $repeatUuid = [];
        foreach ($list as $_attr) {
            // 同一个用户有多个attribute_id时，不需要重复处理
            if (isset($repeatUuid[$_attr['student_uuid']])) {
                continue;
            }
            $repeatUuid[$_attr['student_uuid']] = 1;

            // 体验已完成
            if ($_attr['attribute_id'] == ErpStudentAttributeModel::REAL_PERSON_LIFE_CYCLE_TRIAL_FINISHED) {
                $_insertData['now_referral_trail_num'] += 1;
            }
            // 已付费的
            if (in_array($_attr['attribute_id'], [
                ErpStudentAttributeModel::REAL_PERSON_LIFE_CYCLE_LEARNING,
                ErpStudentAttributeModel::REAL_PERSON_LIFE_CYCLE_WAIT_RENEW,
                ErpStudentAttributeModel::REAL_PERSON_LIFE_CYCLE_EXPIRED,
            ])) {
                $_insertData['now_referral_normal_num'] += 1;
            }
        }
        unset($_attr);
        return $_insertData;
    }
}
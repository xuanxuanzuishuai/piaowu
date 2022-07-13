<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\StudentService;

/**
 * 智能用户在分享活动中特殊逻辑服务类
 */
class DssService extends LimitTimeActivityBaseAbstract
{
    public function __construct($studentInfo, $fromType)
    {
        $this->appId       = Constants::SMART_APP_ID;
        $this->studentInfo = $studentInfo;
        $this->fromType    = $fromType;
    }

    /**
     * 根据uuid批量获取用户信息
     * @param array $uuids
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByUUID(array $uuids, array $fields = []): array
    {
        $list = DssStudentModel::getRecords(['uuid' => $uuids], $fields);
        return is_array($list) ? $list : [];
    }

    /**
     * 根据手机号获取用户信息
     * @param array $mobiles
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByMobile(array $mobiles, array $fields = []): array
    {
        $list = DssStudentModel::getRecords(['mobile' => $mobiles], $fields);
        return is_array($list) ? $list : [];
    }

    /**
     * 根据uuid批量获取用户信息
     * @param string $name
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByName(string $name, array $fields = []): array
    {
        $list = DssStudentModel::getRecords(['name[~]' => $name], $fields);
        return is_array($list) ? $list : [];
    }

    /**
     * 学生付费状态检测
     * @return array
     * @throws RunTimeException
     */
    public function studentPayStatusCheck(): array
    {
        //学生信息
        $userDetail = StudentService::dssStudentStatusCheck($this->studentInfo['user_id'], false,
            null);
        if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
            SimpleLogger::error('student status no satisfy', [$userDetail]);
            throw new RunTimeException(['no_in_progress_activity']);
        }
        return $userDetail;
    }

    /**
     * 获取智能账户邀请购买体验卡并且创建转介绍关系的学生数量
     * @return int
     */
    public function getStudentReferralOrBuyTrailCount(): int
    {
        return StudentReferralStudentStatisticsModel::getReferralCountGroupByStage($this->studentInfo['user_id'],
            StudentReferralStudentStatisticsModel::STAGE_TRIAL);
    }
}
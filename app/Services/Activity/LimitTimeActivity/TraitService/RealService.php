<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\StudentServices\ErpStudentService;

/**
 * 真人用户在分享活动中特殊逻辑服务类
 */
class RealService extends LimitTimeActivityBaseAbstract
{
    public function __construct($studentInfo, $fromType)
    {
        $this->appId = Constants::REAL_APP_ID;
        $this->studentInfo = $studentInfo;
        $this->fromType = $fromType;
    }

    /**
     * 根据uuid批量获取用户信息
     * @param array $uuids
     * @param array $fields
     * @return array
     */
    public function getStudentInfoByUUID(array $uuids, array $fields = []): array
    {
        $list = ErpStudentModel::getRecords(['uuid' => $uuids], $fields);
        return is_array($list) ? array_column($list, null, 'uuid') : [];
    }

    /**
     * 根据手机号获取用户信息
     * @param array $mobiles
     * @param array $fields
     * @return array
     */
    public static function getStudentInfoByMobile(array $mobiles, array $fields = []): array
    {
        $list = ErpStudentModel::getRecords(['mobile' => $mobiles], $fields);
        return is_array($list) ? $list : [];
    }

    /**
     * 根据name批量获取用户信息
     * @param string $name
     * @param array $fields
     * @param int[] $limitArr
     * @return array
     */
    public function getStudentInfoByName(string $name, array $fields = [], $limitArr = [0, 1000]): array
    {
        $list = ErpStudentModel::getRecords(['name[~]' => $name, 'ORDER' => $limitArr], $fields);
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
        $userCourseData = ErpStudentService::getStudentCourseData($this->studentInfo['uuid']);
        if ($userCourseData['is_valid_pay'] != Erp::USER_IS_PAY_YES) {
            SimpleLogger::error('student status no satisfy', [$userCourseData]);
            throw new RunTimeException(['no_in_progress_activity']);
        }
        $userCourseData['student_status'] = $userCourseData['is_valid_pay'];
        return $userCourseData;
    }

    /**
     * 学生状态汉化
     * @param int $studentStatus
     * @return string
     */
    public function studentPayStatusZh(int $studentStatus): string
    {
        //todo
        return '';
    }

    /**
     * 获取创建转介绍关系的学生数量
     * @return int
     */
    public function getStudentReferralOrBuyTrailCount(): int
    {
        return StudentReferralStudentStatisticsModel::getCount([
            'referee_id'   => $this->studentInfo['user_id'],
            'referee_type' => ErpReferralUserRefereeModel::REFEREE_TYPE_STUDENT,
            'app_id'       => Constants::REAL_APP_ID
        ]);
    }

    /**
     * 获取员工信息
     * @param array $employeeIds
     * @return array
     */
    public function getEmployeeInfo(array $employeeIds): array
    {
        return array_column(EmployeeModel::getRecords(['id' => $employeeIds]) ?: [], null, 'id');
    }
}
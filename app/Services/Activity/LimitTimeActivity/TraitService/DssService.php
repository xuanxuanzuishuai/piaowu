<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssStudentModel;
use App\Models\OperationActivityModel;
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
        $userDetail                   = StudentService::dssStudentStatusCheck($this->studentInfo['user_id'], false,
            null);
        $userDetail['student_status'] = 2;
        if ($userDetail['student_status'] != DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
            throw new RunTimeException(['no_in_progress_activity']);
        }
        return $userDetail;
    }

    /**
     * 获取活动数据，并检测活动参与条件
     * @param int $countryCode
     * @param int $firstPayVipTime
     * @return array
     * @throws RunTimeException
     */
    public function getActivity(int $countryCode, int $firstPayVipTime): array
    {
        //查询活动
        $activityInfo = self::getActivityBaseData($this->appId, $countryCode);
        if (empty($activityInfo)) {
            throw new RunTimeException(['no_in_progress_activity']);
        }
        //部分付费
        if ($activityInfo['target_user_type'] == OperationActivityModel::TARGET_USER_PART) {
            $filterWhere = json_decode($activityInfo[0]['target_user'], true);
            if ($firstPayVipTime < $filterWhere['target_use_first_pay_time_start'] ||
                $firstPayVipTime > $filterWhere['target_use_first_pay_time_end']) {
                throw new RunTimeException(['no_in_progress_activity']);
            }
        }
        return $activityInfo;
    }
}
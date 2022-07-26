<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\StudentService;
use App\Services\UserService;

/**
 * 智能用户在分享活动中特殊逻辑服务类
 */
class DssService extends LimitTimeActivityBaseAbstract
{
	public function __construct($studentInfo, $fromType)
	{
		$this->appId = Constants::SMART_APP_ID;
		$this->studentInfo = $studentInfo;
		$this->fromType = $fromType;
		$this->busiType = DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP;
	}

	/**
	 * 根据uuid批量获取用户信息
	 * @param array $uuids
	 * @param array $fields
	 * @return array
	 */
	public function getStudentInfoByUUID(array $uuids, array $fields = []): array
	{
        if (empty($uuids)) return [];
        if (!empty($fields)) {
            $fields = array_merge($fields, ['uuid']);
        }
        $list = DssStudentModel::getRecords(['uuid' => $uuids], $fields);
		return is_array($list) ? array_column($list, null, 'uuid') : [];
	}

	/**
	 * 根据手机号获取用户信息
	 * @param array $mobiles
	 * @param array $fields
	 * @return array
	 */
	public function getStudentInfoByMobile(array $mobiles, array $fields = []): array
	{
        if (empty($mobiles)) return [];
		$list = DssStudentModel::getRecords(['mobile' => $mobiles], $fields);
		return is_array($list) ? $list : [];
	}

    /**
     * 根据名字批量获取用户信息
     * @param string $name
     * @param array $fields
     * @param int[] $limitArr
     * @return array
     */
    public function getStudentInfoByName(string $name, array $fields = [], $limitArr = [0,1000]): array
    {
        if (empty($name)) return [];
        $list = DssStudentModel::getRecords(['name[~]' => $name, 'LIMIT' => $limitArr], $fields);
        return is_array($list) ? $list : [];
    }

	/**
	 * 学生检测是否付费有效检测
	 * @return array
	 * @throws RunTimeException
	 */
	public function studentPayStatusCheck(): array
	{
		//首先检测是否付费有效检测
		$studentIdentity = UserService::checkDssStudentIdentityIsNormal($this->studentInfo['user_id']);
		$this->studentInfo['pay_status_check_res'] = $studentIdentity[0];
		if ($studentIdentity[0] !== true) {
			throw new RunTimeException(['student_pay_status_no']);
		}
		//此处首次付费时间取dss接口提供的数据，因为存在白名单账户，数据表的pay_vip_time有可能不是白名单中设置的first_pay_time
		$this->studentInfo['first_pay_time'] = $studentIdentity[1]['first_pay_time'];
		return $studentIdentity[1];
	}

	/**
	 * 获取学生状态
	 * @return array
	 * @throws RunTimeException
	 */
	public function getStudentStatus(): array
	{
		//再次检测学生信息
		$statusCheckData = StudentService::dssStudentStatusCheck($this->studentInfo['user_id'], false, null);
		return [
			'student_status'    => $statusCheckData['student_status'],
			'student_status_zh' => DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$statusCheckData['student_status']]
		];
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

	/**
	 * 获取员工信息
	 * @param array $employeeIds
	 * @return array
	 */
	public function getEmployeeInfo(array $employeeIds): array
	{
		return array_column(DssEmployeeModel::getRecords(['id' => $employeeIds]) ?: [], null, 'id');
	}

	/**
	 * 限时活动详情页面
	 * @return string
	 */
    public function getActivityDetailHtmlUrl(): string
    {
        $url = DictConstants::get(DictConstants::DSS_JUMP_LINK_CONFIG, 'limit_time_activity_detail');
        return is_string($url) ? $url : '';
    }

	/**
	 * 上传截图记录详情页面
	 * @return string
	 */
    public function getActivityRecordListHtmlUrl(): string
    {
        $url = DictConstants::get(DictConstants::DSS_JUMP_LINK_CONFIG, 'limit_time_activity_record_list');
        return is_string($url) ? $url : '';
    }
}
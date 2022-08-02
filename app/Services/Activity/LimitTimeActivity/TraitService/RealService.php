<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Services\ErpUserService;
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
		$this->busiType = DssUserWeiXinModel::REAL_MINI_BUSI_TYPE;

	}

	/**
	 * 根据uuid批量获取用户信息
	 * @param array $uuids
	 * @param array $fields
	 * @return array
	 */
	public function getStudentInfoByUUID(array $uuids, array $fields = []): array
	{
		if (empty($uuids)) {
			return [];
		}
		if (!empty($fields)) {
			$fields = array_merge($fields, ['uuid']);
		}
		$list = ErpStudentModel::getRecords(['uuid' => $uuids], $fields);
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
		if (empty($mobiles)) {
			return [];
		}
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
		if (empty($name)) {
			return [];
		}
		$list = ErpStudentModel::getRecords(['name[~]' => $name, 'LIMIT' => $limitArr], $fields);
		return is_array($list) ? $list : [];
	}

	/**
	 * 学生付费是否有效状态检测
	 * @return array
	 * @throws RunTimeException
	 */
	public function studentPayStatusCheck(): array
	{
		$studentIdAttribute = ErpStudentService::getStudentCourseData($this->studentInfo['uuid']);
		// 检查一下用户是否是有效用户，不是有效用户不可能有可参与的活动
		$this->studentInfo['pay_status_check_res'] = $studentIdAttribute['is_valid_pay'];
		if (empty($studentIdAttribute['is_valid_pay'])) {
			throw new RunTimeException(['student_pay_status_no'], [$studentIdAttribute]);
		}
		$this->studentInfo['first_pay_time'] = $studentIdAttribute['first_pay_time'];
		return $studentIdAttribute;
	}

	/**
	 * 获取学生状态
	 * @return array
	 */
	public function getStudentStatus(): array
	{
		$studentPayStatus = ErpUserService::getStudentStatus($this->studentInfo['user_id']);
		return [
			'student_status'    => $studentPayStatus['pay_status'],
			'student_status_zh' => $studentPayStatus['status_zh']
		];
	}

	/**
	 * 获取创建转介绍关系的学生数量
	 * @return int
	 */
	public function getStudentReferralOrBuyTrailCount(): int
	{
		return ErpReferralUserRefereeModel::getCount([
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

	// 限时活动详情页面
	public function getActivityDetailHtmlUrl(): string
	{
		$url = RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'limit_time_activity_detail');
		return is_string($url) ? $url : '';
	}

	//上传截图记录详情页面
	public function getActivityRecordListHtmlUrl(): string
	{
		$url = RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'limit_time_activity_record_list');
		return is_string($url) ? $url : '';
	}
}
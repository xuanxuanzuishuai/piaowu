<?php

namespace App\Services\Activity\LimitTimeActivity;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\LimitTimeActivity\LimitTimeActivityAwardRuleModel;
use App\Models\LimitTimeActivity\LimitTimeActivityHtmlConfigModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\LimitTimeActivity\LimitTimeActivitySharePosterModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Services\Activity\LimitTimeActivity\TraitService\LimitTimeActivityBaseAbstract;
use App\Services\ActivityService;
use App\Services\PosterService;
use App\Services\Queue\Activity\LimitTimeAward\LimitTimeAwardProducerService;
use App\Services\SharePosterService;

/**
 * 限时有奖活动客户端功能服务类
 */
class LimitTimeActivityClientService
{

	/**
	 * 获取活动基础数据
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @return array
	 * @throws RunTimeException
	 */
	public static function baseData(LimitTimeActivityBaseAbstract $serviceObj): array
	{
		$data = [
			'list'              => [],// 海报列表
			'activity'          => [],// 活动详情
			'student_info'      => [],// 学生详情
			"is_have_activity"  => false,//是否有可参与的活动
			'student_status_zh' => '',//学生付费状态
		];
		$studentStatusData = $serviceObj->getStudentStatus();
		$data['student_status_zh'] = $studentStatusData['student_status_zh'];
		//获取活动数据
		$data['activity'] = self::getStudentCanJoinActivityList($serviceObj);
		if (empty($data['activity'])) {
			return $data;
		}
		$data['student_info'] = [
			'nickname'   => $serviceObj->studentInfo['name'],
			'headimgurl' => $serviceObj->studentInfo['thumb_oss_url'],
		];
		$data['is_have_activity'] = true;
		//格式化活动数据
		$data['activity'] = ActivityService::formatData($data['activity']);
		$sharePosterGroupList = $serviceObj->getSharePosterList(json_decode($data['activity']['share_poster'], true),
			$data['activity']['first_poster_type_order']);
		$sharePosterList = [];
		array_map(function ($pv) use (&$sharePosterList) {
			$sharePosterList = array_merge($sharePosterList, $pv);
		}, $sharePosterGroupList);
		//海报打水印
		$data['list'] = PosterService::batchSynthesizePosterAndQr(
			$serviceObj->appId,
			DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP,
			DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
			$sharePosterList,
			$data['activity']['activity_id'],
			$data['activity']['activity_id'],
			$serviceObj->studentInfo['user_id'],
			$studentStatusData['student_status'],
			$serviceObj->getChannelByFromType(),
			false);
		return $data;
	}

	/**
	 * 获取可参与活动,检测活动参与条件
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @return array
	 */
	private static function getStudentCanJoinActivityList(LimitTimeActivityBaseAbstract $serviceObj): array
	{
		try {
			//学生状态检测
			$serviceObj->studentPayStatusCheck();
			//查询活动
			$nowTime = time();
			$where = [
				'start_time_e'          => $nowTime,
				'end_time_s'            => $nowTime,
				'enable_status'         => OperationActivityModel::ENABLE_STATUS_ON,
				'app_id'                => $serviceObj->appId,
				'activity_country_code' => OperationActivityModel::getWeekActivityCountryCode($serviceObj->studentInfo['country_code']),
			];
			$activityList = LimitTimeActivityModel::searchList($where, [0, 1], [],
				$serviceObj::RETURN_ACTIVITY_BASE_DATA_FIELDS);
			if (empty($activityList[0])) {
				return [];
			}
			$activityInfo = $activityList[0][0];
			//部分付费
			if ($activityInfo['target_user_type'] == OperationActivityModel::TARGET_USER_PART) {
				$filterWhere = json_decode($activityInfo['target_user'], true);
				//首次付费时间校验/邀请人数量检验
				if (!empty($filterWhere['target_user_first_pay_time_start']) &&
					($serviceObj->studentInfo['first_pay_time'] < $filterWhere['target_user_first_pay_time_start'] ||
						$serviceObj->studentInfo['first_pay_time'] > $filterWhere['target_user_first_pay_time_end'])) {
					return [];
				}
				if (!empty((int)$filterWhere['invitation_num'])) {
					$invitationNum = $serviceObj->getStudentReferralOrBuyTrailCount();
					if ($invitationNum < $filterWhere['invitation_num']) {
						return [];
					}
				}
			}
			$activityInfo['ext'] = [
				'award_rule' => Util::textDecode($activityInfo['award_rule']),
				'remark'     => Util::textDecode($activityInfo['remark']),
			];
			unset($activityInfo['award_rule']);
			unset($activityInfo['remark']);
			return $activityInfo;
		} catch (RunTimeException $e) {
			SimpleLogger::error('activity condition check error', [$e->getMessage()]);
			return [];
		}

	}

	/**
	 * 获取参与记录
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @param int $page
	 * @param int $limit
	 * @return array
	 */
	public static function joinRecords(LimitTimeActivityBaseAbstract $serviceObj, int $page, int $limit): array
	{
		$recordsResult = [
			'total_count' => 0,
			'list'        => [],
		];
		$records = LimitTimeActivitySharePosterModel::searchJoinRecords($serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			['group' => ['sp.activity_id']],
			$page,
			$limit);
		if (empty($records[0])) {
			return $recordsResult;
		}
		$recordsResult['total_count'] = $records[1];
		//活动基础信息
		$activityIds = array_column($records[0], 'activity_id');
		$activityData = array_column(LimitTimeActivityModel::getRecords(
			['activity_id' => $activityIds, 'ORDER' => ['start_time' => 'DESC', 'id' => 'DESC']],
			['activity_name', 'activity_id', 'start_time', 'end_time', 'activity_type', 'enable_status']), null, 'activity_id');
		$dictData = DictConstants::getTypesMap([
			DictConstants::ACTIVITY_ENABLE_STATUS['type'],
			DictConstants::SEND_AWARD_STATUS['type'],
			DictConstants::ACTIVITY_TIME_STATUS['type'],
		]);
		//活动奖励规则信息
		$activityAwardRuleData = LimitTimeActivityAwardRuleModel::getActivityAwardRuleList($activityIds);
		//获取活动参与详细信息
		$recordsDetail = LimitTimeActivitySharePosterModel::searchJoinRecords(
			$serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			[
				'activity_id' => $activityIds
			],
			0);
		$recordsDetailFormat = [];
		foreach ($recordsDetail[0] as $rdv) {
			$recordsDetailFormat[$rdv['activity_id']][$rdv['task_num']] = $rdv;
		}
		//组装数据
		foreach ($activityData as $info) {
			//活动奖励节点状态
			$tmpTaskList = self::formatActivityTaskListData($info, $activityAwardRuleData[$info['activity_id']],
				$recordsDetailFormat[$info['activity_id']]);
			$tmpVerifyStatusSum = array_fill_keys([SharePosterModel::VERIFY_STATUS_WAIT, SharePosterModel::VERIFY_STATUS_UNQUALIFIED, SharePosterModel::VERIFY_STATUS_QUALIFIED], 0);
			foreach ($tmpTaskList as $tmpVal) {
				$tmpVerifyStatusSum[$tmpVal['verify_status']] += 1;
			}
			//活动基础数据
			$recordsResult['list'][] = [
				'activity_id'        => $info['activity_id'],
				'activity_type'      => $info['activity_type'],
				'activity_name'      => $info['activity_name'] . '(' . date('m.d',
						$info['start_time']) . '-' . date('m.d', $info['end_time']) . ')',
				'activity_status_zh' => ($info['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE) ?
					$dictData[DictConstants::ACTIVITY_ENABLE_STATUS['type']][OperationActivityModel::ENABLE_STATUS_DISABLE]['value'] :
					$dictData[DictConstants::ACTIVITY_TIME_STATUS['type']][OperationActivityModel::dataMapToTimeStatus($info['start_time'],
						$info['end_time'])]['value'],
				'success'            => $tmpVerifyStatusSum[SharePosterModel::VERIFY_STATUS_QUALIFIED],
				'fail'               => $tmpVerifyStatusSum[SharePosterModel::VERIFY_STATUS_UNQUALIFIED],
				'wait'               => $tmpVerifyStatusSum[SharePosterModel::VERIFY_STATUS_WAIT],
				'task_list'          => array_values($tmpTaskList),
				'task_num_count'     => count($tmpTaskList),
			];
		}
		return $recordsResult;
	}

	/**
	 * 格式化处理活动任务节点数据
	 * @param array $activityData
	 * @param array $taskList
	 * @param array $recordsDetailFormat
	 * @param bool $isStayHaveVerifySuccessTask
	 * @return array
	 */
	private static function formatActivityTaskListData(
		array $activityData,
		array $taskList,
		array $recordsDetailFormat,
		bool $isStayHaveVerifySuccessTask = true
	): array {
		$dictData = DictConstants::getTypesMap(
			[
				DictConstants::SEND_AWARD_STATUS['type'],
				DictConstants::ACTIVITY_ENABLE_STATUS['type'],
			]);
		$formatTaskList = [];
		$awardType = next($taskList)['award_type'];
		$maxTaskNum = max(array_column($taskList, 'task_num'));
		$tmpTotalAwardAmount = 0;
		for ($i = 1; $i <= $maxTaskNum; $i++) {
			if ($isStayHaveVerifySuccessTask == false &&
				isset($recordsDetailFormat[$i]) &&
				$recordsDetailFormat[$i]['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
				continue;
			}
			//累计打卡活动：奖励数量需要进行累计
			if ($activityData['activity_type'] == OperationActivityModel::ACTIVITY_TYPE_FULL_ATTENDANCE) {
				$tmpTotalAwardAmount += (int)$taskList[$i]['award_amount'];
			}
			$tmpAwardStatus = isset($recordsDetailFormat[$i]) ? (int)$recordsDetailFormat[$i]['send_award_status'] : OperationActivityModel::SEND_AWARD_STATUS_NOT_OWN;

			$formatTaskList[$i] = [
				'task_num'        => $i,
				'award_amount'    => (isset($taskList[$i]['award_amount']) && $activityData['activity_type'] == OperationActivityModel::ACTIVITY_TYPE_FULL_ATTENDANCE)
					? $tmpTotalAwardAmount : (int)$taskList[$i]['award_amount'],
				'award_type'      => (int)$awardType,
				'verify_status'   => (int)$recordsDetailFormat[$i]['verify_status'],
				'award_status'    => $tmpAwardStatus,
				'award_status_zh' => $dictData[DictConstants::SEND_AWARD_STATUS['type']][$tmpAwardStatus]['value'],
			];
		}
		return $formatTaskList;
	}


	/**
	 * 获取可参与活动的任务列表
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @return array|array[]
	 */
	public static function activityTaskList(LimitTimeActivityBaseAbstract $serviceObj): array
	{
		$result = [
			'list' => [],
		];
		$activityData = self::getStudentCanJoinActivityList($serviceObj);
		if (empty($activityData)) {
			return $result;
		}
		//活动任务列表
		$taskList = LimitTimeActivityAwardRuleModel::getActivityAwardRule($activityData['activity_id']);
		//获取活动参与详细信息
		$recordsDetail = array_column(LimitTimeActivitySharePosterModel::searchJoinRecords(
			$serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			['activity_id' => $activityData['activity_id']],
			0)[0], null, 'task_num');
		$formatTaskList = self::formatActivityTaskListData($activityData, $taskList, $recordsDetail, false);
		foreach ($formatTaskList as $tv) {
			$result['list'][] = [
				'activity_id' => $activityData['activity_id'],
				'task_num'    => $tv['task_num'],
				'name'        => $activityData['activity_name'] . '-' . $tv['task_num'],
			];
		}
		return $result;
	}

	/**
	 * 获取已参与活动的任务审核列表
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @param array $params
	 * @param int $page
	 * @param int $limit
	 * @return array
	 */
	public static function activityTaskVerifyList(LimitTimeActivityBaseAbstract $serviceObj, array $params, int $page, int $limit): array
	{
		$result = [
			'total_count' => 0,
			'list'        => []
		];
		//获取活动参与记录
		$recordsDetail = LimitTimeActivitySharePosterModel::searchJoinRecords(
			$serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			['activity_id' => $params['activity_id'], 'order' => ['id' => 'DESC']],
			$page, $limit, ['a.activity_name', 'a.start_time', 'a.end_time']);
		if (empty($recordsDetail[0])) {
			return $result;
		}
		$result['total_count'] = $recordsDetail[1];
		//组合数据
		foreach ($recordsDetail[0] as $item) {
			$tmpList = [];
			$tmpList['task_name'] = $item['activity_name'] . '-' . $item['task_num'];
			$tmpList['create_time'] = date("Y.m.d H:i", $item['create_time']);
			$tmpList['verify_status'] = $item['verify_status'];
			$tmpList['id'] = $item['id'];
			$tmpList['task_num'] = $item['task_num'];
			$tmpList['can_upload'] = (int)self::checkIsCanAgainDone($item['start_time'], $item['end_time']);
			$result['list'][] = $tmpList;
		}
		return $result;
	}

	/**
	 * 检测活动任务是否还可以再次完成
	 * @param int $startTime
	 * @param int $endTime
	 * @return bool
	 */
	private static function checkIsCanAgainDone(int $startTime, int $endTime): bool
	{
		$nowTime = time();
		if ($nowTime <= $endTime && $nowTime >= $startTime) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 获取任务审核详情
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @param int $detailId
	 * @return array|array[]
	 */
	public static function activityTaskVerifyDetail(LimitTimeActivityBaseAbstract $serviceObj, int $detailId): array
	{
		$result = [
			'poster' => []
		];
		//获取活动参与记录
		$recordsDetail = LimitTimeActivitySharePosterModel::searchJoinRecords(
			$serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			['id' => $detailId],
			0, 10, ['a.activity_name', 'a.start_time', 'a.end_time', 'sp.award_type', 'sp.award_amount']);
		if (empty($recordsDetail[0][0])) {
			return $result;
		}
		$formatRes = SharePosterService::formatPosterVerifyResult($recordsDetail[0][0]);
		//设置海报是否可以再次上传标识
		$result['poster']['can_upload'] = (int)self::checkIsCanAgainDone($recordsDetail[0][0]['start_time'],
			$recordsDetail[0][0]['end_time']);
		if ($recordsDetail[0]['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
			$result['can_upload'] = Constants::STATUS_FALSE;
		}
		$result['poster']['poster_status'] = $formatRes['verify_status'];
		$result['poster']['status_name'] = $formatRes['status_name'];
		$result['poster']['activity_id'] = $formatRes['activity_id'];
		$result['poster']['award_type'] = $formatRes['award_type'];
		$result['poster']['img_url'] = $formatRes['img_url'];
		$result['poster']['reason_str'] = $formatRes['reason_str'];
		$result['poster']['task_num'] = $formatRes['task_num'];
		$result['poster']['award_amount'] = $formatRes['award_amount'];
		return $result;
	}

	/**
	 * 获取活动奖励规则
	 * @param int $activityId
	 * @return string
	 */
	public static function awardRule(int $activityId): string
	{
		return Util::textDecode(LimitTimeActivityHtmlConfigModel::getRecord(['activity_id' => $activityId],
			'award_rule'));
	}

	/**
	 * 上传海报截图
	 * @param LimitTimeActivityBaseAbstract $serviceObj
	 * @param int $activityId
	 * @param int $taskNum
	 * @param string $imagePath
	 * @return int
	 * @throws RunTimeException
	 */
	public static function uploadSharePoster(
		LimitTimeActivityBaseAbstract $serviceObj,
		int $activityId,
		int $taskNum,
		string $imagePath
	): int {
		//获取活动数据
		$activity = self::getStudentCanJoinActivityList($serviceObj);
		if ($activityId != $activity['activity_id']) {
			throw new RunTimeException(['no_in_progress_activity']);
		}
		//加锁防止并发操作
		$lockRes = Util::setLock($serviceObj::UPLOAD_LOCK_KEY_PREFIX . $serviceObj->studentInfo['uuid'] . $activityId . $taskNum,
			5, 0);
		if (empty($lockRes)) {
			throw new RunTimeException(['frequent_operation']);
		}
		//获取参与记录
		$recordsDetail = LimitTimeActivitySharePosterModel::searchJoinRecords(
			$serviceObj->appId,
			[$serviceObj->studentInfo['uuid']],
			['activity_id' => $activityId, 'task_num' => $taskNum, 'order' => ['id' => 'DESC']],
			1, 1);
		if ($recordsDetail[0][0]['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
			throw new RunTimeException(['activity_task_node_is_complete']);
		}
		//组合数据
		$time = time();
		$data = [
			'student_uuid' => $serviceObj->studentInfo['uuid'],
			'activity_id'  => $activityId,
			'task_num'     => $taskNum,
			'image_path'   => $imagePath,
			'app_id'       => $serviceObj->appId,
		];
		//不存在或审核不通过：写入数据
		if (empty($recordsDetail[0][0]) || $recordsDetail[0][0]['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
			$data['create_time'] = $time;
			$insertId = $affectRows = LimitTimeActivitySharePosterModel::insertRecord($data);
		} else {
			//已存在：待审核修改数据
			$data['update_time'] = $time;
			$affectRows = LimitTimeActivitySharePosterModel::updateRecord($recordsDetail[0][0]['id'], $data);
			$insertId = $recordsDetail[0][0]['id'];
		}
		if (empty($affectRows)) {
			throw new RunTimeException(['share_poster_add_fail']);
		}
		//系统自动审核
		LimitTimeAwardProducerService::autoCheckProducer($insertId, $serviceObj->studentInfo['user_id'], 0);
		return $insertId;
	}
}
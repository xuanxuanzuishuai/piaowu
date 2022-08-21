<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class LotteryActivityModel extends Model
{
	public static $table = 'lottery_activity';
	// 参加用户来源类型 1：规则筛选 2：手动导入
	const USER_SOURCE_FILTER = 1;
	const USER_SOURCE_IMPORT = 2;

	// 中奖限制参与次数限制类型:1自定义2不限
	const MAX_HIT_TYPE_CUSTOM    = 1;
	const MAX_HIT_TYPE_UNLIMITED = 2;

	// 中奖时间段规则类型:1同活动时间 2自定义
	const HIT_TIMES_TYPE_KEEP_ACTIVITY = 1;
	const HIT_TIMES_TYPE_CUSTOM        = 2;

	//业务线和店铺对应关系
	const BUSINESS_MAP_SHOP = [
		Constants::REAL_APP_ID  => Constants::SALE_SHOP_VIDEO_PLAY_SERVICE,
		Constants::SMART_APP_ID => Constants::SALE_SHOP_AI_REFERRAL_SERVICE,
	];

	//允许多次编辑字段
	const ALLOW_UPDATE_COLUMNS = [
		'base_data'      => [
			'name',//活动名称
			'end_time',//活动结束时间
			'activity_desc',//活动规则
			'end_pay_time',//付款结束时间
			'update_time',
			'update_uuid',
			'status',
			'material_send_interval_hours',
		],
		'win_prize_rule' => [//中奖规则
			'low_pay_amount',
			'high_pay_amount',
			'award_level',
		],
		'awards'         => [
			'name',//奖品名称
			'weight',//奖品概率
			'num',//奖品数量
			'hit_times',//奖品中奖时间
			'hit_times_type',//奖品中奖时间类型
		]
	];

	/**
	 * 增加抽奖活动
	 * @param $addParamsData
	 * @return bool
	 */
	public static function add($addParamsData): bool
	{
		$db = MysqlDB::getDB();
		$db->beginTransaction();
		//活动表
		$lotteryId = self::insertRecord($addParamsData['base_data']);
		if (empty($lotteryId)) {
			SimpleLogger::error("lottery add error", []);
			$db->rollBack();
			return false;
		}
		//中奖规则
		$awardRuleRes = true;
		if (!empty($addParamsData['win_prize_rule'])) {
			$awardRuleRes = LotteryAwardRuleModel::batchInsert($addParamsData['win_prize_rule']);
		}
		if (empty($awardRuleRes)) {
			SimpleLogger::error("lottery award rule add error", []);
			$db->rollBack();
			return false;
		}
		//规则筛选可抽奖用户
		$lotteryFilterRes = true;
		if (!empty($addParamsData['lottery_times_rule'])) {
			$lotteryFilterRes = LotteryFilterUserModel::batchInsert($addParamsData['lottery_times_rule']);
		}
		if (empty($lotteryFilterRes)) {
			SimpleLogger::error("lottery filter user rule add error", []);
			$db->rollBack();
			return false;
		}
		//奖品数据
		$awardInfoRes = LotteryAwardInfoModel::batchInsert($addParamsData['awards']);
		if (empty($awardInfoRes)) {
			SimpleLogger::error("lottery award add error", []);
			$db->rollBack();
			return false;
		}
		$lotteryImportRes = true;
		if (!empty($addParamsData['import_user'])) {
			$lotteryImportRes = LotteryImportUserModel::batchInsert($addParamsData['import_user']);
		}
		if (empty($lotteryImportRes)) {
			SimpleLogger::error("lottery import user add error", []);
			$db->rollBack();
			return false;
		}
		$db->commit();
		return true;
	}

	/**
	 * 编辑
	 * @param $opActivityId
	 * @param $updateParamsData
	 * @return bool
	 */
	public static function update($opActivityId, $updateParamsData): bool
	{
		$db = MysqlDB::getDB();
		$db->beginTransaction();
		//基础数据修改
		$updateRows = self::batchUpdateRecord($updateParamsData['update_params_data']['base_data'], ['op_activity_id' => $opActivityId]);
		if (empty($updateRows)) {
			SimpleLogger::error("lottery update error", []);
			$db->rollBack();
			return false;
		}
		//删除中奖规则/筛选可抽奖用户奖品数据
		$commonDeleteWhere = ['op_activity_id' => $opActivityId];
		LotteryAwardRuleModel::batchDelete($commonDeleteWhere);
		//中奖规则
		$awardRuleRes = true;
		if (!empty($updateParamsData['update_params_data']['win_prize_rule'])) {
			$awardRuleRes = LotteryAwardRuleModel::batchInsert($updateParamsData['update_params_data']['win_prize_rule']);
		}
		if (empty($awardRuleRes)) {
			SimpleLogger::error("lottery award rule update error", []);
			$db->rollBack();
			return false;
		}
		//奖品数据
		if ($updateParamsData['update_params_data']['awards_update_sql'] != '') {
			$awardInfoRes = LotteryAwardInfoModel::batchUpdateRecordDifferentWhereAndData($updateParamsData['update_params_data']['awards_update_sql']);
		} else {
			LotteryAwardInfoModel::batchDelete($commonDeleteWhere);
			$awardInfoRes = LotteryAwardInfoModel::batchInsert($updateParamsData['update_params_data']['awards']);
		}
		if (empty($awardInfoRes)) {
			SimpleLogger::error("lottery award update/insert error", []);
			$db->rollBack();
			return false;
		}
		//导入名单数据
		$lotteryImportRes = true;
		if (!empty($updateParamsData['update_params_data']['import_user'])) {
			$lotteryImportRes = LotteryImportUserModel::batchInsert($updateParamsData['update_params_data']['import_user']);
		}
		if (empty($lotteryImportRes)) {
			SimpleLogger::error("lottery import user update error", []);
			$db->rollBack();
			return false;
		}
		//数据变更记录
		$changeLogRes = true;
		if (!empty($updateParamsData['mysql_change_data'])) {
			$changeLogRes = LotteryActivityChangeLogModel::batchInsert($updateParamsData['mysql_change_data']);
		}
		if (empty($changeLogRes)) {
			SimpleLogger::error("lottery change log insert error", []);
			$db->rollBack();
			return false;
		}
		$db->commit();
		return true;
	}
}
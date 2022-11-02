<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use PDO;

class WxSopsModel extends Model
{
	//表名称
	public static $table = "wx_sops";
	//执行周期类型1每天 2指定日期
	const EXEC_TYPE_EVERY_DAY     = 1;
	const EXEC_TYPE_APPOINTED_DAY = 2;

	/**
	 * 创建sop
	 * @param array $sopData
	 * @param array $sopDetailsData
	 * @return bool
	 */
	public static function add(array $sopData, array $sopDetailsData): bool
	{
		$db = MysqlDB::getDB();
		$db->beginTransaction();
		$sopId = $db->insertGetID(self::$table, $sopData);
		if (empty($sopId)) {
			$db->rollBack();
			return false;
		}
		foreach ($sopDetailsData as &$sdv) {
			$sdv['sop_id'] = $sopId;
		}
		$pdo = $db->insert(WxSopsDetailsModel::$table, $sopDetailsData);
		if ($pdo->errorCode() != PDO::ERR_NONE) {
			$db->rollBack();
			return false;
		}
		$db->commit();
		return true;
	}

	/**
	 * 修改
	 * @param int $sopId
	 * @param array $sopData
	 * @param array $sopDetailsData
	 * @param int $time
	 * @param string $employeeUuid
	 * @return bool
	 */
	public static function update(
		int $sopId,
		array $sopData,
		array $sopDetailsData,
		int $time,
		string $employeeUuid
	): bool {
		$db = MysqlDB::getDB();
		$db->beginTransaction();
		$sopUpdateAffectRows = $db->updateGetCount(self::$table, $sopData, ["id" => $sopId]);
		if (empty($sopUpdateAffectRows)) {
			$db->rollBack();
			return false;
		}
		$sopDetailsUpdateAffectRows = $db->updateGetCount(WxSopsDetailsModel::$table, [
			"status"               => Constants::COMMON_STATUS_DEL,
			"update_time"          => $time,
			"update_operator_uuid" => $employeeUuid,
		], ["sop_id" => $sopId]);
		if (empty($sopDetailsUpdateAffectRows)) {
			$db->rollBack();
			return false;
		}
		foreach ($sopDetailsData as &$sdv) {
			$sdv['sop_id'] = $sopId;
		}
		$pdo = $db->insert(WxSopsDetailsModel::$table, $sopDetailsData);
		if ($pdo->errorCode() != PDO::ERR_NONE) {
			$db->rollBack();
			return false;
		}
		$db->commit();
		return true;
	}

	/**
	 * 搜索sop数据
	 * @param array $searchParams
	 * @return array
	 */
	public static function searchSop(array $searchParams): array
	{
		$data = [
			"total_count" => 0,
			"list"        => [],
		];
		if (isset($searchParams["wx_original_id"]) && !empty($searchParams["wx_original_id"])) {
			$where["wx_original_id"] = trim($searchParams["wx_original_id"]);
		}
		if (isset($searchParams["name"]) && !empty($searchParams["name"])) {
			$where["name[~]"] = trim($searchParams["name"]);
		}
		$where["status"] = [Constants::COMMON_STATUS_WAITING, Constants::COMMON_STATUS_ON];
		$where["LIMIT"] = [($searchParams["page"] - 1) * $searchParams["limit"], $searchParams["limit"]];
		$where["ORDER"] = ["status_update_time" => "DESC"];
		$count = self::getCount($where);
		if (empty($count)) {
			return $data;
		}
		$data["total_count"] = $count;
		$data["list"] = self::getRecords($where, ["id", "status", "name", "wx_original_id", "exec_type", "exec_start_time", "exec_end_time"]);
		return $data;
	}

	/**
	 * ad系统获取指定公众号&指定触发动作类型的sop数据
	 * @param string $wxOriginalId
	 * @param string $triggerType
	 * @return array
	 */
	public static function adGetSops(string $wxOriginalId, string $triggerType, string $extra): array
	{

		$db = MysqlDB::getDB();
		return $db->select(self::$table, [
			'[>]' . WxSopsDetailsModel::$table => ['id' => 'sop_id'],
		],
			[
				self::$table . ".id(sop_id)",
				self::$table . ".exec_type",
				self::$table . ".exec_start_time",
				self::$table . ".exec_end_time",
				WxSopsDetailsModel::$table . ".id(sop_detail_id)",
				WxSopsDetailsModel::$table . ".message_type",
				WxSopsDetailsModel::$table . ".defer_time",
				WxSopsDetailsModel::$table . ".contents",
				WxSopsDetailsModel::$table . ".is_check_add_wx",
			],
			[
				self::$table . ".wx_original_id"             => $wxOriginalId,
				self::$table . ".status"                     => Constants::COMMON_STATUS_ON,
				WxSopsDetailsModel::$table . ".trigger_type" => $triggerType,
				WxSopsDetailsModel::$table . ".extra"        => $extra,
				WxSopsDetailsModel::$table . ".status"       => Constants::COMMON_STATUS_ON,
				"ORDER"                                      => [
					self::$table . ".status_update_time" => "DESC",
					self::$table . ".id"                 => "DESC",
					WxSopsDetailsModel::$table . ".defer_time"   => "ASC",
					WxSopsDetailsModel::$table . ".id"   => "ASC",
				],
			]);
	}
}
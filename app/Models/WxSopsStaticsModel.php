<?php

namespace App\Models;

use App\Libs\MysqlDB;
use Medoo\Medoo;

class WxSopsStaticsModel extends Model
{
	//表名称
	public static $table = "wx_sops_statics";

	/**
	 * 获取sop成功推送人数
	 * @param array $sopIds
	 * @param int $startCreateTime
	 * @param int $endCreateTime
	 * @return array
	 */
	public static function getSopStaticsUserCount(array $sopIds, int $startCreateTime, int $endCreateTime): array
	{
		$db = MysqlDB::getDB();
		$data = $db->select(self::$table,
			[
				"sop_id",
				"total_count" => Medoo::raw("count(id)")
			],
			[
				"sop_id"          => $sopIds,
				"create_time[>=]" => $startCreateTime,
				"create_time[<=]" => $endCreateTime,
				"error_code"      => "",
				"GROUP"           => "sop_id",
			]);
		return empty($data) ? [] : array_column($data, null, "sop_id");
	}
}
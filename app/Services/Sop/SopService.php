<?php

namespace App\Services\Sop;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\QiNiu;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\WxSopsDetailsModel;
use App\Models\WxSopsModel;
use App\Models\WxSopsStaticsModel;

class SopService
{
	/**
	 * 下拉框搜索条件列表
	 * @return array
	 */
	public static function selects(): array
	{
		$formatData = [];
		//sop通用配置
		$formatData[DictConstants::SOP_ADD_WX_CHECK_CONFIG['type']] = DictConstants::getTypeKeyCodes(DictConstants::SOP_ADD_WX_CHECK_CONFIG['type'], [1, 2]);
		//sop各种下拉框/状态等配置
		$dictData = DictConstants::getTypesMap([
			DictConstants::SOP_WX_ACCOUNT_CONFIG['type'],
			DictConstants::SOP_EXEC_TYPE_CONFIG['type'],
			DictConstants::SOP_TRIGGER_TYPE_CONFIG['type'],
			DictConstants::SOP_MESSAGE_TYPE_CONFIG['type'],
		]);
		foreach ($dictData as $key => $val) {
			$formatData[$key] = array_values($val);
		}

		return $formatData;
	}

	/**
	 * 创建
	 * @param array $params
	 * @param string $employeeUuid
	 * @return bool
	 * @throws RunTimeException
	 */
	public static function add(array $params, string $employeeUuid): bool
	{
		$time = time();
		//检测并格式化sop规则数据
		$formatSopParams = self::checkAndFormatSopParams($params);
		$formatSopParams["create_time"] = $time;
		$formatSopParams["create_operator_uuid"] = $employeeUuid;
		$formatSopParams["status"] = Constants::COMMON_STATUS_WAITING;

		$formatDetailParams = self::checkAndFormatSopDetailParams($params["details"], $employeeUuid, $time);
		$addRes = WxSopsModel::add($formatSopParams, $formatDetailParams);
		if (empty($addRes)) {
			throw new RunTimeException(['insert_failure']);
		}
		return true;
	}

	/**
	 * 修改
	 * @param array $params
	 * @param string $employeeUuid
	 * @return bool
	 * @throws RunTimeException
	 */
	public static function update(array $params, string $employeeUuid): bool
	{
		$time = time();
		//规则数据
		$sopData = WxSopsModel::getRecord(["id" => $params["sop_id"]]);
		if (empty($sopData) || !in_array($sopData["status"], [Constants::COMMON_STATUS_WAITING, Constants::COMMON_STATUS_ON])) {
			throw new RunTimeException(["sop_invalid"]);
		}
		//检测并格式化sop规则数据
		$formatSopParams = self::checkAndFormatSopParams($params);
		$formatSopParams["update_time"] = $time;
		$formatSopParams["update_operator_uuid"] = $employeeUuid;
		$formatSopParams["status"] = $sopData["status"];
		unset($formatSopParams["name"]);
		$formatDetailParams = self::checkAndFormatSopDetailParams($params["details"], $employeeUuid, $time);
		$addRes = WxSopsModel::update($params["sop_id"], $formatSopParams, $formatDetailParams, $time, $employeeUuid);
		if (empty($addRes)) {
			throw new RunTimeException(['update_failure']);
		}
		return true;
	}

	/**
	 * 获取sop规则详情数据
	 * @param int $sopId
	 * @return array
	 * @throws RunTimeException
	 */
	public static function detail(int $sopId): array
	{
		//规则数据
		$sopData = WxSopsModel::getRecord(["id" => $sopId], [
			"wx_original_id",
			"name",
			"exec_type",
			"exec_start_time",
			"exec_end_time",
			"status"
		]);
		if (empty($sopData) || !in_array($sopData["status"], [Constants::COMMON_STATUS_WAITING, Constants::COMMON_STATUS_ON])) {
			throw new RunTimeException(["sop_invalid"]);
		}
		//规则详情数据
		$sopDetailsData = WxSopsDetailsModel::getRecords([
			"sop_id" => $sopId,
			"status" => Constants::COMMON_STATUS_ON,
			"ORDER"  => ["id" => "ASC"]
		], [
			"id",
			"sop_id",
			"trigger_type",
			"extra",
			"defer_time",
			"message_type",
			"is_check_add_wx",
			"contents",
		]);
		foreach ($sopDetailsData as &$dv) {
			//内容
			$dv["contents"] = json_decode($dv["contents"], true);
			self::formatSopDetailsContents($dv["message_type"], $dv["contents"]);
			//推送时间
			$dv["hours"] = floor(($dv["defer_time"]) / 3600);
			$dv["minutes"] = floor(($dv["defer_time"] - $dv["hours"] * 3600) / 60);
			$dv["seconds"] = $dv["defer_time"] % 60;
		}
		return [
			"sop_data"         => $sopData,
			"sop_details_data" => $sopDetailsData,
		];
	}

	/**
	 * sop规则列表
	 * @param array $searchParams
	 * @return array
	 */
	public static function list(array $searchParams): array
	{
		$data = WxSopsModel::searchSop($searchParams);
		if (empty($data["list"])) {
			return $data;
		}
		self::formatSopListData($data['list']);
		return $data;
	}

	/**
	 * 格式化列表数据
	 * @param array $data
	 */
	private static function formatSopListData(array &$data)
	{
		$dictData = DictConstants::getTypesMap([
			DictConstants::SOP_WX_ACCOUNT_CONFIG['type'],
			DictConstants::SOP_EXEC_TYPE_CONFIG['type']
		]);
		$todayTimes = Util::getStartEndTimestamp(time());
		$staticsData = WxSopsStaticsModel::getSopStaticsUserCount(array_column($data, "id"), $todayTimes[0], $todayTimes[1]);
		foreach ($data as &$val) {
			$val["wx_original_id_zh"] = $dictData[DictConstants::SOP_WX_ACCOUNT_CONFIG["type"]][$val["wx_original_id"]]["value"];
			if ($val["exec_type"] == WxSopsModel::EXEC_TYPE_EVERY_DAY) {
				$val["exec_type_zh"] = $dictData[DictConstants::SOP_EXEC_TYPE_CONFIG["type"]][$val["exec_type"]]["value"];
			} else {
				$val["exec_type_zh"] = date("Y.m.d", $val["exec_start_time"]) . "-" . date("Y.m.d", $val["exec_end_time"]);
			}
			$val["today_success_user_count"] = isset($staticsData[$val["id"]]) ? (int)$staticsData[$val["id"]]["total_count"] : 0;
			unset($val["exec_start_time"]);
			unset($val["exec_end_time"]);
			unset($val["wx_original_id"]);
			unset($val["exec_type"]);
		}
	}


	/**
	 * 检测sop规则参数
	 * @param array $sopParams
	 * @return array
	 * @throws RunTimeException
	 */
	private static function checkAndFormatSopParams(array $sopParams): array
	{
		$wxAccountConfig = DictConstants::getSet(DictConstants::SOP_WX_ACCOUNT_CONFIG);
		if (!isset($wxAccountConfig[$sopParams["wx_original_id"]])) {
			throw new RunTimeException(['sop_wx_original_id_invalid']);
		}
		$formatSopParams["wx_original_id"] = $sopParams["wx_original_id"];
		$formatSopParams["exec_type"] = $sopParams["exec_type"];
		if ($formatSopParams["exec_type"] == WxSopsModel::EXEC_TYPE_EVERY_DAY) {
			$formatSopParams['exec_start_time'] = $formatSopParams['exec_end_time'] = 0;
		} elseif ($formatSopParams["exec_type"] == WxSopsModel::EXEC_TYPE_APPOINTED_DAY) {
			if (empty($sopParams['exec_start_time']) || empty($sopParams['exec_end_time'])) {
				throw new RunTimeException(['sop_exec_start_or_end_time_invalid']);
			}
			if (($sopParams['exec_start_time'] < time()) ||
				($sopParams['exec_start_time'] >= $sopParams['exec_end_time'])) {
				throw new RunTimeException(['sop_exec_start_time_invalid']);
			}
			$formatSopParams['exec_start_time'] = $sopParams['exec_start_time'];
			$formatSopParams['exec_end_time'] = $sopParams['exec_end_time'];
		}
		$formatSopParams["name"] = $sopParams['name'];
		return $formatSopParams;
	}

	/**
	 * 检测sop规则详情数据
	 * @param array $detailParams
	 * @param string $employeeUuid
	 * @param int $time
	 * @return array
	 * @throws RunTimeException
	 */
	private static function checkAndFormatSopDetailParams(array $detailParams, string $employeeUuid, int $time): array
	{
		if (empty($detailParams) ||
			!is_array($detailParams)) {
			throw new RunTimeException(['sop_detail_invalid']);
		}
		$validTriggerTypes = [
			WxSopsDetailsModel::TRIGGER_TYPE_USER_SUBSCRIBE,
			WxSopsDetailsModel::TRIGGER_TYPE_USER_INTERACTION_ALL,
		];
		$validCheckAddWx = [
			WxSopsDetailsModel::IS_CHECK_ADD_WX_YES,
			WxSopsDetailsModel::IS_CHECK_ADD_WX_NO,
		];
		$formatDetailParams = [];
		foreach ($detailParams as $dv) {
			//触发动作类型校验
			if (!isset($dv['trigger_type']) || !in_array($dv['trigger_type'], $validTriggerTypes)) {
				throw new RunTimeException(['sop_detail_trigger_type_invalid']);
			}
			//推送时间单位转换和校验
			$tmpHours = (int)($dv['hours'] ?? 0);
			$tmpMinutes = (int)($dv['minutes'] ?? 0);
			$tmpSeconds = (int)($dv['seconds'] ?? 0);
			$tmpDeferTime = $tmpHours * 3600 + $tmpMinutes * 60 + $tmpSeconds;
			if ($tmpDeferTime < 0 || $tmpDeferTime > WxSopsDetailsModel::TRIGGER_TYPE_VALIDITY_AND_QUANTITY[$dv["trigger_type"]]["validity"]) {
				throw new RunTimeException(['sop_detail_defer_time_invalid']);
			}
			if (!isset($dv['is_check_add_wx']) || !in_array($dv['is_check_add_wx'], $validCheckAddWx)) {
				throw new RunTimeException(['sop_detail_is_check_add_wx_invalid']);
			}
			//根据不同消息类型，检测不同的参数
			$messageTypeParams = self::formatMessageTypeParams($dv);
			if (empty($messageTypeParams)) {
				continue;
			}
			$formatDetailParams[] = [
				"trigger_type"         => $dv['trigger_type'],
				"defer_time"           => $tmpDeferTime,
				"message_type"         => $dv['message_type'],
				"is_check_add_wx"      => $dv['is_check_add_wx'],
				"status"               => Constants::COMMON_STATUS_ON,
				"create_time"          => $time,
				"create_operator_uuid" => $employeeUuid,
				"contents"             => json_encode($messageTypeParams, JSON_UNESCAPED_UNICODE),
			];
		}
		if (empty($formatDetailParams)) {
			throw new RunTimeException(['sop_detail_params_invalid']);
		}

		return $formatDetailParams;
	}

	/**
	 * 格式化处理不同消息类型需要的参数
	 * @param array $typeParams
	 * @return array
	 * @throws RunTimeException
	 */
	private static function formatMessageTypeParams(array $typeParams): array
	{
		switch ($typeParams['message_type']) {
			case WxSopsDetailsModel::MESSAGE_TYPE_TEXT:
				if (!isset($typeParams["text"]) || empty($typeParams["text"])) {
					return [];
				}
				return ["text" => $typeParams["text"]];
			case WxSopsDetailsModel::MESSAGE_TYPE_IMAGE:
				if (!isset($typeParams["image"]) || empty($typeParams["image"])) {
					return [];
				}
				return ["image" => $typeParams["image"]];
			case WxSopsDetailsModel::MESSAGE_TYPE_NEWS:
				if (!isset($typeParams["title"]) || empty($typeParams["title"])) {
					return [];
				}
				if (!isset($typeParams["desc"]) || empty($typeParams["desc"])) {
					return [];
				}
				if (!isset($typeParams["href"]) || empty($typeParams["href"])) {
					return [];
				}
				if (!isset($typeParams["thumb"]) || empty($typeParams["thumb"])) {
					return [];
				}
				return [
					"title" => $typeParams["title"],
					"desc"  => $typeParams["desc"],
					"href"  => $typeParams["href"],
					"thumb" => $typeParams["thumb"],
				];
			case WxSopsDetailsModel::MESSAGE_TYPE_MINI_CARD:
				if (!isset($typeParams["title"]) || empty($typeParams["title"])) {
					return [];
				}
				if (!isset($typeParams["app_id"]) || empty($typeParams["app_id"])) {
					return [];
				}
				if (!isset($typeParams["page_path"]) || empty($typeParams["page_path"])) {
					return [];
				}
				if (!isset($typeParams["thumb"]) || empty($typeParams["thumb"])) {
					return [];
				}
				return [
					"title"     => $typeParams["title"],
					"app_id"    => $typeParams["app_id"],//小程序的appid，要求小程序的 appid 需要与公众号有关联关系
					"page_path" => $typeParams["page_path"],//小程序的页面路径，跟 app.json 对齐，支持参数，比如pages/index/index?foo=bar
					"thumb"     => $typeParams["thumb"],
				];
			case WxSopsDetailsModel::MESSAGE_TYPE_POSTER_BASE:
				if (!isset($typeParams["poster_base"]) || empty($typeParams["poster_base"])) {
					return [];
				}
				//boss后台上传的助教二维码图片bucket:theone-shop-pre
				//sss后台上传的助教二维码图片存储在七牛云
				$posterBaseImgOss = AliOSS::replaceCdnDomainForDss($typeParams["poster_base"]);
				$extension = pathinfo($typeParams["poster_base"], PATHINFO_EXTENSION);
				$shopBucketImgPath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_ASSISTANT . '/' . md5($typeParams["poster_base"]) . '.' . $extension;
				return [
					"poster_base" => $typeParams["poster_base"],
					"ali"         => AliOSS::putObject($shopBucketImgPath, $posterBaseImgOss, Constants::REAL_APP_ID),
					"qi_niu"      => (new QiNiu())->upload($posterBaseImgOss, false, QiNiu::QI_NIU_DIR_ASSISTANT),
				];
			case WxSopsDetailsModel::MESSAGE_TYPE_VOICE:
				if (!isset($typeParams["title"]) || empty($typeParams["title"])) {
					return [];
				}
				if (!isset($typeParams["voice"]) || empty($typeParams["voice"])) {
					return [];
				}
				return [
					"title" => $typeParams["title"],
					"voice" => $typeParams["voice"],
				];
			default:
				throw new RunTimeException(['sop_detail_message_type_invalid']);
		}
	}

	/**
	 * 启用/禁用规则
	 * @param int $sopId
	 * @param int $status
	 * @param string $employeeUuid
	 * @return bool
	 * @throws RunTimeException
	 */
	public static function enableOrDisable(int $sopId, int $status, string $employeeUuid): bool
	{
		//规则数据
		$sopData = WxSopsModel::getRecord(["id" => $sopId]);
		if (empty($sopData) || !in_array($sopData["status"], [Constants::COMMON_STATUS_WAITING, Constants::COMMON_STATUS_ON])) {
			throw new RunTimeException(["sop_invalid"]);
		}
		$affectRows = WxSopsModel::updateRecord($sopId, ["status" => $status, "status_update_time" => time(), "status_operator_uuid" => $employeeUuid]);
		if (empty($affectRows)) {
			throw new RunTimeException(["update_failure"]);
		}
		return true;
	}

	/**
	 * 删除sop规则
	 * @param int $sopId
	 * @param string $employeeUuid
	 * @return bool
	 * @throws RunTimeException
	 */
	public static function delete(int $sopId, string $employeeUuid): bool
	{
		//规则数据
		$sopData = WxSopsModel::getRecord(["id" => $sopId]);
		if (empty($sopData) || !in_array($sopData["status"], [Constants::COMMON_STATUS_WAITING, Constants::COMMON_STATUS_ON])) {
			throw new RunTimeException(["sop_invalid"]);
		}
		$affectRows = WxSopsModel::updateRecord($sopId, ["status" => Constants::COMMON_STATUS_DEL, "status_update_time" => time(), "status_operator_uuid" => $employeeUuid]);
		if (empty($affectRows)) {
			throw new RunTimeException(["update_failure"]);
		}
		return true;
	}

	/**
	 * 第三方系统获取sop数据
	 * @param string $wxOriginalId
	 * @param string $triggerType
	 * @param string $extra
	 * @return array
	 */
	public static function thirdServiceGetSops(string $wxOriginalId, string $triggerType, string $extra): array
	{
		//区分不同触发动作，组织不同搜索条件
		if ($triggerType != WxSopsDetailsModel::TRIGGER_TYPE_USER_SUBSCRIBE) {
			$triggerType = WxSopsDetailsModel::TRIGGER_TYPE_USER_INTERACTION_ALL;
			$extra = "";//暂时没做到这么细致,占位使用：存储点击事件，消息互动等内容
		}
		$data = WxSopsModel::adGetSops($wxOriginalId, $triggerType, $extra);
		if (empty($data)) {
			return [];
		}
		$nowTime = time();
		//过滤数据
		foreach ($data as $k => &$v) {
			if ($v["exec_type"] == WxSopsModel::EXEC_TYPE_APPOINTED_DAY &&
				($v["exec_start_time"] > $nowTime || $v["exec_end_time"] < $nowTime)) {
				unset($data[$k]);
			}
			$v["contents"] = json_decode($v["contents"], true);
			self::formatSopDetailsContents($v["message_type"], $v["contents"]);
		}
		return $data;
	}

	/**
	 * 格式化sop规则详情内容数据
	 * @param string $messageType
	 * @param array $contents
	 */
	private static function formatSopDetailsContents(string $messageType, array &$contents)
	{
		switch ($messageType) {
			case WxSopsDetailsModel::MESSAGE_TYPE_IMAGE:
				$contents["image_oss"] = AliOSS::replaceCdnDomainForDss($contents["image"]);
				break;
			case WxSopsDetailsModel::MESSAGE_TYPE_NEWS:
			case WxSopsDetailsModel::MESSAGE_TYPE_MINI_CARD:
				$contents["thumb_oss"] = AliOSS::replaceCdnDomainForDss($contents["thumb"]);
				break;
			case WxSopsDetailsModel::MESSAGE_TYPE_VOICE:
				$contents["voice_oss"] = AliOSS::replaceCdnDomainForDss($contents["voice"]);
				break;
			case WxSopsDetailsModel::MESSAGE_TYPE_POSTER_BASE:
				$contents["poster_base_oss"] = AliOSS::replaceCdnDomainForDss($contents["poster_base"]);
				$contents["ali"] = AliOSS::replaceShopCdnDomain($contents["ali"]);
				$contents["qi_niu"] = (new QiNiu())->formatUrl($contents["qi_niu"]);
				break;
		}
	}

	/**
	 *  sop数据发送结果统计
	 * @param array $params
	 */
	public static function sopStaticsAdd(array $params)
	{
		$insertData = [
			"wx_original_id" => $params["wx_original_id"],
			"sop_id"         => $params["sop_id"],
			"sop_details_id" => $params["sop_details_id"],
			"open_id"        => $params["open_id"],
			"union_id"       => $params["union_id"],
			"error_code"     => $params["error_code"],
			"create_time"    => time(),
		];
		$res = WxSopsStaticsModel::insertRecord($insertData);
		SimpleLogger::info("sop statics add res", ["data" => $insertData, "res" => $res]);
	}
}
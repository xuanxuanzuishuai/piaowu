<?php

namespace App\Services\Queue\SystemStatics\AuditRecorder;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use Exception;

class AuditRecorderDownloadProducerService
{
	//说明文档地址：https://dowbo10hxj.feishu.cn/docx/doxcn2jHpkUPAL0hMTWa4JsvDwb
	//数据下载格式
	const DATA_TYPE_OSS    = 1;//oss保存路径
	const DATA_TYPE_STREAM = 2;//数据流

	/**
	 * 文件下载操作流痕事件上报
	 * @param string $employeeUuid
	 * @param array $statement
	 * @param string $downloadData
	 * @param string $requestLogUid
	 * @param int $dataType
	 * @param string $fileExtension
	 * @return bool
	 */
	public static function downloadRecorder(
		string $employeeUuid,
		array $statement,
		string $downloadData,
		string $requestLogUid,
		int $dataType,
		string $fileExtension = "csv"
	): bool {
		try {

			$msgBody = [
				"repo_name"      => Constants::SELF_SYSTEM_REPO_NAME,//应用的仓库地址
				"uuid"           => $employeeUuid,//下载者的uuid
				"download_time"  => time(),//以秒为单位的时间戳
				"uri"            => $_SERVER['REQUEST_URI'],//下载文件操作入口地址
				"statement"      => $statement,//下载文件对应的sql语句
				"file_extension" => $fileExtension,//下载文件扩展，也可能是excel等
				"request_uid"    => $requestLogUid, //用于链路追踪的唯一id
			];
			if ($dataType == self::DATA_TYPE_OSS) {
				$msgBody['original_oss_path'] = $downloadData;//文件的原始地址，必须是加过签名，有效期大于等于3分钟的oss地址
			} elseif ($dataType == self::DATA_TYPE_STREAM) {
				$msgBody['data'] = $downloadData;//string data 是将文件内容base64编码后的字符串
			} else {
				return false;
			}
			$nsqObj = new AuditRecorderTopic();
			$nsqObj->nsqDataSet($msgBody, $nsqObj::EVENT_TYPE_DOWNLOAD)->publish();
		} catch (Exception $e) {
			SimpleLogger::error($e->getMessage(), []);
			return false;
		}
		return true;
	}
}
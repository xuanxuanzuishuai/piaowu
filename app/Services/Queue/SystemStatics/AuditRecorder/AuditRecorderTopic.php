<?php

namespace App\Services\Queue\SystemStatics\AuditRecorder;

use App\Services\Queue\BaseTopic;
use App\Services\Queue\QueueService;
use Exception;

/**
 * 数据操作记录topic
 */
class AuditRecorderTopic extends BaseTopic
{
	const TOPIC_NAME          = "audit_recorder";
	const EVENT_TYPE_DOWNLOAD = 'download'; // 数据下载

	/**
	 * 构造函数
	 * @param null $publishTime
	 * @throws Exception
	 */
	public function __construct($publishTime = null)
	{
		parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, false);
	}
}
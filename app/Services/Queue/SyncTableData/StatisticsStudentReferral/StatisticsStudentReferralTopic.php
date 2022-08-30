<?php
/**
 * 检查学生是否可参与活动的消费者
 * 2022.08.29:当学生课程有变化时更新
 */

namespace App\Services\Queue\SyncTableData\StatisticsStudentReferral;

use App\Services\Queue\BaseTopic;
use App\Services\Queue\QueueService;
use Exception;

class StatisticsStudentReferralTopic extends BaseTopic
{
    const TOPIC_NAME = "op_statistics_student_referral";

    const EVENT_TYPE_SYNC_STUDENT_REFERRAL       = 'sync_statistics_real_student_referral_info';
    const EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_TMP = 'op_sync_erp_erp_student_course_tmp';

    /**
     * 构造函数
     * @param null $publishTime
     * @throws Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, true);
    }
}
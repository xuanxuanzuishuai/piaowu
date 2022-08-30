<?php
/**
 * 检查学生是否可参与活动的消费者
 * 2022.08.29:当学生课程有变化时更新
 */

namespace App\Services\Queue\SyncTableData\StatisticsStudentReferral;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\Pika;
use App\Libs\SimpleLogger;
use App\Services\StudentService;
use App\Services\SyncTableData\StatisticsStudentReferralService;

class StatisticsStudentReferralConsumerService
{
    /**
     * 同步真人学生转介绍信息
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function syncStatisticsRealStudentReferralInfo($params)
    {
        // 先记录请求日志
        SimpleLogger::info("StatisticsStudentReferralConsumerService::syncStatisticsRealStudentReferralInfo", [
            'msg' => 'start',
            'params' => $params,
            "event_type" => StatisticsStudentReferralTopic::EVENT_TYPE_SYNC_STUDENT_REFERRAL,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        // 只处理insert 和 update
        if (!$pikaObj->isInsert() && !$pikaObj->isUpdate()) {
            return false;
        }
        $rowsData = $pikaObj->getRows();
        // 开始更新学生转介绍关系统计数据
        $res = StatisticsStudentReferralService::updateStatisticsStudentReferral($pikaObj->getAppId(), $rowsData);
        SimpleLogger::info("StatisticsStudentReferralConsumerService::syncStatisticsRealStudentReferralInfo", [
            'msg' => 'end',
            'res' => $res,
        ]);
        return true;
    }
}
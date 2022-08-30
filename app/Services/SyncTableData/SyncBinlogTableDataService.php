<?php
/**
 * 同步表binlog修改
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData;

use App\Libs\Constants;
use App\Libs\Elasticsearch;
use App\Libs\Pika;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentCourseModel;
use App\Services\Queue\SyncTableData\StatisticsStudentReferral\StatisticsStudentReferralTopic;

class SyncBinlogTableDataService
{
    /**
     * 同步真人清退用户表（erp_student_course_tmp）到es
     * @param $params
     * @return bool
     */
    public function opSyncErpErpStudentCourseTmp($params)
    {
        $eventType = StatisticsStudentReferralTopic::EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_TMP;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opErpErpStudentCourseTmp", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        // 只处理insert 和 update
        if (!$pikaObj->isInsert() && !$pikaObj->isUpdate()) {
            return false;
        }
        $rowsData = $pikaObj->getRows();

        $es = Elasticsearch::getInstance($eventType);
        if ($pikaObj->isDelete()) {
            // 删除es中的数据
            list($esData) = $es->getOneDoc(['id' => $rowsData['id'] ?? 0]);
            $es->delete($esData['_id']);
            return true;
        }

        // 判断es是否存在,
        list($studentCleanInfo, $esId) = $es->getOneDoc(['student_id' => $rowsData['student_id']]);
        // 存在
        if (!empty($studentCleanInfo)) {
            // 存在并且属于清退后再购买 - 属于不做任何处理
            if ($studentCleanInfo['buy_after_clean'] == Constants::STATUS_TRUE) {
                return true;
            }
            // 检查是否在清退后再次购买 （这里需要用到用户首次清退时间进行计算）
            $cleanTime = min($studentCleanInfo['create_time'], $rowsData['create_time']);
        } else {
            // 不存在
            $cleanTime = $rowsData['create_time'];
        }
        $courseInfo = ErpStudentCourseModel::getStudentCleanAfterHasBuyCourse($rowsData['student_id'], $cleanTime);
        // 保存到es 存在update,不存在add
        $update = [
            'create_time'     => $cleanTime,
            'buy_after_clean' => !empty($courseInfo) ? Constants::STATUS_TRUE : Constants::STATUS_FALSE,
        ];
        if (!empty($studentCleanInfo)) {
            $es->update($esId, $update);
        } else {
            $update['id'] = $rowsData['id'];
            $update['student_id'] = $rowsData['student_id'];
            $es->index($update);
        }
        return true;
    }
}
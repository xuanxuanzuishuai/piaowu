<?php
/**
 * 同步表binlog修改
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Pika;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentCourseModel;
use App\Models\Erp\ErpStudentModel;

class SyncBinlogTableDataService
{
    const EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_TMP    = 'op_sync_erp_erp_student_course_tmp';
    const EVENT_TYPE_SYNC_ERP_REFERRAL_USER_REFEREE = 'op_sync_erp_erp_referral_user_referee';
    const EVENT_TYPE_SYNC_ERP_STUDENT_ATTRIBUTE     = 'op_sync_erp_erp_student_attribute';
    const EVENT_TYPE_SYNC_ERP_STUDENT_COURSE        = 'op_sync_erp_erp_student_course';
    const EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_EXT    = 'op_sync_erp_erp_student_course_ext';
    const EVENT_TYPE_SYNC_ERP_GENERIC_WHITELIST     = 'op_sync_erp_erp_generic_whitelist';

    /**
     * 同步真人清退用户表（erp_student_course_tmp）到cache
     * @param $params
     * @return bool
     */
    public function opSyncErpErpStudentCourseTmp($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_TMP;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opErpErpStudentCourseTmp", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();
        $redis = RedisDB::getConn();

        if ($pikaObj->isDelete()) {
            // 删除操
            $redis->hdel($eventType, $rowsData['student_id']);
            return true;
        }

        // 判断是否存在,
        $studentCleanInfoStr = $redis->hget($eventType, $rowsData['student_id']);
        $studentCleanInfo = !empty($studentCleanInfoStr) ? json_decode($studentCleanInfoStr, true) : [];
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
        $studentCleanInfo['create_time'] = (int)$cleanTime;
        $studentCleanInfo['buy_after_clean'] = !empty($courseInfo) ? Constants::STATUS_TRUE : Constants::STATUS_FALSE;
        $studentCleanInfo['id'] = $rowsData['id'];
        $studentCleanInfo['student_id'] = $rowsData['student_id'];
        $redis->hset($eventType, $studentCleanInfo['student_id'], json_encode($studentCleanInfo));

        // 清退又购买的用户重新计算用户命中的真人周周活动
        $studentCleanInfo['buy_after_clean'] = 1;
        if (!empty($studentCleanInfo['buy_after_clean'])) {
            (new RealUpdateStudentCanJoinActivityService(['student_id' => $rowsData['student_id']]))->run();
        }
        return true;
    }

    /**
     * 监听真人转介绍关系表（referral_user_referee）
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public function opSyncErpErpReferralUserReferee($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_REFERRAL_USER_REFEREE;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpReferralUserReferee", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();

        if ($pikaObj->isDelete()) {
            // 删除推荐人转介绍关系
            // TODO 现在真人转介绍关系不能被删除，也不能被更改
            return true;
        }

        // 开始更新学生转介绍关系统计数据
        $res = StatisticsStudentReferralService::updateStatisticsStudentReferral($pikaObj->getAppId(), $rowsData);
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpReferralUserReferee", [
            'msg' => 'end',
            'res' => $res,
        ]);
        return true;
    }

    /**
     * 监听学生生命周期表（erp_student_attribute）
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public function opSyncErpErpStudentAttribute($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_STUDENT_ATTRIBUTE;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpStudentAttribute", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();

        if ($pikaObj->isDelete()) {
            return true;
        }

        // 开始更新学生转介绍关系统计数据
        $urInfo = ErpReferralUserRefereeModel::getStudentReferral($rowsData['user_id']);
        if ($urInfo) {
            StatisticsStudentReferralService::updateStatisticsStudentReferral($pikaObj->getAppId(), ['ur_id' => $urInfo['id']]);
        }

        return true;
    }

    /**
     * 监听学生课程数量变化（erp_student_course）
     * @param $params
     * @return bool
     */
    public function opSyncErpErpStudentCourse($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_STUDENT_COURSE;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpStudentCourse", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();

        // 正式课 + 价格>0 + 非免费课包
        if ($rowsData['business_type'] == ErpStudentCourseModel::BUSINESS_TYPE_NORMAL) {
            $businessTag = json_encode($rowsData['business_tag'], true);
            if (!empty($businessTag['price']) && !isset($businessTag['free_type'])) {
                // 开始更新学生转介绍关系统计数据
                (new RealUpdateStudentCanJoinActivityService(['student_id' => $rowsData['student_id']]))->run();
            }
        }

        return true;
    }

    /**
     * 监听学生课程数量变化（erp_student_course_ext）
     * @param $params
     * @return bool
     */
    public function opSyncErpErpStudentCourseExt($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_STUDENT_COURSE_EXT;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpStudentCourseExt", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();

        // 正式课 + 价格>0 + 非免费课包
        $courseInfo = ErpStudentCourseModel::getRecord(['id' => $rowsData['student_course_id']]);
        if ($courseInfo['business_type'] == ErpStudentCourseModel::BUSINESS_TYPE_NORMAL) {
            $businessTag = json_encode($courseInfo['business_tag'], true);
            if (!empty($businessTag['price']) && !isset($businessTag['free_type'])) {
                // 开始更新学生转介绍关系统计数据
                (new RealUpdateStudentCanJoinActivityService(['student_id' => $courseInfo['student_id']]))->run();
            }
        }

        return true;
    }

    /**
     * 监听白名单变化（erp_generic_whitelist）
     * @param $params
     * @return bool
     */
    public function opSyncErpErpGenericWhitelist($params)
    {
        $eventType = self::EVENT_TYPE_SYNC_ERP_GENERIC_WHITELIST;
        // 先记录请求日志
        SimpleLogger::info("SyncBinlogTableDataService::opSyncErpErpGenericWhitelist", [
            'msg'        => 'start',
            'params'     => $params,
            "event_type" => $eventType,
        ]);
        // 解析数据
        $pikaObj = Pika::initObj($params);
        $rowsData = $pikaObj->getRows();

        $studentInfo = ErpStudentModel::getRecord(['uuid' => $rowsData['scene_key']], ['id']);
        // 计算学生命中活动
        (new RealUpdateStudentCanJoinActivityService(['student_id' => $studentInfo['id']]))->run();

        return true;
    }
}
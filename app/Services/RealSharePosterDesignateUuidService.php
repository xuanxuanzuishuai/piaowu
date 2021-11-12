<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/12
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterDesignateUuidModel;
use App\Models\RealWeekActivityModel;

class RealSharePosterDesignateUuidService
{
    /**
     * 批量保存活动指定用户UUID
     * @param $activityId
     * @param $employeeId
     * @param array $designateUuid
     * @param bool $isCheckUuidExists
     * @return array
     * @throws RunTimeException
     */
    public static function batchSaveDesignateUuid($activityId, $employeeId, array $designateUuid, bool $isCheckUuidExists = true): array
    {
        $returnData = [
            'error_code' => 0,
            'no_exist_uuid' => [],
        ];
        if (empty($designateUuid)) {
            throw new RunTimeException(['designate_uuid_is_required']);
        }
        // 检查活动是否存在
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['activity_not_found']);
        }
        // 查询uuid是否存在（是否是正确的用户）
        if ($isCheckUuidExists) {
            $noExistUuid = UserService::checkStudentUuidExists(Constants::REAL_APP_ID, $designateUuid);
            if (!empty($noExistUuid)) {
                $returnData['no_exist_uuid'] = $noExistUuid;
                $returnData['error_code'] = 1;
                return $returnData;
            }
        }
        // 读取当前活动下以及存在的uuid
        $isExistUuid = RealSharePosterDesignateUuidModel::getRecords(['activity_id' => $activityId], ['uuid']);
        // 取出还没入库的uuid
        $diffUuid = array_diff($designateUuid, array_column($isExistUuid, 'uuid'), $returnData['no_exist_uuid']);
        // 未入库的uuid入库
        if (empty($diffUuid)) {
            return $returnData;
        }
        // 保存分享任务
        $saveRes = RealSharePosterDesignateUuidModel::batchInsertUuid($activityId, $diffUuid, $employeeId, time());
        if (empty($saveRes)) {
            SimpleLogger::info("batchSaveDesignateUuid", [$activityId,$employeeId,$designateUuid, $isCheckUuidExists, $diffUuid, $saveRes]);
            throw new RunTimeException(["add_designate_uuid_fail"]);
        }
        return $returnData;
    }

    /**
     * 删除未启用周周领奖活动指定UUID
     * @param $activityId
     * @param $employeeId
     * @param $designateUUID
     * @return bool
     * @throws RunTimeException
     */
    public static function delActivityDesignateUUID($activityId, $employeeId, $designateUUID): bool
    {
        if (empty($designateUUID)) {
            throw new RunTimeException(['designate_uuid_is_required']);
        }
        // 检查活动是否存在
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['activity_not_found']);
        }
        // 只有未启用的活动可以删除uuid
        if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_OFF) {
            SimpleLogger::info('delActivityDesignateUUID', ['activity_is_status_on_not_edit_designate_uuid', $activityInfo, $employeeId, $designateUUID]);
            throw new RunTimeException(['activity_is_status_on_not_edit_designate_uuid']);
        }
        // 删除uuid
        $res = RealSharePosterDesignateUuidModel::delDesignateUUID($activityId, $designateUUID, $employeeId);
        if (!$res) {
            throw new RunTimeException(['activity_del_designate_uuid_fail']);
        }
        return true;
    }

    /**
     * 获取指定活动的UUID列表
     * @param $activityId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getActivityDesignateUUIDList($activityId, $page, $limit): array
    {
        $returnData = ['total_count' => 0, 'list' => []];
        [$returnData['list'], $returnData['total_count']] = RealSharePosterDesignateUuidModel::searchList(['activity_id' => $activityId], $page, $limit);
        if (!empty($returnData['list']) && is_array($returnData['list'])) {
            foreach ($returnData['list'] as &$info) {
                $info['format_create_time'] = date("Y-m-d H:i:s", $info['create_time']);
            }
        }
        return $returnData;
    }
}

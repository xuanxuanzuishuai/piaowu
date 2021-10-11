<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/12
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Models\AdminOperationLogModel;

class AdminOpLogService
{
    /**
     * 操作日志记录
     * @param $operatorId
     * @param $newData
     * @param $oldData
     */
    public static function opLogAdd($operatorId, $newData, $oldData)
    {
        $time = time();
        //比较新旧数据不同
        $changeData = self::formatLogData($oldData, $newData);
        $logData = [];
        $batchId = md5($operatorId . microtime(true));
        array_walk($changeData, function ($cv, $tableName) use (&$logData, $operatorId, $time, $batchId) {
            foreach ($cv as $fieldName => $tmpValue) {
                if ($tmpValue['new_val'] != $tmpValue['old_val']) {
                    $logData[] = [
                        'old_value' => $tmpValue['old_val'],
                        'new_value' => $tmpValue['new_val'],
                        'data_id' => $tmpValue['data_id'],
                        'field_name' => $fieldName,
                        'table_name' => $tableName,
                        'operator_id' => $operatorId,
                        'create_time' => $time,
                        'batch_id' => $batchId,
                    ];
                }
            }
        });
        if (!empty($changeData)) {
            $addRes = AdminOperationLogModel::batchInsert($logData);
            if (empty($addRes)) {
                SimpleLogger::error("admin op log add fail", ['log_data' => $changeData]);
            }
        }
    }

    /**
     * 格式化处理新旧数据
     * @param $oldData
     * @param $newData
     * @return array
     */
    private static function formatLogData($oldData, $newData)
    {
        $formatLogData = [];
        foreach ($newData as $tmpTableName => $newVal) {
            foreach ($newVal as $fieldName => $fieldNewVal) {
                $fieldOldVal = isset($oldData[$tmpTableName][$fieldName]) ? $oldData[$tmpTableName][$fieldName] : '';
                if ($fieldOldVal != $fieldNewVal) {
                    $formatLogData[$tmpTableName][$fieldName] = [
                        'new_val' => $fieldNewVal,
                        'old_val' => $fieldOldVal,
                        'data_id' => $oldData[$tmpTableName]['data_id'],
                    ];
                }
            }
        }
        return $formatLogData;
    }
}
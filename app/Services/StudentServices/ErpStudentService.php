<?php

namespace App\Services\StudentServices;

use App\Models\Erp\ErpStudentModel;

class ErpStudentService
{
    /**
     * 获取学生基础信息
     * @param $uuids
     * @return array
     */
    public static function getStudentByUuid($uuids): array
    {
        return array_column(ErpStudentModel::getRecords(['uuid' => $uuids], ['uuid', 'mobile']), null, 'uuid');
    }
}
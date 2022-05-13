<?php

namespace App\Services\StudentServices;


use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssStudentModel;
use App\Services\Employee\DssEmployeeService;

class DssStudentService
{
    /**
     * 获取学生助教信息
     * @param       $studentId
     * @param array $fields
     * @return array
     * @throws RunTimeException
     */
    public static function getStudentAssistantInfo($studentId, array $fields = []): array
    {
        $returnData = [
            'is_add_assistant_wx' => 0,
            'assistant_info'      => [],
        ];
        $studentInfo = DssStudentModel::getRecord(['id' => $studentId], ['assistant_id', 'is_add_assistant_wx']);
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $returnData['is_add_assistant_wx'] = (int)$studentInfo['is_add_assistant_wx'];
        // 没有助教直接返回空
        if (empty($studentInfo['assistant_id'])) {
            return $returnData;
        }
        // 获取助教信息
        $assistantInfo = DssEmployeeService::getEmployeeInfoById($studentInfo['assistant_id'], $fields);
        if (!empty($assistantInfo)) {
            // 如果助教信息存在：获取助教配置（app中需要的跳转信息）
            $appAssistantInfo = (new Dss())->getWxAppAssistant(['assistant_id' => $studentInfo['assistant_id']]);
            $returnData['assistant_info'] = array_merge($assistantInfo, $appAssistantInfo ?? []);
        }
        return $returnData;
    }
}
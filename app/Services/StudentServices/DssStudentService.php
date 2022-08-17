<?php

namespace App\Services\StudentServices;


use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Risk;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentLeadsModel;
use App\Models\Dss\DssStudentModel;
use App\Services\DssDictService;
use App\Services\Employee\DssEmployeeService;

class DssStudentService
{
    // 是否是系统认定的重复用户（是否是薅羊毛用户）
    const STUDENT_COLLECT_WOOL_YES = 1; // 是
    const STUDENT_COLLECT_WOOL_NO  = 0;  // 不是

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
        $returnData['is_add_assistant_wx'] = self::checkStudentIsAddAssistant($studentId);
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

    /**
     * 检查学生是否添加了助教id
     * @param $studentId
     * @return int
     */
    public static function checkStudentIsAddAssistant($studentId)
    {
        $id = DssStudentLeadsModel::getRecord(['student_id' => $studentId], ['is_add_assistant_wx'])['is_add_assistant_wx'] ?? 0;
        return (int)$id;
    }

    /**
     * 获取学生是否能够购买指定课包，系统判定的重复用户购买指定课包时会返回其他课包
     * 检查条件： 未购买过体验课，不是重复用户
     * @param string $uuid 学生uuid
     * @param numeric $pkg PayServices::getPackageIDByParameterPkg方法参数
     * @param array $extendParams 扩展参数
     * @return array
     * @throws RunTimeException
     */
    public static function getStudentRepeatBuyPkg($uuid, $pkg, $extendParams = [])
    {
        $isRepeat = self::STUDENT_COLLECT_WOOL_NO;
        $newPkg = 0;
        // 检查用户是否是薅羊毛用户， 如果是走提价策略
        $studentIsRepeatInfo = (new Risk())->getStudentIsRepeat([
            'uuid'    => $uuid,
            'open_id' => $extendParams['open_id'] ?? '',
        ]);
        if (isset($studentIsRepeatInfo['tag']) && $studentIsRepeatInfo['tag'] == self::STUDENT_COLLECT_WOOL_YES) {
            SimpleLogger::info("getStudentRepeatBuyPkg", ['msg' => 'student_is_repeat', 'info' => $studentIsRepeatInfo]);
            $isRepeat = self::STUDENT_COLLECT_WOOL_YES;
            $newPkg = DssDictService::getKeyValue(DictConstants::DSS_WEB_STUDENT_CONFIG, 'pkg_9_student_is_repeat_new_pkg');
        }
        // 查询是否已经有体验课订单
        $studentInfo = DssStudentModel::getRecord(['uuid' => $uuid], ['id']);
        $studentId = $studentInfo['id'] ?? 0;
        $hadPurchasePackageByType = DssGiftCodeModel::hadPurchasePackageByType($studentId, DssPackageExtModel::PACKAGE_TYPE_TRIAL, false, ['limit' => 1]);
        if (!empty($hadPurchasePackageByType)) {
            throw new RunTimeException(['has_trialed']);
        }

        return [
            'is_repeat' => $isRepeat,
            'old_pkg'   => (int)$pkg,
            'new_pkg'   => (int)$newPkg,
            'has_trail' => 0,
            'is_check'  => 1,   // 1开启， 2未开启，  现在走羊毛系统所以都是开启状态
        ];
    }
}
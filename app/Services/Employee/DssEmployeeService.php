<?php
/**
 * 员工信息处理
 * User: qingfeng.lian
 * Date: 2018/6/26
 * Time: 上午11:34
 */

namespace App\Services\Employee;


use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssEmployeeModel;

class DssEmployeeService
{
    /**
     * @param $employeeId
     * @param array $fields
     * @return array
     * @throws RunTimeException
     * 获取用户详情
     */
    public static function getEmployeeInfoById($employeeId, array $fields = [])
    {
        $employeeInfo = DssEmployeeModel::getRecord(['id' => $employeeId], $fields);
        SimpleLogger::info('dss-getEmployeeInfoById', [$employeeId, $employeeInfo]);
        if (empty($employeeInfo)) {
            throw new RunTimeException(['employee_not_exist']);
        }
        return self::formatInfo($employeeInfo);
    }

    /**
     * 格式化信息
     * @param $employeeInfo
     * @return mixed
     */
    public static function formatInfo($employeeInfo)
    {
        $employeeInfo['format_thumb'] = !empty($employeeInfo['wx_thumb']) ? AliOSS::replaceCdnDomainForDss($employeeInfo['wx_thumb']) : '';
        $employeeInfo['format_qr'] = !empty($employeeInfo['wx_qr']) ? AliOSS::replaceCdnDomainForDss($employeeInfo['wx_qr']) : '';
        return $employeeInfo;
    }
}
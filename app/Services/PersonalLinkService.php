<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/21
 * Time: 1:55 PM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\RC4;
use App\Models\EmployeeModel;
use App\Models\ErpPackageModel;

class PersonalLinkService
{
    /**
     * 专属售卖链接相关产品包
     * @return array
     */
    public static function getPackages()
    {
        $packageIdStr = DictConstants::get(DictConstants::PERSONAL_LINK_PACKAGE_ID, ['package_id']);
        return ErpPackageModel::getPackAgeList(['id' => explode(',', reset($packageIdStr))]);
    }

    /**
     * @param $employeeId
     * @param $packageId
     * 生成专属售卖链接
     */
    public static function createPersonalLink($employeeId, $packageId)
    {
        $employeeInfo = EmployeeModel::getById($employeeId);
        $encryptStr = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $employeeInfo['uuid']);
        return $_ENV['WECHAT_FRONT_DOMAIN'] . '/buy/detail?packageId=' . $packageId . '&employeeId=' . $encryptStr;
    }
}

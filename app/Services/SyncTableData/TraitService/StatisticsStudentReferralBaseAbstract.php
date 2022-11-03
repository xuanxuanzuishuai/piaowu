<?php

namespace App\Services\SyncTableData\TraitService;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;

/**
 * 活动客户端管理基础服务接口类：定义一些方法，由子类去实现
 */
abstract class StatisticsStudentReferralBaseAbstract implements StatisticsStudentReferralBaseInterface
{
// 实例化对象列表
    private static $objList = [];

    /**
     * 获取示例话对象
     * @param $appId
     * @param array $initData
     * @param bool $isNewObj
     * @return RealStatisticsStudentReferralService|mixed
     * @throws RunTimeException
     */
    public static function getAppObj($appId, $initData = [], $isNewObj = false)
    {
        if ($isNewObj == false && !empty(self::$objList[$appId])) {
            return self::$objList[$appId];
        }
        switch ($appId) {
            case Constants::REAL_APP_ID;
                $obj = new RealStatisticsStudentReferralService();
                break;
            default:
                throw new RunTimeException(['app_id_invalid']);
        }
        self::$objList[$appId] = $obj;
        return self::$objList[$appId];
    }

}
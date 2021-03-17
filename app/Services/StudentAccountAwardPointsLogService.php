<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Erp\ErpDictModel;
use App\Models\StudentAccountAwardPointsLogModel;


class StudentAccountAwardPointsLogService
{
    /**
     * 获取批量发放积分奖励日志列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getList($params, $page, $count)
    {
        $awardPointsLogList = StudentAccountAwardPointsLogModel::getList($params, $page, $count);
        $awardPointsLogList['list'] = self::formatAwardPointsLogInfo($awardPointsLogList['list']);
        return $awardPointsLogList;
    }

    /**
     * 格式化批量发放积分奖励日志信息
     * @param $awardPointsLogList
     * @return mixed
     */
    public static function formatAwardPointsLogInfo($awardPointsLogList)
    {
        $accountNameList = self::getAccountName();
        foreach ($awardPointsLogList as $key => $val) {
            $awardPointsLogList[$key]['format_create_time'] = date("Y-m-d H:i", $val['create_time']);
            $awardPointsLogList[$key]['account_name'] = $accountNameList[$val['app_id'] . '_' . $val['sub_type']] ?? '';
            $awardPointsLogList[$key]['num'] = Util::yuan($val['num'], 0);
        }
        return $awardPointsLogList;
    }

    /**
     * 奖励积分的excel数据转换成数组
     * @param $excelData
     * @param $isDelFirst
     * @return array
     */
    public static function excelDataToLogData($excelData)
    {
        $returnData = [];
        foreach ($excelData as $_v) {
            $returnData[] = [
                'uuid' => $_v['A'],
                'mobile' => $_v['B'],
                'num' => $_v['C'],
            ];
        }
        return $returnData;
    }

    /**
     * 获取积分账户列表
     * @return string[]
     */
    public static function getAccountName()
    {
        // 获取积分账户， 从表获取数据需要排除现金
        $accountNameList = DictConstants::getErpDictArr(Constants::ERP_DICT_ACCOUNT_NAME_TYPE, [], [Constants::ERP_ACCOUNT_NAME_CASH]);
        return array_column($accountNameList[Constants::ERP_DICT_ACCOUNT_NAME_TYPE],'value','code');
    }
}

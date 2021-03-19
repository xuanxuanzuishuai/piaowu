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
        $where = [];
        if (!empty($params['uuid'])) {
            $where['uuid'] = $params['uuid'];
        }
        if (!empty($params['mobile'])) {
            $where['mobile'] = $params['mobile'];
        }
        if (!empty($params['account_name'])) {
            $accountType = explode(StudentAccountAwardPointsLogModel::APPID_SUBTYPE_EXPLODE, $params['account_name']);
            $where['app_id'] = $accountType[0] ?? 0;
            $where['sub_type'] = $accountType['1'] ?? 0;
        }
        if (!empty($params['start_time'])) {
            $where['create_time[>=]'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where['create_time[<=]'] = strtotime($params['end_time']);
        }
        $awardPointsLogList = StudentAccountAwardPointsLogModel::getList($where, $page, $count);
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
        return array_column($accountNameList,'code','value');
    }
}

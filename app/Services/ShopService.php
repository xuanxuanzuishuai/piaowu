<?php

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\ReceiptApplyModel;
use App\Models\RoleModel;
use App\Models\ShopBelongManageModel;
use App\Models\ShopInfoModel;

class ShopService
{
    /**
     * 获取门店列表
     * @param $params
     * @param $employeeId
     * @param $page
     * @param $count
     * @return array
     * @throws RunTimeException
     */
    public static function getShopList($params, $employeeId, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RunTimeException(['not_found_employee']);
        }

        list($list, $totalCount) = ShopInfoModel::getShopInfo($params, $page, $count);

        return [$list, $totalCount];
    }

    /**
     * 新增编辑门店
     * @param $params
     * @param $employeeId
     * @throws RunTimeException
     */
    public static function addShop($params, $employeeId)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RunTimeException(['not_found_employee']);
        }

        $where = [
            'shop_number' => $params['shop_number']
        ];

        if (!empty($params['shop_id'])) {
            $where['id[!]'] = $params['shop_id'];
        }

        $res = ShopInfoModel::getRecord($where);

        if (!empty($res)) {
            throw new RunTimeException(['shop_number_has_exist']);
        }

        $data = [
            'province_id' => $params['province_id'],
            'city_id' => $params['city_id'],
            'shop_number' => $params['shop_number'],
            'shop_name' => Util::filterEmoji($params['shop_name']),
            'detail_address' => $params['detail_address']
        ];

        if (empty($params['shop_id'])) {
            $data['employee_id'] = $employeeId;
            $data['create_time'] = time();
            ShopInfoModel::insertRecord($data);
        } else {
            $data['update_time'] = time();
            ShopInfoModel::updateRecord($params['shop_id'], $data);
        }

        $shopInfo = ShopInfoModel::getRecord(['shop_number' => $params['shop_number']]);

        if (!empty($params['belong_manage_id'])) {

            $info = ShopBelongManageModel::getRecords(['shop_id' => $shopInfo['id']]);

            if (empty($info)) {
                ShopBelongManageModel::insertRecord(
                    [
                        'employee_id' => $params['belong_manage_id'],
                        'shop_id' => $shopInfo['id'],
                        'status' => 1,
                        'create_time' => time()
                    ]
                );
            } else {
                ShopBelongManageModel::batchUpdateRecord(
                    [
                        'status' => 2,
                        'update_time' => time()
                    ],
                    [
                        'shop_id' => $shopInfo['id']
                    ]
                );
            }
        }
    }

}

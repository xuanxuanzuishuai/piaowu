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
     * @param $page
     * @param $count
     * @return array
     * @throws RunTimeException
     */
    public static function getShopList($params, $page, $count)
    {

        list($list, $totalCount) = ShopInfoModel::getShopInfo($params, $page, $count);

        return [$list, $totalCount];
    }

    /**
     * 获取门店详情
     */
    public static function getShopDetail($shopId)
    {

         $shopDetail =  ShopInfoModel::getShopDetail($shopId);

        return $shopDetail;
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

        if (!empty($params['shop_id'])) {
            $shopInfo = ShopInfoModel::getRecord(['id' => $params['shop_id']]);
            if (empty($shopInfo)) {
                throw new RunTimeException(['shop_not_exist']);
            }
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

        $manageEmployeeInfo = EmployeeModel::getRecord(['id' => $params['shop_belong_manage_id']]);
        if (empty($manageEmployeeInfo)) {
            throw new RunTimeException(['not_found_manage_employee']);
        }



        $data = [
            'province_id' => $params['province_id'],
            'city_id' => $params['city_id'],
            'district_id' => $params['district_id'],
            'shop_number' => $params['shop_number'],
            'shop_name' => Util::filterEmoji($params['shop_name']),
            'detail_address' => $params['detail_address'],
            'update_time' => time()
        ];

        if (empty($params['shop_id'])) {
            $data['employee_id'] = $employeeId;
            $data['create_time'] = time();
            ShopInfoModel::insertRecord($data);
        } else {
            ShopInfoModel::updateRecord($params['shop_id'], $data);
        }

        $shopInfo = ShopInfoModel::getRecord(['shop_number' => $params['shop_number']]);

        if (!empty($params['shop_belong_manage_id'])) {

            $info = ShopBelongManageModel::getRecords(['shop_id' => $shopInfo['id'], 'employee_id' => $params['shop_belong_manage_id']]);

            if (empty($info)) {
                ShopBelongManageModel::insertRecord(
                    [
                        'employee_id' => $params['shop_belong_manage_id'],
                        'shop_id' => $shopInfo['id'],
                        'status' => 1,
                        'create_time' => time()
                    ]
                );
            }
            ShopBelongManageModel::batchUpdateRecord(
                    [
                        'status' => 2,
                        'update_time' => time()
                    ],
                    [
                        'shop_id' => $shopInfo['id'],
                        'employee_id[!]' => $params['shop_belong_manage_id']
                    ]
                );
        }
    }

}

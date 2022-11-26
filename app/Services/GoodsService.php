<?php

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\EmployeeModel;
use App\Models\GoodsModel;

class GoodsService
{
    /**
     * 获取商品列表
     * @param $params
     * @param $employeeId
     * @param $page
     * @param $count
     * @return array
     * @throws RunTimeException
     */
    public static function getGoodsList($params, $employeeId, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RunTimeException(['not_found_employee']);
        }

        list($list, $totalCount) = GoodsModel::getGoodsInfo($params, $page, $count);

        return [$list, $totalCount];
    }

    /**
     * 新增编辑商品
     * @param $params
     * @param $employeeId
     * @throws RunTimeException
     */
    public static function addGoods($params, $employeeId)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RunTimeException(['not_found_employee']);
        }

        $where = [
            'goods_number' => $params['goods_number']
        ];


        if (!empty($params['goods_id'])) {
            $where['id[!]'] = $params['goods_id'];
        }


        $res = GoodsModel::getRecord($where);

        if (!empty($res)) {
            throw new RunTimeException(['goods_number_has_exist']);
        }

        if (!empty($params['goods_id'])) {
            GoodsModel::updateRecord($params['goods_id'],
                [
                    'goods_name' => $params['goods_name'],
                    'goods_number' => $params['goods_number'],
                    'update_time' => time(),
                    'employee_id' => $employeeId
                ]);
        } else {
            GoodsModel::insertRecord(
                [
                    'goods_name' => $params['goods_name'],
                    'goods_number' => $params['goods_number'],
                    'create_time' => time(),
                    'update_time' => time(),
                    'employee_id' => $employeeId
                ]
            );
        }

    }

}

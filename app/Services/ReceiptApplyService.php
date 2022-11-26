<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\BAApplyModel;
use App\Models\BAListModel;
use App\Models\EmployeeModel;
use App\Models\GoodsModel;
use App\Models\ReceiptApplyGoodsModel;
use App\Models\ReceiptApplyModel;
use App\Models\RoleModel;
use App\Models\ShopInfoModel;

class ReceiptApplyService
{
    /**
     * 后台录入销售单
     * @param $params
     * @param $employeeId
     * @throws RunTimeException
     */
    public static function backendUploadApply($params, $employeeId)
    {
        $newArr = [];
        foreach($params['goods_info'] as $v) {
            if (!empty($newArr[$v['id'] . '_' . $v['is_refund']])) {
                $startNum = $newArr[$v['id']];
                $newArr[$v['id'] . '_' . $v['is_refund']] = $startNum + $v['num'];
            } else {
                $newArr[$v['id'] . '_' . $v['is_refund']] = $v['num'];
            }
        }


        $receiptNumber = $params['receipt_number'];
        $where = [
            'receipt_number' => $receiptNumber,
            'check_status' => [ReceiptApplyModel::CHECK_PASS,
                ReceiptApplyModel::CHECK_WAITING
            ]
        ];
        if (!empty($params['receipt_id'])) {
            $where['id[!]'] = $params['receipt_id'];
        }

        $res = ReceiptApplyModel::getRecord($where);
        if (!empty($res)) {
            throw new RunTimeException(['receipt_number_has_exist']);
        }

        $baId = $params['ba_id'];
        $baInfo = BAListModel::getRecord(['id' => $baId]);
        if (empty($baInfo)) {
            throw new RunTimeException(['ba_is_not_exist']);
        }
        $shopId = $params['shop_id'];
        if ($baInfo['shop_id'] != $shopId) {
            throw new RunTimeException(['ba_not_have_relate_shop']);
        }
        $shopInfo = ShopInfoModel::getRecord(['id' => $shopId]);
        if (empty($shopInfo)) {
            throw new RunTimeException(['shop_is_not_exist']);
        }
        $data = [
            'receipt_number' => $receiptNumber,
            'ba_id' => $baId,
            'buy_time' => strtotime($params['buy_time']),
            'shop_id' => $shopId,
            'create_time' => time(),
            'reference_money' => 0,
            'check_status' => ReceiptApplyModel::CHECK_WAITING,
            'pic_url' => $params['pic_url'],
            'ba_name' => $baInfo['name'],
            'shop_number' => $shopInfo['shop_number'],
            'shop_name' => $shopInfo['shop_name'],
            'add_type' => ReceiptApplyModel::ENTER_BACKEND,
            'backend_enter_employee' => $employeeId,
            'receipt_from' => $params['receipt_from']
        ];

        if (!empty($params['receipt_id'])) {
            $receiptId = $params['receipt_id'];
            ReceiptApplyModel::updateRecord($params['receipt_id'], $data);
        } else {
            $receiptId = ReceiptApplyModel::insertRecord(
                $data
            );
        }


        ReceiptApplyGoodsModel::batchUpdateRecord(['status' => ReceiptApplyGoodsModel::STATUS_DEL], ['receipt_apply_id' => $receiptId]);


        foreach($newArr as $k => $v) {
            $arr = explode('_', $k);
            $goodsId = $arr[0];
            $status = $arr[1];


            $goodsInfo = GoodsModel::getRecord(['id' => $goodsId]);

            $record = ReceiptApplyGoodsModel::getRecord(['receipt_apply_id' => $receiptId, 'goods_id' => $k, 'status' => $status]);

            if (empty($record)) {
                ReceiptApplyGoodsModel::insertRecord([
                    'goods_id' => $goodsInfo['id'],
                    'goods_number' => $goodsInfo['goods_number'],
                    'goods_name' => $goodsInfo['goods_name'],
                    'create_time' => time(),
                    'num' => $v,
                    'receipt_apply_id' => $receiptId,
                    'status' => $status
                ]);
            } else {
                throw new RunTimeException(['please_try']);
            }

        }


    }

    /**
     * 导出订单列表
     * @param $params
     * @param $employeeId
     * @return array
     */
    public static function exportData($params, $employeeId)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        $count = ReceiptApplyModel::getCount(['id[>=]' => 1]);

        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) =  ReceiptApplyModel::getBAManageReceiptList($employeeId, $params, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) =  ReceiptApplyModel::getRegionManageReceiptList($employeeId, $params, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = ReceiptApplyModel::getSuperReceiptList($params, 1, $count);
        }

        $title = [
            '小票编号',
            '所属BA',
            '所属BA店铺',
            '大区',
            '区域',
            '省份',
            '城市',
            '所属BA经理',
            '大区经理',
            '购买日期',
            '小票店铺编号',
            '小票店铺名称',
        ];


        var_dump ($list);
        die();






        return [self::buildReturnInfo($list), $totalCount];
    }

    /**
     * 得到订单列表
     * @param $params
     * @param $employeeId
     * @param $page
     * @param $count
     * @return array
     */
    public static function getReceiptList($params, $employeeId, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);

        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) =  ReceiptApplyModel::getBAManageReceiptList($employeeId, $params, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) =  ReceiptApplyModel::getRegionManageReceiptList($employeeId, $params, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = ReceiptApplyModel::getSuperReceiptList($params, $page, $count);
        }
        return [self::buildReturnInfo($list), $totalCount];
    }


    /**
     * 格式化返回信息
     * @param $list
     * @return array
     */
    private static function buildReturnInfo($list)
    {
        $res = [];
        foreach ($list as $k => $v) {
            $v['check_status_msg'] = ReceiptApplyModel::CHECK_STATUS_MSG[$v['check_status']];
            $v['reference_money'] = Util::yuan($v['reference_money']);
            $v['actual_money'] = Util::yuan($v['actual_money']);
            $res[$k] = $v;
        }

        return $res;
    }

    private static function getOneReturnInfo($info)
    {
        $info['check_status_msg'] = ReceiptApplyModel::CHECK_STATUS_MSG[$info['check_status']];
        $info['reference_money'] = Util::yuan($info['reference_money']);
        $info['actual_money'] = Util::yuan($info['actual_money']);
        $info['pic_url'] = AliOSS::signUrls($info['pic_url']);
        return $info;
    }

    /**
     * 销售单信息
     * @param $receiptId
     * @param $employeeId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getReceiptInfo($receiptId, $employeeId)
    {
        $receiptInfo = ReceiptApplyModel::getRecord(['id' => $receiptId]);
        if (empty($receiptInfo)) {
            throw new RunTimeException(['receipt_info_not_exist']);
        }

        //关联的BA信息
        $baInfo = BAListModel::getRecord(['id' => $receiptInfo['ba_id']]);
        $receiptInfo['ba_name'] = $baInfo['name'];
        $receiptInfo['ba_number'] = $baInfo['job_number'];

        //关联的商品信息
        $relateGoods = ReceiptApplyGoodsModel::getRecords(['receipt_apply_id' => $receiptInfo['id'], 'status[!]' => ReceiptApplyGoodsModel::STATUS_DEL]);
        foreach ($relateGoods as $k => $v) {
            $v['status_msg'] = ReceiptApplyGoodsModel::STATUS_MSG[$v['status']];
            $relateGoods[$k] = $v;
        }

        $receiptInfo['goods'] = $relateGoods;
        return self::getOneReturnInfo($receiptInfo);
    }

    /**
     * 更新处理销售单
     * @param $receiptIds
     * @param $checkStatus
     * @param $employeeId
     * @throws RunTimeException
     */
    public static function updateReceiptInfo($receiptIds, $checkStatus,$employeeId)
    {
        $info = ReceiptApplyModel::getRecords(['id' => explode(',', $receiptIds), 'check_status[!]' => ReceiptApplyModel::CHECK_WAITING]);
        if (!empty($info)) {
            throw new RunTimeException(['not_allow_deal_has_deal_info']);
        }
        $data = [
            'check_status' => $checkStatus,
            'update_time' => time()
        ];

        if ($checkStatus == ReceiptApplyModel::CHECK_PASS) {
            $data['pass_time'] = time();
        }

        ReceiptApplyModel::batchUpdateRecord($data, ['id' => explode(',', $receiptIds)]);
        //发红包的逻辑

    }
}
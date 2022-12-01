<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Excel\ExcelImportFormat;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\BAApplyModel;
use App\Models\BAListModel;
use App\Models\BaWeixinModel;
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
            if (!empty($newArr[$v['id'] . '_' . $v['status']])) {
                $startNum = $newArr[$v['id']];
                $newArr[$v['id'] . '_' . $v['status']] = $startNum + $v['num'];
            } else {
                $newArr[$v['id'] . '_' . $v['status']] = $v['num'];
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
            'update_time' => time(),
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
            $data['create_time'] = time();
            $receiptId = ReceiptApplyModel::insertRecord(
                $data
            );
        }


        //处理销售单关联的商品
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
                    'status' => $status,
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
            list($list, $totalCount) =  ReceiptApplyModel::getExportBAManageReceiptList($employeeId, $params, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) =  ReceiptApplyModel::getExportRegionManageReceiptList($employeeId, $params, 1, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = ReceiptApplyModel::getExportSuperReceiptList($params, 1, $count);
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
            '参考奖励',
            '实发奖励',
            '订单状态',
            '系统审核建议',
            '商品名称',
            '商品编码',
            '数量',
            '市场价',
            '是否退款',
            '最后更新时间'
        ];

        $dataResult = [];
        foreach($list as $v) {
            $dataResult[] = [
                'receipt_number' => $v['receipt_number'],
                'ba_name' => $v['ba_name'],
                'shop_name' => $v['shop_name'],
                'region_name' => $v['region_name'],
                'district_name' => '',
                'province_name' => $v['province_name'],
                'city_name' => $v['city_name'],
                'ba_manage' => $v['ba_manage'],
                'region_manage' => $v['region_manage'],
                'buy_time' => date('Y-m-d H:i:s', $v['buy_time']),
                'receipt_shop_number' => '',
                'receipt_shop_name' => '',
                'reference_money' => $v['reference_money'],
                'actual_money' => $v['actual_money'],
                'check_status_msg' => ReceiptApplyModel::CHECK_STATUS_MSG[$v['check_status']],
                'system_check_note' => $v['system_check_note'],
                'goods_name' => $v['goods_name'],
                'goods_number' => $v['goods_number'],
                'num' => $v['num'],
                'market_price' => '',
                'is_refund' => ReceiptApplyGoodsModel::STATUS_MSG[$v['status']],
                'last_update_time' => date('Y-m-d H:i:s', $v['last_update_time'])

            ];
        }
        $fileName =  '(' . date("Y-m-d H:i:s") . '_'.mt_rand(1, 100) . ')订单列表-' . ReceiptApplyModel::RECEIPT_FROM[$params['receipt_from']] . '.xlsx';
        $tmpFileSavePath = ExcelImportFormat::createExcelTable($dataResult, $title,
            ExcelImportFormat::OUTPUT_TYPE_SAVE_FILE);
        $ossPath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_TMP_EXCEL . '/' . $fileName;
        AliOSS::uploadFile($ossPath, $tmpFileSavePath);
        unlink($tmpFileSavePath);

        $ossPath = AliOSS::signUrls($ossPath, "", "", "", true);

        return $ossPath;

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
        $receiptInfo['buy_time'] = date('Y-m-d H:i:s', $receiptInfo['buy_time']);

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


    /**
     * BA上传票据申请
     * @param $params
     * @param $baId
     * @throws RunTimeException
     */
    public static function uploadApply($params, $baId)
    {

        if (strlen($params['receipt_number']) != 24) {
            throw new RunTimeException(['receipt_number_must_24_len']);
        }

        //如果是编辑，校验是否有关联关系
        if (!empty($params['receipt_id'])) {
            $relate = ReceiptApplyModel::getRecord(['id' => $params['receipt_id'], 'ba_id' => $baId]);
            if (empty($relate)) {
                throw new RunTimeException(['receipt_not_relate_ba']);
            }

        }


        //一个单号仅可有一条申请记录，如有多个，请让他联系管理员
        //校验BA手动输入的单号
        $res = ReceiptApplyModel::getRecord(['receipt_number' => $params['receipt_number']]);

        //编辑的时候如果票据撞单，也需要拒绝入库
        if (empty($params['receipt_id'])) {
            if (!empty($res)) {
                throw new RunTimeException(['receipt_number_has_exist']);
            }
        } else {
            if (!empty($res) && $params['receipt_id'] != $res['id']) {
                throw new RunTimeException(['receipt_number_has_exist']);
            }
        }



        //校验BA的状态
        $baInfo = BAApplyModel::getRecord(['id' => $baId]);
        if (empty($baInfo)) {
            throw new RunTimeException(['ba_is_not_exist']);
        }

        if ($baInfo['check_status'] != BAApplyModel::APPLY_PASS) {
            throw new RunTimeException(['check_status_not_pass']);
        }

        //图片识别，作为后台的参考结果
        $picUrl = AliOSS::signUrls($params['pic_url']);


        //图片识别结果，仅供系统审核建议,对于关联的商品信息不能确定时，要提供建议
        list($referReceiptFrom, $referReceiptNumber, $referBuyTime, $referGoodsInfo, $referRemark) = AutoCheckPicture::dealReceiptInfo($picUrl);
        $remark = $referRemark;


        //图片识别拿到可识别的商品信息
        $newArr = [];
        foreach($referGoodsInfo as $v) {
            if (!empty($newArr[$v['id'] . '_' . $v['status']])) {
                $startNum = $newArr[$v['id']];
                $newArr[$v['id'] . '_' . $v['status']] = $startNum + $v['num'];
            } else {
                $newArr[$v['id'] . '_' . $v['status']] = $v['num'];
            }
        }



        //小票编号必须唯一
        $where = [
            'receipt_number' => $referReceiptNumber,
        ];

        $res = ReceiptApplyModel::getRecord($where);

        if (!empty($res) && $res['ba_id'] != $baId) {
            $remark[] = '图片识别的单号' . $referReceiptNumber . '已在系统存在, 订单状态是' . ReceiptApplyModel::CHECK_STATUS_MSG[$res['check_status']];
        }


        if ($referReceiptNumber != $params['receipt_number']) {
            $remark[] = '图片识别票据单号和用户输入单号不一致，图片识别单号为' . $referReceiptNumber;
        }


        $shopInfo = ShopInfoModel::getRecord(['id' => $baInfo['shop_id']]);

        $note = '';
        if (!empty($remark)) {
            foreach($remark as $k => $v) {
                $note .= $k + 1 . ' ' . $v . PHP_EOL;
            }
        }


        //小票入库
        $data = [
            'receipt_number' => $params['receipt_number'],
            'ba_id' => $baId,
            'buy_time' => empty($referBuyTime) ? date('Y-m-d H:i:s') : strtotime($referBuyTime),
            'shop_id' => $shopInfo['id'],
            'update_time' => time(),
            'reference_money' => 0,
            'check_status' => ReceiptApplyModel::CHECK_WAITING,
            'pic_url' => $params['pic_url'],
            'ba_name' => $baInfo['name'],
            'shop_number' => $shopInfo['shop_number'],
            'shop_name' => $shopInfo['shop_name'],
            'add_type' => ReceiptApplyModel::ENTER_BA,
            'receipt_from' => $referReceiptFrom,
            'system_check_note' => $note
        ];

        if (empty($params['receipt_id'])) {
            $data['create_time'] = time();
            $receiptId = ReceiptApplyModel::insertRecord(
                $data
            );
        } else {
            ReceiptApplyModel::updateRecord($params['receipt_id'], $data);
            $receiptId = $params['receipt_id'];
        }



        //处理销售单关联的商品
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
                    'status' => $status,
                ]);
            } else {
                throw new RunTimeException(['please_retry']);
            }

        }
    }

    /**
     * BA看到的票据申请列表
     * @param $baId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getBAReceiptList($baId, $page, $count, $params)
    {
        $where = [];
        if (!empty($params['check_status'])) {
            $where['check_status'] = $params['check_status'];
        }

        $where['ba_id'] = $baId;


        $totalCount = ReceiptApplyModel::getCount($where);


        $where['LIMIT'] = [($page-1) * $count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        $list = ReceiptApplyModel::getRecords($where);


        $info = [];
        if (!empty($list)) {
            foreach($list as $v) {
                $info[] = [
                    'receipt_id' => $v['id'],
                    'pic_url' => AliOSS::signUrls($v['pic_url']),
                    'receipt_number' => $v['receipt_number'],
                    'create_time' => $v['create_time'],
                    'check_status' => $v['check_status'],
                    'check_status_msg' => ReceiptApplyModel::CHECK_STATUS_MSG[$v['check_status']],
                    'employee_remark' => $v['employee_remark']

                ];
            }
        }
        return [$totalCount, $info];
    }


    /**
     * 获取BA的某个票据申请详情
     * @param $receiptId
     * @param $baId
     * @return array
     * @throws RunTimeException
     */
    public static function getBaReceiptInfo($receiptId, $baId)
    {
        $receiptInfo = ReceiptApplyModel::getRecord(['id' => $receiptId, 'ba_id' => $baId]);
        if (empty($receiptInfo)) {
            throw new RunTimeException(['receipt_info_not_exist']);
        }

        return [
            'receipt_id' => $receiptInfo['id'],
            'pic_url' => AliOSS::signUrls($receiptInfo['pic_url']),
            'receipt_number' => $receiptInfo['receipt_number'],
            'create_time' => $receiptInfo['create_time'],
            'check_status' => $receiptInfo['check_status'],
            'check_status_msg' => ReceiptApplyModel::CHECK_STATUS_MSG[$receiptInfo['check_status']],
            'employee_remark' => $receiptInfo['employee_remark']
        ];
    }
}
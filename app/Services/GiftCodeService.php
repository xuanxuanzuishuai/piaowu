<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/02/18
 * Time: 19:58
 *
 * 机构相关数据service
 */

namespace App\Services;

use App\Libs\Code;
use App\Libs\CodeConsume;
use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Libs\Dict;

class GiftCodeService
{
    /**
     * @param $num
     * @param $validNum
     * @param $validUnits
     * @param $generateChannel
     * @param string|array $buyer id|[['id' => 1, 'mobile' => 138...], ...]
     * @param $generateWay
     * @param null $remarks
     * @param null $employeeId
     * @param null $buyTime
     * @param array $billInfo
     * @return array [['id' => 1, 'mobile' => 138..., 'code' => 'abc123'], ...]
     * 批量生成激活码
     */
    public static function batchCreateCode($num,
                                           $validNum,
                                           $validUnits,
                                           $generateChannel,
                                           $buyer,
                                           $generateWay,
                                           $remarks = NULL,
                                           $employeeId = NULL,
                                           $buyTime = NULL,
                                           $billInfo = [])
    {
        $i = 0;
        //随机生成码
        do {
            $codes = self::randCodeCreate($num, $generateChannel, $buyer);
            //是否有重合
            $hasExist = GiftCodeModel::codeExists($codes);

            if (!$hasExist) {
                break;
            }

            if ($i >= 10) {
                $codes = NULL;
                break;
            }

            $i++;
        } while (true);

        if (empty($codes)) {
            return [];
        }

        //插入数据
        $t = time();
        $params = [
            'generate_channel' => $generateChannel,
            'buy_time' => $buyTime,
            'valid_num' => $validNum,
            'valid_units' => $validUnits,
            'generate_way' => $generateWay,
            'operate_user' => $employeeId,
            'create_time' => $t,
            'operate_time' => $t,
            'remarks' => $remarks,
            'bill_id' => $billInfo['bill_id'] ?? '',
            'parent_bill_id' => $billInfo['parent_bill_id'] ?? '',
            'bill_amount' => $billInfo['bill_amount'] ?? 0,
            'bill_app_id' => $billInfo['bill_app_id'] ?? 0,
            'bill_package_id' => $billInfo['bill_package_id'] ?? 0,
            'employee_uuid' => $billInfo['employee_uuid'] ?? '',
            'package_v1' => !empty($billInfo['package_v1']) ? GiftCodeModel::PACKAGE_V1 : GiftCodeModel::PACKAGE_V1_NOT
        ];
        !empty($params['employee_uuid']) && $params['employee_dept_info'] = DeptService::getSubAllParentDept($params['employee_uuid']);

        $params['buyer'] = $buyer;

        foreach ($codes as $i => $value) {
            $params['code'] = $value;

            GiftCodeModel::insertRecord($params, false);
        }

        return $codes;
    }

    /**
     * @param $num
     * @param $generateChannel
     * @param $buyer
     * @return array
     * 随机码生成
     */
    private static function randCodeCreate($num, $generateChannel, $buyer)
    {
        $data = [];
        for ($i = 0; $i < $num; $i++) {
            $data[] = substr(base_convert(str_replace('.', '', $i . $buyer . microtime() . mt_rand(10000, 99999) . $generateChannel), 10, 36), 0, 12);
        }
        return $data;
    }

    /**
     * 给学生生成激活码
     *
     * @param $validNum
     * @param $validUnits
     * @param $generateChannel
     * @param $buyer
     * @param $generateWay
     * @param $operateId
     * @param bool $apply 是否直接充值
     * @param null $remarks
     * @param null $buyTime
     * @param null $billId
     * @param int $billAmount
     * @throws RunTimeException
     */
    public static function createByStudent($validNum,
                                           $validUnits,
                                           $generateChannel,
                                           $buyer,
                                           $generateWay,
                                           $operateId,
                                           $apply = false,
                                           $remarks = NULL,
                                           $buyTime = NULL,
                                           $billId = NULL,
                                           $billAmount = 0)
    {
        for($i = 0; $i < 10; $i++) {
            $codes = self::randCodeCreate(1, $generateChannel, $buyer);
            //是否有重合
            $hasExist = GiftCodeModel::codeExists($codes);

            if (!$hasExist) {
                break;
            } else {
                $codes = null;
            }
        }

        if (empty($codes)) {
            throw new RunTimeException(['gift_code_create_error']);
        }

        if ($apply) {
            StudentService::addSubDuration($buyer, $validNum, $validUnits);
        }

        //插入数据
        $now = time();
        $params = [
            'code' => $codes[0],
            'generate_channel' => $generateChannel,
            'buyer' => $buyer,
            'buy_time' => $buyTime,
            'apply_user' => $buyer,
            'valid_num' => $validNum,
            'valid_units' => $validUnits,
            'be_active_time' => $now,
            'generate_way' => $generateWay,
            'code_status' => GiftCodeModel::CODE_STATUS_HAS_REDEEMED,
            'operate_user' => $operateId,
            'create_time' => $now,
            'operate_time' => $now,
            'remarks' => $remarks,
            'bill_id' => $billId,
            'bill_amount' => $billAmount,
        ];

        $result = GiftCodeModel::insertRecord($params, false);
        if (empty($result)) {
            throw new RunTimeException(['gift_code_create_error']);
        }
    }

    /**
     * @param $params
     * @return array
     * 获取激活码
     */
    public static function batchGetCode($params)
    {
        list($totalCount, $data) = GiftCodeModel::getLikeCodeInfo($params);

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $data[$key]['generate_channel'] = Dict::getCodeChannel($value['generate_channel']);
                $data[$key]['generate_way'] = Dict::getCodeWay($value['generate_way']);
                $data[$key]['code_status_state'] = Dict::getCodeStatus($value['code_status']);
                if (!empty($value['buy_time'])) {
                    $data[$key]['buy_time'] = date('Y-m-d H:i:s', $value['buy_time']);
                }
                if (!empty($value['apply_mobile'])) {
                    $data[$key]['apply_name'] = $value['apply_name'] . '(' . $value['apply_mobile'] . ')';
                }
                $data[$key]['valid_time'] = $value['valid_num'] . Dict::getCodeTimeUnit($value['valid_units']);
                if (!empty($value['be_active_time'])) {
                    $data[$key]['be_active_time'] = date('Y-m-d H:i:s', $value['be_active_time']);
                }
                //购买人（根据购买渠道区分）
                switch ($value['generate_channel']) {
                    case GiftCodeModel::BUYER_TYPE_ORG:
                        $data[$key]['buyer_name'] = $value['org_buyer_name'];
                        break;
                    default: //不是机构，就人为是个人(学生)
                        $data[$key]['buyer_name'] = "{$value['student_buyer_name']}({$value['student_buyer_mobile']})";
                }
                if($value['raw_operate_user'] == EmployeeModel::SYSTEM_EMPLOYEE_ID) {
                    $data[$key]['operate_user'] = EmployeeModel::SYSTEM_EMPLOYEE_NAME;
                }
            }
        }
        return [$totalCount, $data];
    }

    /**
     * 作废激活码
     * @param int|array $ids
     * @param bool $force 强制删除使用过的激活码
     * @return int 修改的数量
     */
    public static function abandonCode($ids, $force = false)
    {
        $where = ['id' => $ids];
        if (!$force) {
            $where['code_status'] = GiftCodeModel::CODE_STATUS_NOT_REDEEMED;
        } else {
            $where['code_status'] = [GiftCodeModel::CODE_STATUS_NOT_REDEEMED, GiftCodeModel::CODE_STATUS_HAS_REDEEMED];
        }


        if (!empty($ids)) {
            return GiftCodeModel::batchUpdateRecord([
                'code_status' => GiftCodeModel::CODE_STATUS_INVALID,
                'operate_time' => time()
            ], $where, false);
        }
        return 0;
    }


    /**
     * dss后台作废激活码
     * @param int $id
     * @return int 修改的数量
     * @throws RunTimeException
     */
    public static function orgWebAbandonCode($id)
    {
        $giftCodeData = GiftCodeModel::getRecord(['id' => $id]);
        if (empty($giftCodeData)) {
            throw new RunTimeException(['gift_code_empty']);
        }

        // 只可以作废 手动在dss后台手动生成、机构生成 并且是未激活或者已激活 的激活码
        $channelStatus = ($giftCodeData['generate_channel'] == GiftCodeModel::BUYER_TYPE_STUDENT || $giftCodeData['generate_channel'] == GiftCodeModel::BUYER_TYPE_ORG);
        $codeStatus = ($giftCodeData['code_status'] == GiftCodeModel::CODE_STATUS_NOT_REDEEMED || $giftCodeData['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED);
        if (!$channelStatus || !$codeStatus) {
            throw new RunTimeException(['gift_code_type_error']);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        // 修改激活码状态
        $updateGiftCodeStatus = GiftCodeModel::updateRecord($giftCodeData['id'], ['code_status' => GiftCodeModel::CODE_STATUS_INVALID]);
        $updateSubDurationStatus = Constants::STATUS_TRUE;

        // 已激活的扣除相应时间
        if ($giftCodeData['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED) {
            $updateSubDurationStatus = StudentService::reduceSubDuration($giftCodeData['apply_user'], $giftCodeData['valid_num'], $giftCodeData['valid_units']);
        }

        if (!$updateGiftCodeStatus || !$updateSubDurationStatus) {
            // 更新失败
            $db->rollBack();
            throw new RunTimeException(['update_fail']);
        }

        if (!empty($giftCodeData['package_v1'])) {
            // 新产品包 赠单-->退单
            $erp = new Erp();
            $erp->abandonFreeBill([
                'free_bill_id' => $giftCodeData['bill_id']
            ]);
        }

        $db->commit();


        return $updateGiftCodeStatus;
    }

    /**
     * 订单是否存在
     * @param $billId
     * @param $parentBillId
     * @return bool
     */
    public static function getGiftCodeByBill($billId, $parentBillId)
    {
        $where = [];
        if (!empty($parentBillId)) {
            $where['parent_bill_id'] = $parentBillId;
        }
        if (!empty($billId)) {
            $where['bill_id'] = $billId;
        }
        $giftCode = GiftCodeModel::getRecord($where);
        return !empty($giftCode) ? true : false;
    }

    /**
     * 激活码时长消耗
     * @param $buyer
     * @param $giftCodeId
     * @return array|bool
     */
    public static function codeConsume($buyer, $giftCodeId)
    {
        $codes = GiftCodeModel::getRecords([
            'buyer' => $buyer,
            'code_status' => GiftCodeModel::CODE_STATUS_HAS_REDEEMED,
            'be_active_time[>]' => 0,
            'ORDER' => ['be_active_time' => 'ASC']
        ]);

        $array = [];
        foreach ($codes as $code) {
            $units = $code['valid_units'];
            $num = $code['valid_num'];

            if ($units == GiftCodeModel::CODE_TIME_YEAR) {
                $duration = $num * 366;
            } elseif ($units == GiftCodeModel::CODE_TIME_MONTH) {
                $duration = $num * 31;
            } else {
                $duration = $num;
            }
            $array[] = new Code($code['id'], $code['be_active_time'],  $duration);
        }

        $consume = new CodeConsume($array);
        return $consume->consume($giftCodeId);
    }

    /**
     * 检查是否续费学员
     * @param $studentId
     * @return bool
     */
    public static function checkIsRenewStudent($studentId)
    {
        $isRenew = GiftCodeModel::checkIsRenewStudentStatus($studentId);
        return $isRenew;
    }
}
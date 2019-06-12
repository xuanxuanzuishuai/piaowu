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

use App\Libs\Constants;
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
     * @param $buyer
     * @param $generateWay
     * @param null $remarks
     * @param null $employeeId
     * @param null $buyTime
     * @param null|int $billId
     * @param int $billAmount
     * @return array
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
                                           $billId = NULL,
                                           $billAmount = 0)
    {
        $ret = false;
        $i = 0;
        //随机生成码
        do {
            $data['code'] = self::randCodeCreate($num, $generateChannel, $buyer);
            //是否有重合
            $hasExist = GiftCodeModel::getCodeInfo($data);
            $hasExist && $ret = true;
            //避免死循环
            if ($i >= 10) {
                $data['code'] = NULL;
                break;
            }
            $i++;
        } while ($ret);
        //插入数据
        $t = time();
        $params = [
            'generate_channel' => $generateChannel,
            'buyer' => $buyer,
            'buy_time' => $buyTime,
            'valid_num' => $validNum,
            'valid_units' => $validUnits,
            'generate_way' => $generateWay,
            'operate_user' => $employeeId,
            'create_time' => $t,
            'operate_time' => $t,
            'remarks' => $remarks,
            'bill_id' => $billId,
            'bill_amount' => $billAmount,
        ];

        if (!empty($data['code'])) {
            foreach ($data['code'] as $value) {
                $params['code'] = $value;
                GiftCodeModel::insertRecord($params, false);
            }
        }
        return $data['code'];
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
                        $data[$key]['buyer_name'] = $value['name'];
                        break;
                    default: //不是机构，就人为是个人(学生)
                        $data[$key]['buyer_name'] = $value['name'] . '(' . $value['mobile'] . ')';
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
     * @return int 修改的数量
     */
    public static function abandonCode($ids)
    {
        if (!empty($ids)) {
            return GiftCodeModel::batchUpdateRecord(
                ['code_status' => GiftCodeModel::CODE_STATUS_INVALID],
                ['id' => $ids, 'code_status' => GiftCodeModel::CODE_STATUS_NOT_REDEEMED],
                false);
        }
        return 0;
    }
}
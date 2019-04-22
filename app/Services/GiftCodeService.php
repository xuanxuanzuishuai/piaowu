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
     * @return array
     * 批量生成激活码
     */
    public static function batchCreateCode($num, $validNum, $validUnits, $generateChannel, $buyer, $generateWay, $remarks = NULL, $employeeId = NULL, $buyTime = NULL)
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
        $params['generate_channel'] = $generateChannel;
        $params['buyer'] = $buyer;
        $params['buy_time'] = $buyTime;
        $params['valid_num'] = $validNum;
        $params['valid_units'] = $validUnits;
        $params['generate_way'] = $generateWay;
        $params['operate_user'] = $employeeId;
        $params['create_time'] = $t;
        $params['operate_time'] = $t;
        $params['remarks'] = $remarks;
        if (!empty($data['code'])) {
            foreach ($data['code'] as $value) {
                $params['code'] = $value;
                GiftCodeModel::InsertCodeInfo($params);
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
                        $data[$key]['buyer_name'] = $value['agent_name'];
                        break;
                    case GiftCodeModel::BUYER_TYPE_STUDENT:
                        $data[$key]['buyer_name'] = $value['name'] . '(' . $value['mobile'] . ')';
                        break;
                    case GiftCodeModel::BUYER_TYPE_OTHER:
                        $data[$key]['buyer_name'] = Dict::getCodeOtherChannelBuyer($value['buyer']);
                        break;
                }
            }
        }
        return [$totalCount, $data];
    }

    /**
     * 作废激活码
     * @param $params
     */
    public static function abandonCode($params)
    {
        $idsArr = explode(',', $params['ids']);
        if (!empty($idsArr)) {
            foreach ($idsArr as $value) {
                GiftCodeModel::batchUpdateRecord(
                    ['id' => $value, 'code_status' => GiftCodeModel::CODE_STATUS_NOT_REDEEMED],
                    ['code_status' => GiftCodeModel::CODE_STATUS_INVALID],
                    false);
            }
        }
    }
}
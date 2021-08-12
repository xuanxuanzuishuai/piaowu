<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/03/09
 * Time: 上午10:47
 */

namespace App\Services;

use App\Libs\Erp;

class ErpUserService
{
    //学生账户子类型
    const ACCOUNT_SUB_TYPE_STUDENT_POINTS = 3001;//学生积分余额
    const ACCOUNT_SUB_TYPE_CASH = 1001; // 学生现金账户

    const ACCOUNT_SUB_TYPE_GOLD_LEAF = 3002; // 学生金叶子账户

    /**
     * 获取学生账户信息
     * @param string $uuid
     * @param int|array $subType
     * @return float|int|array|mixed
     */
    public static function getStudentAccountInfo(string $uuid, $subType = self::ACCOUNT_SUB_TYPE_CASH)
    {
        $subTypes = [];
        if (is_array($subType)) {
            $ret = [];
            foreach ($subType as $value) {
                $ret[$value] = 0;
            }
            $subTypes = $subType;
        } else {
            $ret = 0;
            $subTypes[] = $subType;
        }

        $erp      = new Erp();
        $accounts = $erp->studentAccount($uuid);
        if (!isset($accounts['code']) || empty($accounts['data'])) {
            return $ret;
        }
        $accounts = array_column($accounts['data'], null, 'sub_type');

        $data = [];

        foreach ($subTypes as $item) {
            if (!isset($accounts[$item])) {
                continue;
            }
            $account = $accounts[$item];

            if ($item == self::ACCOUNT_SUB_TYPE_CASH) {
                $data[$item] = ($account['total_num'] * 100 - $account['out_time_num'] * 100) ?? 0;
            } else {
                $data[$item] = $account['total_num'] ?? 0;
            }
        }

        if (is_array($subType)) return $data;

        return $data[$subType] ?? 0;
    }
}

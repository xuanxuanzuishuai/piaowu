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

    /**
     * 获取学生现金余额
     * @param $uuid
     * @return float|int
     */
    public static function getStudentCash($uuid)
    {
        $erp = new Erp();
        $accounts = $erp->studentAccount($uuid);
        if (!isset($accounts['code']) || empty($accounts['data'])) {
            return 0;
        }
        $accounts = array_column($accounts['data'], null, 'sub_type');
        $cash = $accounts[self::ACCOUNT_SUB_TYPE_CASH];
        return ($cash['total_num'] * 100 - $cash['out_time_num'] * 100);
    }
}

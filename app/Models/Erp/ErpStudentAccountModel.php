<?php
namespace App\Models\Erp;

class ErpStudentAccountModel extends ErpModel
{
    public static $table = 'erp_student_account';

    // 账户子类型 根据type类型区分
    const SUB_TYPE_CNY = 1001; // 人民币余额
    const SUB_TYPE_DOLLAR = 1002; // 人民币退费
    const SUB_TYPE_VIRTUAL_COIN = 2001; // 充值币余额
    const SUB_TYPE_GOLD_LEAF = 3002; // 账户子类型

    const DATA_TYPE_LEAF = 4; //金叶子
}
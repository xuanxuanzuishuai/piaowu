<?php


namespace App\Models\Erp;

class ErpStudentOrderV1Model extends ErpModel
{
    public static $table = 'erp_student_order_v1';

    // 1 包含待支付 2 支付待审核 3 审核未通过 4 全部支付单已支付 5 未支付(指定时间内未支付)
    const STATUS_WAIT_PAY = 1;
    const STATUS_VERIFYING = 2;
    const STATUS_REJECTED = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCEL = 5;

    //支付类型
    const SOURCE_SELF = 1;     //用户自己支付
    const SOURCE_MAN = 2;      //后台补录
    const SOURCE_CRM_MAN = 3;  //CRM代客下单
}

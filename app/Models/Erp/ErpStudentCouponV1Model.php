<?php
namespace App\Models\Erp;

class ErpStudentCouponV1Model extends ErpModel
{
    //优惠券状态 1未使用 2 已使用 3 已失效 4 已作废
    const STATUS_UNUSE = 1;
    const STATUS_USED = 2;
    const STATUS_EXPIRE = 3;
    const STATUS_ABANDONED = 4;
    
    public static $table = 'erp_student_coupon_v1';
}
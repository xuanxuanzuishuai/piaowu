<?php

namespace App\Models\Erp;

class ErpStudentAttributeModel extends ErpModel
{
    public static $table = 'erp_student_attribute';

    // 真人学员的生命周期-新注册
    const REAL_PERSON_LIFE_CYCLE_NEW_REGISTER = 11;
    // 真人学员的生命周期-已分配（分配了任意的cc）
    const REAL_PERSON_LIFE_CYCLE_ASSIGNED_ASSISTANT = 12;
    // 真人学员的生命周期-已联系（进行了任意电话联系/跟进登记)
    const REAL_PERSON_LIFE_CYCLE_CONTACTED = 13;
    // 真人学员的生命周期-预约体验（首次进行了体验课预约）
    const REAL_PERSON_LIFE_CYCLE_BOOK_TRIAL = 14;
    // 真人学员的生命周期-体验完成（体验课状态首次变成已经出席）
    const REAL_PERSON_LIFE_CYCLE_TRIAL_FINISHED = 15;
    // 真人学员的生命周期-学习中（支付了金额大于3000的订单）
    const REAL_PERSON_LIFE_CYCLE_LEARNING = 16;
    // 真人学员的生命周期-待续费（剩余正式课时小于20节或者学员上课有效期小于30天）
    const REAL_PERSON_LIFE_CYCLE_WAIT_RENEW = 17;
    // 真人学员的生命周期-付费过期
    const REAL_PERSON_LIFE_CYCLE_EXPIRED = 18;
    // 真人学员的生命周期-退费
    const REAL_PERSON_LIFE_CYCLE_REFUND = 19;
}

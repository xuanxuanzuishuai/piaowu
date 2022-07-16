<?php

namespace App\Models\Erp;

class ErpStudentAccountModel extends ErpModel
{
    public static $table = 'erp_student_account';

    // 账户子类型 根据type类型区分
    const SUB_TYPE_CNY = 1001; // 人民币余额
    const SUB_TYPE_DOLLAR = 1002; // 人民币退费
    const SUB_TYPE_VIRTUAL_COIN = 2001; // 充值币余额
    const SUB_TYPE_INTGEGRAL = 3001; // 音符
    const SUB_TYPE_GOLD_LEAF = 3002; // 金叶子
    const DATA_TYPE_LEAF = 4; //金叶子
    //发放积分类用户行为类型
    const LOTTERY_ACTION = 6003; //抽奖活动
    const TYPE_LOTTERY_ACTIVE_HIS_AWARD_COURSE = 18; //活动赠课-抽奖活动赠课
    const SOURCE_TYPE_LIMIT_TIME_ACTIVITY_AWARD = 5009;//限时活动奖励

    //账户各种资产名称
    const ACCOUNT_ASSETS_NAME_MAP = [
        self::SUB_TYPE_INTGEGRAL => '音符',
        self::SUB_TYPE_GOLD_LEAF => '金叶子',
    ];
}
<?php

namespace App\Models\Erp;

use App\Libs\Constants;

class ErpStudentAppModel extends ErpModel
{
    public static $table = 'erp_student_app';
    //状态
    const STATUS_DEFAULT = 0;       // 默认状态0
    const STATUS_REGISTER = 1;      // 注册
    const STATUS_BOOK = 2;          // 已预约
    const STATUS_CONFIRM = 3;       // 待出席
    const STATUS_ATTEND = 4;        // 已出席
    const STATUS_FINISH = 41;       // 已完课
    const STATUS_NOT_ATTEND = 5;    // 未出席
    const STATUS_CANCEL = 6;        // 已取消
    const STATUS_PAID = 7;          // 付费

    public static $statusMap = [
        self::STATUS_DEFAULT => '未定义',
        self::STATUS_REGISTER => '注册',
        self::STATUS_BOOK => '已预约',
        self::STATUS_CONFIRM => '待出席',
        self::STATUS_ATTEND => '出席',
        self::STATUS_FINISH => '已完课',
        self::STATUS_NOT_ATTEND => '未出席',
        self::STATUS_CANCEL => '已取消',
        self::STATUS_PAID => '付费',
    ];

    public static function getRegisterRoughCount()
    {
        $table = self::$table;
        $appId = Constants::USER_TYPE_STUDENT;
        return self::dbRO()->count($table, ['app_id' => $appId]);
    }

}
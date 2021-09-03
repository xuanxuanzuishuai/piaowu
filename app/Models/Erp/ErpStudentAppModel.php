<?php
namespace App\Models\Erp;

use App\Libs\Constants;

class ErpStudentAppModel extends ErpModel
{
    public static $table = 'erp_student_app';

    public static function getRegisterRoughCount()
    {
        $table = self::$table;
        $appId = Constants::USER_TYPE_STUDENT;
        return self::dbRO()->count($table, null, null, ['app_id' => $appId]);
    }

}
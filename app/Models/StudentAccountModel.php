<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-29
 * Time: 15:16
 */

namespace App\Models;


class StudentAccountModel extends Model
{
    public static $table = "student_account";
    const TYPE_CASH = 1; // 现金
    const TYPE_VIRTUAL = 2; // 虚拟金

    const STATUS_NORMAL = 1;
    const STATUS_CANCEL = 0;

    public static function insertSA($studentAccount) {
        return self::insertRecord($studentAccount);
    }

    public static function updateSA($data,$where) {
        return self::batchUpdateRecord($data,$where);
    }

    public static function getSADetailBySId($studentIds, $type = null)
    {
        $where = ['student_id' => $studentIds, 'status' => self::STATUS_NORMAL];
        if (!empty($type)) {
            $where['type'] = $type;
        }
        return self::getRecords($where);
    }


}
<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/15
 * Time: 11:36 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentRefereeModel extends Model
{
    public static $table = 'student_referee';
    //referee_type 推荐人类型 1 学生 2 老师
    const REFEREE_TYPE_STUDENT = UserQrTicketModel::STUDENT_TYPE;
    const REFEREE_TYPE_TEACHER = UserQrTicketModel::TEACHER_TYPE;

    /**
     * @param $refererId
     * @param $packageIdArr
     * @param $startTime
     * @param null $isV1Package
     * @return mixed
     * 被推荐人在时间范围有没有买过特定的课包
     */
    public static function refereeBuyCertainPackage($refererId, $packageIdArr, $startTime, $isV1Package = null)
    {
        $db = MysqlDB::getDB();
        $where = [
            self::$table . '.referee_id' => $refererId,
            GiftCodeModel::$table . '.bill_package_id' => $packageIdArr,
            GiftCodeModel::$table . '.create_time[>]' => $startTime
        ];
        if (!is_null($isV1Package)) {
            $where[GiftCodeModel::$table . '.package_v1'] = $isV1Package;
        }
        return $db->get(
            self::$table,
            [
                '[><]' . GiftCodeModel::$table => ['student_id' => 'buyer']
            ],
            [GiftCodeModel::$table . '.id'],
            $where
        );
    }

}
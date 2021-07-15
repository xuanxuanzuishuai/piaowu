<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/6/10
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;

class CountingActivityModel extends Model
{
    public static $table = "counting_activity";

    const ENABLE_STATUS = 2;
    const DISABLE_STATUS = 1;

    public static function createActivity($data, $operator_id, $now){

        return self::insertRecord([
            'op_activity_id'    => $data['op_activity_id'],
            'name'              => $data['name'],
            'start_time'        => $data['start_time'],
            'end_time'          => $data['end_time'],
            'sign_end_time'     => $data['sign_end_time'],
            'join_end_time'     => $data['join_end_time'],
            'remark'            => $data['remark'],
            'rule_type'         => $data['rule_type'],
            'nums'              => $data['nums'],
            'title'             => $data['title'],
            'instruction'       => $data['instruction'],
            'banner'            => $data['banner'],
            'reminder_pop'      => $data['reminder_pop'],
            'award_thumbnail'   => $data['award_thumbnail'],
            'status'            => self::DISABLE_STATUS,
            'create_time'       => $now,
            'update_time'       => $now,
            'operator_id'       => $operator_id
        ]);
    }

}
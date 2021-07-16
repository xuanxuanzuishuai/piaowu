<?php
/**
 * 计数任务基础信息表
 *
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;


class CountingActivityModel extends Model
{
    public static $table = "counting_activity";

    const DISABLE_STATUS = 1; //禁用
    const NORMAL_STATUS = 2; //启用

    const RULE_TYPE_CONTINU = 1; // 连续
    const RULE_TYPE_COUNT = 2; //累计

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
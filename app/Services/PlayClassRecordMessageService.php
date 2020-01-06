<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/6
 * Time: 11:02 AM
 */

namespace App\Services;


use App\Models\PlayClassRecordMessageModel;

class PlayClassRecordMessageService
{
    public static function save($message)
    {
        $data = [
            'create_time' => time(),
            'body' => json_encode($message)
        ];
        $id = PlayClassRecordMessageModel::insertRecord($data, false);
        return $id;
    }
}
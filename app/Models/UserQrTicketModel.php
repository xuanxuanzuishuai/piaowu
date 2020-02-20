<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/10/25
 * Time: 12:07 PM
 */

namespace App\Models;


class UserQrTicketModel extends Model
{
    public static $table = "user_qr_ticket";
    const STUDENT_TYPE = 1;
    const TEACHER_TYPE = 2;
    //海报存储目录
    public static $posterDir = [
        self::STUDENT_TYPE=>"studentPoster",
        self::TEACHER_TYPE=>"teacherPoster",
    ];
}
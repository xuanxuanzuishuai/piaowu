<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

class StudentInviteModel extends Model
{
    const REFEREE_TYPE_STUDENT = 1; // 推荐人类型：学生
    public static $table = "student_invite";
}
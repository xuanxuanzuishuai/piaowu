<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/19
 * Time: 上午9:54
 */

namespace App\Models;


class StudentOrgModel extends Model
{
    const STATUS_STOP = 0; //解绑
    const STATUS_NORMAL = 1; //绑定
    public static $table = 'student_org';
}
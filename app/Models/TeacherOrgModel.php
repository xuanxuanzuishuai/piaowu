<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午9:04
 */

namespace App\Models;


class TeacherOrgModel extends Model
{
    const STATUS_STOP = 0; // 解除绑定
    const STATUS_NORMAL = 1; // 绑定
    public static $table = 'teacher_org';
}
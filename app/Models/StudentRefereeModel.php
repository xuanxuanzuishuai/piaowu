<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/15
 * Time: 11:36 PM
 */

namespace App\Models;

class StudentRefereeModel extends Model
{
    public static $table = 'student_referee';
    //referee_type 推荐人类型 1 学生 2 老师
    const REFEREE_TYPE_STUDENT = UserQrTicketModel::STUDENT_TYPE;
    const REFEREE_TYPE_TEACHER = UserQrTicketModel::TEACHER_TYPE;

}
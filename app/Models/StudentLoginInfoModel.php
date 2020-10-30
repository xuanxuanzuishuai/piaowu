<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;


class StudentLoginInfoModel extends Model{

    //学员体验期是否有效
    const IS_EXPERIENCE_TRUE = 1;
    const IS_EXPERIENCE_FALSE = 2;

    //学员类型
    const STUDENT_PAY_TYPE_EXPERIENCE = 1;

    public static $table = 'student_login_info';
}
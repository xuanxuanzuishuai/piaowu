<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/2/22
 * Time: 上午11:54
 */

namespace App\Models;


class FeedbackModel extends Model
{
    public static $table = 'feedback';

    const TYPE_STUDENT = 1;
    const TYPE_TEACHER = 2;
    const TYPE_EMPLOYEE = 3;

    const PLATFORM_ANDROID = 1;
    const PLATFORM_IOS = 2;
}
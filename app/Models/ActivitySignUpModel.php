<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/09/28
 * Time: 3:49 PM
 */

namespace App\Models;

class ActivitySignUpModel extends Model
{
    static $table = 'activity_sign_up';
    //状态
    const STATUS_DISABLE = 0;//0无效
    const STATUS_ABLE = 1;//1有效
}
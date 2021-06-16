<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


class AgentServiceEmployeeModel extends Model
{
    public static $table = "agent_service_employee";
    //状态：1正常 2删除
    const STATUS_OK = 1;
    const STATUS_DEL = 2;
}
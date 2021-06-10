<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/6/10
 * Time: 10:52
 */

namespace App\Models;


class AgentOrganizationModel extends Model
{
    public static $table = "agent_organization";
    //状态：1正常 2删除
    const STATUS_OK = 1;
    const STATUS_DELETE = 2;
}
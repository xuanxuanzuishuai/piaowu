<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/24
 * Time: 10:36
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class ScheduleExtendModel extends Model
{
    public static $table = "schedule_extend";

    /**
     * 写入一条报告
     * @param $data
     * @param bool $isOrg TODO $isOrg means what
     * @return int|mixed|null|string
     */
    public static function insertRecord($data, $isOrg=false)
    {
        $report = $data['report'];
        return parent::insertRecord($report, $isOrg);
    }
}
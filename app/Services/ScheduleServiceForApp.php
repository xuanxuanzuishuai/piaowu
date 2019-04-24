<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019-04-24
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\ScheduleModel;
use App\Models\ScheduleExtendModel;
use App\Models\ScheduleTaskUserModel;
use App\Models\ScheduleUserModel;

class ScheduleServiceForApp
{
    public static function endSchedule($schedule){
        // 处理课后单
        $reportOk = ScheduleExtendModel::insertRecord($schedule);
        if(!$reportOk){
            return 'insert_report_fail';
        }
        return null;
    }
}
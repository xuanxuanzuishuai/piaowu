<?php
/**
 * 临时脚本 - 只能执行一次
 * 生成10.18-10.31内的5次活动
 */

namespace App;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ActivityPosterModel;
use App\Models\EmployeeModel;
use App\Models\OperationActivityModel;
use App\Models\WeekActivityModel;
use App\Services\DictService;
use App\Services\WeekActivityService;
use App\Services\XYZOP1262Service;
use Dotenv\Dotenv;

// 1小时超时
set_time_limit(3600);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// 随便获取最新一期活动海报
$poster = ActivityPosterModel::getRecord(['ORDER' => ['id' => 'DESC']], ['poster_id'])['poster_id'];
if (empty($poster)) {
    echo 'error::get_poster_fail';
    exit;
}
$activityIds       = [];
$time              = time();
$activityStartTime = strtotime("2021-10-18 00:00:00");
$activityEndTime   = strtotime("2021-10-31 23:59:59");
for ($i = 1; $i <= count(XYZOP1262Service::WEEK_ACTIVITY_NAME); $i++) {
    // 写入 operation_activity 表 , week_activity 表
    try {
        $_tmpData    = [
            'name'        => XYZOP1262Service::WEEK_ACTIVITY_NAME[$i],
            'create_time' => $time,
            'poster'      => [$poster],
            'start_time'  => $activityStartTime,
            'end_time'    => $activityEndTime,
            'award_rule'  => '奖励规则',
            'personality_poster_button_img' => '',
            'poster_make_button_img' => '',
            'poster_order' => 2,
        ];
        $_activityId = WeekActivityService::add($_tmpData, EmployeeModel::SYSTEM_EMPLOYEE_ID);

        $activityIds[] = $_activityId;
    } catch (RunTimeException $e) {
        echo "error:create_activity_fail:" . $e->getMessage() . ", fail_activity_id:" . implode($activityIds) . PHP_EOL;
    }
}

// 更新dict
DictService::updateValue(DictConstants::XYZOP_1262_WEEK_ACTIVITY['type'], 'xyzop_1262_week_activity_ids', implode(',', $activityIds));
// 输出结果
echo "SUCCESS: activity_id:" . implode(',', $activityIds) . PHP_EOL;

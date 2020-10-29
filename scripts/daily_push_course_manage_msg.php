<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/10/29
 * Time: 14:53
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Models\LeadsModel;
use App\Services\LeadsPool\LeadsService;
use Dotenv\Dotenv;

/**
 * 每天中午十二点执行
 */
$dotEnv = new Dotenv(PROJECT_ROOT, '.env');
$dotEnv->load();
$dotEnv->overload();
SimpleLogger::info("push course manage msg start", []);
$studentList = LeadsModel::getPushCourseManageCache(time());
if (empty($studentList)) {
    SimpleLogger::info("data empty", []);
}
array_map(function ($sid) {
    LeadsService::allotLeadsCourseManageWxPush(['student_id' => $sid]);
}, $studentList);
SimpleLogger::info("push course manage msg end", []);
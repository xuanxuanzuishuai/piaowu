<?php
/**
 * 更新学生参加活动信息
 * author: qingfeng.lian
 * date: 2022/8/30
 */

namespace App;

use App\Services\SyncTableData\RealUpdateStudentCanJoinActivityService;
use Dotenv\Dotenv;

date_default_timezone_set('PRC');
// 不限时
set_time_limit(0);
// 设置内存
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

(new RealUpdateStudentCanJoinActivityService())->run();
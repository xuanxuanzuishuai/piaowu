<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\ReviewCourseTaskService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

echo "create review course task [START]\n";
echo "生成今日的点评任务\n";

$date = date('Ymd');
echo "review date: $date\n";

$count = ReviewCourseTaskService::createDailyTasks($date);

if ($count === false) {
    echo "error !!!\n";
}

echo "create review course task [END]\n";
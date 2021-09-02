<?php
/**
 * 启动真人小程序生成待使用小程序标识任务脚本
 * $data['app_id'] = Constants::REAL_APP_ID;
 * $data['busies_type'] = Constants::REAL_MINI_BUSI_TYPE;
 * 手动执行
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Services\MiniAppQrService;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

MiniAppQrService::startCreateMiniAppId(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE);

echo "SUCCESS" . PHP_EOL;
<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Services\HalloweenService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
/**
 * 设置万圣节里程排行榜数据:定时任务脚本每10分钟更新一次
 * *\/10 * * * * php flush_halloween_rank_cache.php
 */
SimpleLogger::info("start set halloween rank cache",[]);
HalloweenService::setHalloweenRankCache();
SimpleLogger::info("end set halloween rank cache",[]);
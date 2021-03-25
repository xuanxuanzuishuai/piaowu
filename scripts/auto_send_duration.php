<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/24
 * Time: 3:19 PM
 * 发放时长奖励
 * 每天18点运行
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$awardInfo = ErpUserEventTaskAwardModel::needSendDurationAward();

QueueService::sendDuration($awardInfo);

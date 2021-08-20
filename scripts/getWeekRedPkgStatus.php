<?php

/**
 * 获取周周领奖白名单红包发放状态
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\WhiteGrantRecordModel;
use App\Services\Queue\QueueService;
use App\Services\WhiteGrantRecordService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

(new obj())->run();

class obj{

    public function run(){
        $list = WhiteGrantRecordModel::getRecords(['status'=>WhiteGrantRecordModel::STATUS_GIVE], ['id', 'bill_no']);

        foreach ($list as $one){
            QueueService::getWeekWhiteSendRedPkgStatus($one);
        }
    }
}

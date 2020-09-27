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

use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Models\DictModel;
use App\Models\StudentModel;
use App\Services\DictService;
use App\Services\TermSprintService;
use Dotenv\Dotenv;


$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
if (time() >= strtotime('2020-09-29')) {
    return;
}
list($virtualNum, $spanNum, $maxNum) = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, ['virtual_partition_cash_num', 'span_add_partition_num', 'max_partition_num']);
$newNum = $virtualNum + $spanNum;
DictModel::updateValue(DictConstants::TERM_SPRINT_CONFIG['type'], 'virtual_partition_cash_num', $newNum >= $maxNum ? $maxNum : $newNum);

RedisDB::getConn()->del(['dict_list_' . DictConstants::TERM_SPRINT_CONFIG['type']]);


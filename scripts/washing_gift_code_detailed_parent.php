<?php
namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Libs\MysqlDB;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();
$giftCodeDetailedCount = $db->queryAll('select count(distinct apply_user) gift_count from gift_code_detailed');
//分批查询数据:每次查询1000条数据
$pageCount = 1000;
$forTimes = ceil($giftCodeDetailedCount[0]['gift_count'] / $pageCount);

for ($i = 1; $i <= $forTimes; $i++) {
    shell_exec('php washing_gift_code_detailed.php -p='.$i.' -c='.$pageCount);
}




<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\RedisDB;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\PackageExtModel;
use Dotenv\Dotenv;

use App\Libs\MysqlDB;


$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();
$redis = RedisDB::getConn();
$giftCodeCount = $db->count('gift_code', ['apply_user[!]' => null]);
//分批查询数据:每次查询1000条数据
$pageCount = 1000;
$forTimes = ceil($giftCodeCount / $pageCount);

//查询新的产品包
$packageV1Date = $redis->get('washing_package_v1_data');
if (empty($packageV1Date)) {
    $packageV1Date = ErpPackageV1Model::getPackageData();
    $packageV1Date = array_combine(array_column($packageV1Date, 'package_id'), $packageV1Date);
    $redis->set('washing_package_v1_data',json_encode($packageV1Date));
}

$packageDate = $redis->get('washing_package_data');
if (empty($packageDate)) {
    //查询老的产品包
    $packageDate = PackageExtModel::getPackageData();
    $packageDate = array_combine(array_column($packageDate, 'package_id'), $packageDate);
    $redis->set('washing_package_data', json_encode($packageDate));
}

for ($i = 1; $i <= $forTimes; $i++) {
     shell_exec('php calculation_gift_code_detailed.php -p='.$i.' -c='.$pageCount);
}



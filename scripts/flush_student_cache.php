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

use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use Dotenv\Dotenv;
use Medoo\Medoo;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();


$db = MysqlDB::getDB();
$redis = RedisDB::getConn();


$step = 2000;
$where = [
    'has_review_course' => 2,
];
$count = $db->count('student', $where);
echo "records: {$count} \n";
var_dump($count);

$pageCount = ceil($count / $step);
echo "pages: {$pageCount} \n";

$totalDel = 0;
for ($i = 0; $i < $pageCount; $i ++) {
    $where['LIMIT'] = [$i*$step, $step];
    $ret = $db->select('student', [
        'k' => Medoo::raw('concat(:key, <id>)', [':key' => 'dss_student_'])
    ], $where);
    $ret = array_column($ret, 'k');

    $delCount = $redis->del($ret);
    echo "del: {$delCount} \n";

    $totalDel += $delCount;
}

echo "total del: {$totalDel} \n";



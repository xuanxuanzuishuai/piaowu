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
use App\Models\StudentModel;
use App\Services\DictService;
use Dotenv\Dotenv;


$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

echo "[START]\n";


$options = getopt('m:');

print_r($options);

if (empty($options['m'])) {
    die("use -m 199xxxxxxxx,199xxxxxxxx\n");
}

$mobiles = explode(',', $options['m']);
$students = StudentModel::getRecords(['mobile' => $mobiles], ['id', 'mobile', 'uuid']);
if (empty($students)) {
    die("invalid mobile\n");
}

print_r($students);

$whiteList = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'pay_test_students');
$whiteList = explode(',', $whiteList);
$whiteList = array_slice($whiteList, 0, 5);

foreach ($students as $s) {
    $whiteList[] = $s['uuid'];
}

print_r($whiteList);
$whiteList = implode(',', $whiteList);

DictService::updateValue(DictConstants::APP_CONFIG_STUDENT['type'], 'pay_test_students', $whiteList);

echo "\n[END]\n";

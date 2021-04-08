<?php
/**
 * 同步 employee_activity 海报模板数据到poster表中，
 * 建立映射关系
 *
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\EmployeeActivityModel;
use App\Models\PosterModel;
use Dotenv\Dotenv;


$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

$templatePosterList = EmployeeActivityModel::getRecords(['status' => EmployeeActivityModel::STATUS_ENABLE]);
foreach ($templatePosterList as $item) {
    $posterList = json_decode($item['poster']);
    if (!empty($posterList)) {
        foreach ($posterList as $_p) {
            $posterId = PosterModel::getIdByPath(['path' =>$_p], ['name' => $item['name']]);
            if ($posterId <= 0) {
                var_dump("fail :: template_id".$item['id']);
                continue;
            }
        }
    }
}
echo 'employee_activity table sync success' . PHP_EOL;


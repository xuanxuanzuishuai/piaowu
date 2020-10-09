<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/9
 * Time: 4:07 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
require_once PROJECT_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Libs\AliOSS;
use App\Libs\SimpleLogger;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

//上传手机号模板文件到阿里oss
$tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/mobile_number.xlsx";
$savePath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_MESSAGE_EXCEL . '/mobile_number.xlsx';

if (file_exists($tmpFileFullPath)) {
    AliOSS::uploadFile($savePath, $tmpFileFullPath);
} else {
    SimpleLogger::error("待上传文件不存在，请检查", []);
}

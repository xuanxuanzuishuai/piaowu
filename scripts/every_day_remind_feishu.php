<?php
/**
 * 每个工作日提醒飞书更新文档
 */
namespace App;

set_time_limit(0);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\HttpHelper;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

HttpHelper::requestJson('https://open.feishu.cn/open-apis/bot/v2/hook/6ff321f3-7076-45c8-b324-2a2d382efbae',
[
    'msg_type' => 'text',
    'content' => [
        'text' => "https://dowbo10hxj.feishu.cn/docs/doccnb3fj0d4jVQ4PBHft64O8LP \n \n \n 大家填一填，第一个把整体的表格复制并更新日期"
    ]
], 'POST');
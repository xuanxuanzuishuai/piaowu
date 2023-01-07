<?php
namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Libs\WeChat\WeChatMiniPro;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$wechat = WeChatMiniPro::factory();

$menuList = [
    'button' => [
        [
            "type" => "view",
            "name" => "票据上传",
            "url"  => "http://ticket-wx.xyzops.com",
        ],
    ],
];
$res = $wechat->createMenu($menuList);
print_r($res);

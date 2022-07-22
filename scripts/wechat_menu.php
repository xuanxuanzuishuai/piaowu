<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/22
 * Time: 11：20
 */
/*
 * 微信自定义菜单
 */
namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\Constants;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);

$menuList = [
    'button' => [
        [
            'name' => '兑换时长',
            'sub_button' => [
                [
                    "type" => "view",
                    "name" => "🎁年卡活动",
                    "url"  => "https://referral.xiaoyezi.com/operation/activity/awards/index?awtype=week&show_activity=true"
                ],
                [
                    "type" => "view",
                    "name" => "🎁体验卡活动",
                    "url"  => "https://referral.xiaoyezi.com/operation/student/clock5Day/home"
                ],
                [
                    "type" => "view",
                    "name" => "🎉金叶子商城",
                    "url"  => "https://dss-weixin.xiongmaopeilian.com/Student/goldLeaf/index"
                ],
                [
                    "type" => "view",
                    "name" => "加入体验营",
                    "url"  => "https://referral.xiaoyezi.com/market/landingWechatTab?ad=0&channel_id=1720"
                ]
            ],
        ],
        [
            "type" => "view",
            "name" => "练琴日报",
            "url"  => "https://dss-weixin.xiongmaopeilian.com/student/calendar",
        ],
        [
            "name" => "我的账户",
            "sub_button" => [
                [
                    "type" => "view",
                    "name" => "我的账户",
                    "url" => "https://dss-weixin.xiongmaopeilian.com/student/myAccountNew"
                ],
                [
                    "type" => "view",
                    "name" => "学员故事",
                    "url" => "https://mp.weixin.qq.com/mp/homepage?__biz=MzU2MjMxNTc5Mw==&hid=2&sn=1884b772776ab74b77312fb4178bd5e1"
                ],
                [
                    "type" => "view",
                    "name" => "使用指南",
                    "url" => "https://mp.weixin.qq.com/mp/homepage?__biz=MzU2MjMxNTc5Mw==&hid=1&sn=2c284d2fb07e1cfde9ef5fe9543c62e6&scene=18"
                ],
                [
                    "type" => "view",
                    "name" => "下载APP",
                    "url" => "http://www.xiaoyezi.com/html/aipeilian.html"
                ],
                [
                    "type" => "view",
                    "name" => "联系客服",
                   // "url" => "https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=用户"
                    "url" => "https://xiaoyezi2.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=5&uname=用户"
                ]
            ]
        ],
    ],
];
// $res = $wechat->getCurrentMenu();
// echo json_encode($res);
$res = $wechat->createMenu($menuList);
print_r($res);

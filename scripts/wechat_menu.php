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
            'name' => '推荐有奖',
            'sub_button' => [
                [
                    "type" => "view",
                    "name" => "专属海报",
                    "url"  => "https://dss-weixin-pre.xiongmaopeilian.com/student/poster"
                ],
                [
                    "type" => "click",
                    "name" => "推荐好友",
                    "key"  => "STUDENT_PUSH_MSG_USER_SHARE"
                ],
                [
                    "type" => "view",
                    "name" => "分享返学费",
                    "url"  => "http://referral-pre.xiaoyezi.com/operation/student/clock5Day/home"
                ],
                [
                    "type" => "view",
                    "name" => "上传截图领奖",
                    "url"  => "https://dss-weixin-pre.xiongmaopeilian.com/student/referral?tag=1"
                ],
                [
                    "type" => "view",
                    "name" => "郎朗测评",
                    "url" => "http://mp.weixin.qq.com/s?__biz=MzU1NTkxNzY3MQ==&mid=100000231&idx=1&sn=d11c0cfa5a19418dab0c99c1d8b9b77a&chksm=7bcc49ef4cbbc0f9f923570f89f7389933ee84750cd1751f8be23084d4311f740aff1d9c8775&scene=18#rd"
                ],
            ],
        ],
        [
            "type" => "view",
            "name" => "马上体验",
            "url" => "https://referral-pre.xiaoyezi.com/market/landing?channel_id=1244"
        ],
        [
            "name" => "我的账户",
            "sub_button" => [
                [
                    "type" => "view",
                    "name" => "我的账户",
                    "url" => "https://dss-weixin-pre.xiongmaopeilian.com/student/myAccount"
                ],
                [
                    "type" => "view",
                    "name" => "练琴日报",
                    "url" => "https://dss-weixin-pre.xiongmaopeilian.com/student/calendar"
                ],
                [
                    "type" => "view",
                    "name" => "使用指南",
                    "url" => "http://mp.weixin.qq.com/s?__biz=MzU1NTkxNzY3MQ==&mid=100000105&idx=1&sn=a4f83bf34750e86f3902241f8560ad85&chksm=7bcc49614cbbc0770b398c4b0711b2515efde07bfe866db7ba17e930176e6c2c26df66e1f459&scene=18#wechat_redirect"
                ],
                [
                    "type" => "view",
                    "name" => "下载App",
                    "url" => "http://www.xiaoyezi.com/html/aipeilian.html"
                ],
                [
                    "type" => "view",
                    "name" => "联系客服",
                    "url" => "https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=用户"
                ]
            ]
        ],
    ],
];
// $res = $wechat->getCurrentMenu();
// echo json_encode($res);
// $res = $wechat->createMenu($menuList);
// print_r($res);

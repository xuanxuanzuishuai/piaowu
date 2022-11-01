<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
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

use App\Libs\Constants;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\WechatOpenidListModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

//同步已经关注公众号的用户列表
function getSubscribeList($openid)
{
    $time = time();
    $openidListData = WeChatMiniPro::factory(Constants::QC_APP_ID, Constants::QC_APP_BUSI_WX_ID)->getSubscribeList($openid);
    if (!empty($openidListData['data']['openid'])) {
        foreach ($openidListData['data']['openid'] as $openId) {
            $wechatOpenInfo = WechatOpenidListModel::getRecord(['openid' => $openId]);
            if (empty($wechatOpenInfo)) {
                WeChatOpenIdListModel::insertRecord(
                    [
                        'openid'              => $openId,
                        'appid'               => Constants::QC_APP_ID,
                        'status'              => WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT,
                        'last_subscribe_time' => $time,
                        'create_time'         => $time,
                        'update_time'         => $time,
                    ]
                );
            } else {
                if ($wechatOpenInfo['status'] != WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT) {
                    WeChatOpenIdListModel::updateRecord($wechatOpenInfo['id'], [
                        'status'              => WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT,
                        'last_subscribe_time' => $time,
                        'update_time'         => $time,
                    ]);
                }
            }
            echo $openId . ' insert success' . PHP_EOL;
        }
    }
    if (!empty($openidListData['next_openid'])) {
        getSubscribeList($openidListData['next_openid']);
    }
    return;
}

getSubscribeList('');





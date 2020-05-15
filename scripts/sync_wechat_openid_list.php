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

use App\Libs\UserCenter;
use App\Models\UserWeixinModel;
use App\Models\WeChatOpenIdListModel;
use App\Services\WeChatService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
//同步已经关注公众号的用户列表
function getSubscribeList($appId, $userType, $busiType, $openid)
{
    $openidListData = WeChatService::getSubscribeList($appId, $userType, $openid);
    if (!empty($openidListData['data']['openid'])) {
        foreach ($openidListData['data']['openid'] as $openId) {
            $wechatOpenInfo = WeChatOpenIdListModel::getRecord(['openid' => $openId]);
            if (empty($wechatOpenInfo)) {
                WeChatOpenIdListModel::insertRecord(
                    [
                        'openid' => $openId,
                        'appid' => $appId,
                        'user_type' => $userType,
                        'busi_type' => $busiType,
                        'status' => WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT
                    ]
                );
            } else {
                if ($wechatOpenInfo['status'] != WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT) {
                    WeChatOpenIdListModel::updateRecord($wechatOpenInfo['id'], ['status' => WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT]);
                }
            }
        }
    }
    if (!empty($openidListData['next_openid'])) {
        getSubscribeList($appId, $userType, $busiType, $openidListData['next_openid']);
    }
    return;
}
getSubscribeList(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, UserWeixinModel::BUSI_TYPE_STUDENT_SERVER, '');





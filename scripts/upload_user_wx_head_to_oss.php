<?php
/**
 * 上传用户微信头像到oss
 * User: yuxuan
 * Date: 2020/1/7
 * Time: 3:19 PM
 * 执行时间，每天凌晨12:05
 */

namespace App;
set_time_limit(0);
date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\AliOSS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\UserWeiXinInfoModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
while (true) {
    $redis = RedisDB::getConn();
    $redisHashKey = UserWeiXinInfoModel::REDIS_HASH_USER_WEIXIN_INFO_PREFIX . date("Y-m-d", strtotime("-1 day"));
    $redisData = $redis->hgetall($redisHashKey);
    if (empty($redisData)) {
        SimpleLogger::info('upload user wx head to oss is openid empty exec end', []);
        break;
    }
    foreach ($redisData as $key => $val) {
        $wxInfo = json_decode($val, true);
        $appInfo = explode('_', $key);
        $appid = $appInfo[0];
        $busi_type = $appInfo[1];

        //oss 上传到本地
        $localPath = AliOSS::saveTmpImgFile($wxInfo['headimgurl']);
        if (!$localPath) {
            SimpleLogger::info('upload user wx head to oss save tmp img file error', ['wx_head' => $wxInfo]);
            continue;
        }
        //文件规则 -  小程序的id + openid 可以保证同一个小程序下这个用户的头像是唯一的不会造成oss资源浪费
        $fileName = $appid . '_' . $busi_type . '_' . $wxInfo['openid'];
        $ossFile = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName . '.jpg';
        if (!file_exists($localPath)) {
            SimpleLogger::info('upload user wx head to oss localFile is not find', ["local_file" => $localPath]);
            continue;
        }
        //删除文件到oss
        AliOSS::uploadFile($ossFile, $localPath);
        // 删除临时文件
        unlink($localPath);

        $where = ['app_id' => $appid, 'busi_type' => $busi_type, 'open_id' => $wxInfo['openid']];
        $dbInfo = UserWeiXinInfoModel::getRecord($where, ['id']);
        if (!$dbInfo) {
            //更新数据表
            $updateData = [
                'nickname' => Util::textEncode($wxInfo['nickname']),
                'head_url' => $ossFile
            ];
            UserWeiXinInfoModel::updateWxInfo($where, $updateData);
        } else {
            $insertData = [
                'app_id' => $appid,
                'busi_type' => $busi_type,
                'open_id' => $wxInfo['openid'],
                'nickname' => Util::textEncode($wxInfo['nickname']),
                'head_url' => $ossFile,
                'create_time' => time(),
            ];
            UserWeiXinInfoModel::insertRecord($insertData);
        }
    }
    break;
}
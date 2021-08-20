<?php

/**
 * 获取周周领奖白名单红包发放状态
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\WeChatPackage;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WhiteGrantRecordModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

(new obj())->run();

class obj{

    public function run(){

        $now = time();
        $list = WhiteGrantRecordModel::getRecords(['status'=>WhiteGrantRecordModel::STATUS_GIVE], ['id', 'bill_no']);
        $weChatPackage = new WeChatPackage(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER, WeChatPackage::WEEK_FROM);
        foreach ($list as $one){

            $resultData = $weChatPackage->getRedPackBillInfo($one['bill_no']);

            SimpleLogger::info("wx red pack query", ['mch_billno' => $one['bill_no'], 'data' => $resultData]);

            if ($resultData['result_code'] == WeChatAwardCashDealModel::RESULT_FAIL_CODE) {
                $resultCode = $resultData['err_code'];
                $status     = WhiteGrantRecordModel::STATUS_GIVE_FAIL;
                $reason     = WeChatAwardCashDealModel::getWeChatErrorMsg($resultData['err_code']);
            } else {
                //已领取
                if (in_array($resultData['status'], [WeChatAwardCashDealModel::RECEIVED])) {
                    $status = WhiteGrantRecordModel::STATUS_GIVE_NOT_SUCC;
                    $resultCode = $resultData['status'];
                }

                //发放失败/退款中/已退款
                if (in_array($resultData['status'], [WeChatAwardCashDealModel::REFUND, WeChatAwardCashDealModel::RFUND_ING, WeChatAwardCashDealModel::FAILED])) {
                    $status = WhiteGrantRecordModel::STATUS_GIVE_FAIL;
                    $resultCode = $resultData['status'];
                }

                //发放中/已发放待领取
                if (in_array($resultData['status'], [WeChatAwardCashDealModel::SENDING, WeChatAwardCashDealModel::SENT])) {
                    $status = WhiteGrantRecordModel::STATUS_GIVE;
                    $resultCode = $resultData['status'];
                }
            }

            $data = [
                'update_time' => $now,
                'reason'      => $reason,
                'result_code' => $resultCode,
                'status'      => $status,
            ];

            WhiteGrantRecordModel::updateRecord($one['id'], $data);

        }



    }
}

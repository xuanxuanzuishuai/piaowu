<?php
/**
 * 清晨-5日打卡发放红包的领取状态
 * 每隔几分钟执行一次，预计5分钟执行一次
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
use App\Libs\Util;
use App\Models\MorningTaskAwardModel;
use App\Models\MorningWechatAwardCashDealModel;
use App\Services\CashGrantService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptMorningClockActivityUpdateRedPackStatus
{

    /**
     * 初始化参数
     */
    public function __construct()
    {
    }

    public function run()
    {
        // 记录日志
        SimpleLogger::info("ScriptMorningClockActivityUpdateRedPackStatus_start", []);
        // 获取十天内有更新的发放中的红包记录
        $recordList = MorningWechatAwardCashDealModel::getStatusIsGiveingRedPack(Util::TIMESTAMP_ONEDAY * 10);
        if (empty($recordList)) {
            return true;
        }
        // 请求微信获取发放结果
        $now = time();
        foreach ($recordList as $item) {
            $wechatData = CashGrantService::getCashDealStatus(Constants::QC_APP_ID, Constants::QC_APP_BUSI_WX_ID, $item['mch_billno']);
            if (empty($wechatData)) {
                continue;
            }
            if ($item['status'] == $wechatData['status']) {
                continue;
            }
            //更新当前记录
            $updateRow = MorningWechatAwardCashDealModel::updateRecord($item['id'], ['status' => $wechatData['status'], 'result_code' => $wechatData['result_code'], 'update_time' => $now]);
            SimpleLogger::info('ScriptMorningClockActivityUpdateRedPackStatus we chat award update row', ['affectedRow' => $updateRow]);
            // 更新发放结果
            $res = MorningTaskAwardModel::updateRecord($item['task_award_id'], [
                'status'      => $wechatData['status'],
                'reason'      => $wechatData['result_code'],
                'update_time' => $now,
            ]);
            SimpleLogger::info('ScriptMorningClockActivityUpdateRedPackStatus upload task award status', [$res, $item['task_award_id'], $wechatData]);
        }
        return true;

    }
}

(new ScriptMorningClockActivityUpdateRedPackStatus())->run();
<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Models\AIReferralToPandaUserModel;
use App\Services\AIReferralToPandaUserService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$date = date('Ymd');
SimpleLogger::info('send ai referral to panda notice [START]', ['send_date' => $date,]);

list($successIds, $failedIds) = AIReferralToPandaUserService::sendAiReferralToPandaNotice($date);
if (!empty($successIds)) {
    AIReferralToPandaUserService::modifyStatus($successIds, AIReferralToPandaUserModel::USER_SEND_SUCCESS);
}
if (!empty($failedIds)) {
    AIReferralToPandaUserService::modifyStatus($failedIds, AIReferralToPandaUserModel::USER_SEND_FAILED);
}
SimpleLogger::info('send ai referral to panda notice [END]', ['send_date' => $date, 'successIds' => $successIds, 'failedIds' => $failedIds]);

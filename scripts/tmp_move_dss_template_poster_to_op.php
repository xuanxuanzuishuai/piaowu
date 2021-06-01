<?php
/**
 * 迁移dss 个性化海报和标准海报到op
 * dss.template_poster => op.template_poster
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Dss\DssReferralActivityModel;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpEventModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\TemplatePosterModel;
use App\Models\WeekActivityModel;
use App\Services\PosterService;
use App\Services\WeekActivityService;
use Dotenv\Dotenv;
use Exception;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$templatePosterData = [];
$failInfo = [];
$templatePosterList = DssTemplatePosterModel::getRecords([]);
if (empty($templatePosterList)) {
    echo "not found data";
    exit;
}
foreach ($templatePosterList as $poster) {
    try {
        // 海报地址换图片库id
        $posterPathId = PosterService::getIdByPath($poster['poster_url'], ['name' => $poster['poster_name']]);
        $examplePathId = PosterService::getIdByPath($poster['example_url'], ['name' => $poster['poster_name']]);

        // 写入海报库
        $templatePosterData[] = [
            'name' => $poster['poster_name'],
            'poster_id' => $posterPathId,
            'poster_path' => $poster['poster_url'],
            'example_id' => $examplePathId,
            'example_path' => $poster['example_url'],
            'status' => $poster['poster_status'],
            'order_num' => $poster['order_num'],
            'type' => $poster['type'],
            'operate_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'create_time' => $poster['create_time'],
            'update_time' => $poster['update_time'],
        ];
    } catch (Exception $e) {
        $failInfo['fail_data'][] = [
            'id' => $poster['id'],
            'err' => $e->getMessage(),
        ];
    }
}
if (!empty($templatePosterData)) {
    TemplatePosterModel::batchInsert($templatePosterData);
}

if (empty($failInfo)) {
    echo "SUCCESS; total num" . count($templatePosterData) . "\n";
} else {
    echo "FAIL";
    var_dump($failInfo);
}

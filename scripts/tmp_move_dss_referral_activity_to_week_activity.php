<?php
/**
 * 迁移dss转介绍活动信息到op
 * dss.referral_activity => op.operation_activity and op.week_activity
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Models\Dss\DssReferralActivityModel;
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

$failInfo = [];
// 获取dss
$referralActivityList = DssReferralActivityModel::getRecords(['ORDER' => ['id' => 'ASC']]);
// 获取上传截图领奖的任务id
$eventInfo= ErpEventModel::getRecord(['type' => ErpEventModel::DAILY_UPLOAD_POSTER], ['id']);
foreach ($referralActivityList as $referral) {
    try {
        // 周周有奖活动状态与活动当前状态保持一致
        if ($referral['status'] == 1) {
            $weekStatus = OperationActivityModel::ENABLE_STATUS_ON;
        } elseif ($referral['status'] == 2) {
            $weekStatus = OperationActivityModel::ENABLE_STATUS_DISABLE;
        } else {
            $weekStatus = OperationActivityModel::ENABLE_STATUS_OFF;
        }
        // 写入图片库 poster表
        $posterId = PosterService::getIdByPath($referral['poster_url'], ['name' => $referral['name']]);
        if (empty($posterId)) {
            throw new Exception('insert poster');
        }
        // 写入海报库 template_poster
        $templatePosterId = TemplatePosterModel::insertRecord([
            'name' => $referral['name'],
            'poster_id' => $posterId,
            'poster_path' => $referral['poster_url'],
            'status' => TemplatePosterModel::NORMAL_STATUS,     // 海报状态，为上线状态
            'type' => TemplatePosterModel::STANDARD_POSTER,
            'operate_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'create_time' => $referral['create_time'],
            'update_time' => $referral['update_time'],
        ]);
        if (empty($templatePosterId)) {
            throw new Exception('insert template_poster');
        }
        // 写入周周有奖表和活动总表
        $activityId = WeekActivityService::add([
            'name' => $referral['name'],
            'event_id' => $eventInfo['id'],
            'guide_word' => $referral['guide_word'],
            'share_word' => $referral['share_word'],
            'start_time' => date("Y-n-d H:i:s", $referral['start_time']),
            'end_time' => date("Y-m-d H:i:s", $referral['end_time']),
            'enable_status' => $weekStatus, // '活动状态 0:未启用, 1:启用 2:已禁用'  '活动启用状态 1未启用，2启用',
            'banner' => '',
            'share_button_img' => '',
            'award_detail_img' => '',
            'upload_button_img' => '',
            'strategy_img' => '',
            'create_time' => $referral['create_time'],
            'update_time' => $referral['update_time'],
            'operator_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'award_rule' => '稍后更新',
            'remark' => $referral['remark'],
            'poster' => [$templatePosterId],
        ], EmployeeModel::SYSTEM_EMPLOYEE_ID);

        // 更新周周有奖参与记录对应的活动id
        $updateSharePosterRes = SharePosterModel::batchUpdateRecord(['activity_id' => $activityId], ['activity_id' => $referral['id'], 'type' => SharePosterModel::TYPE_WEEK_UPLOAD]);
        if (is_null($updateSharePosterRes)) {
            throw new Exception('update_share_poster; activity_id=' . $activityId);
        }

        // 更新周周有奖活动启用状态和活动原本的最后更新时间
        $updateRes = WeekActivityModel::batchUpdateRecord(['enable_status' => $weekStatus, 'update_time' => $referral['update_time']], ['activity_id' => $activityId]);
        if (is_null($updateRes)) {
            throw new Exception('update_week_status; activity_id=' . $activityId);
        }
    } catch (Exception $e) {
        $failInfo['fail_data'][] = [
            'id' => $referral['id'],
            'err' => $e->getMessage(),
        ];
    }
}

if (empty($failInfo)) {
    echo "SUCCESS";
} else {
    echo "FAIL";
    var_dump($failInfo);
}

<?php

/**
 * 限时领奖活动消息推送
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
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Models\WhiteGrantRecordModel;
use App\Services\Activity\LimitTimeActivity\TraitService\LimitTimeActivityBaseAbstract;
use App\Services\Queue\QueueService;
use App\Services\WhiteGrantRecordService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptLimitTimeActivityPush
{
    const APP_LIST          = [
        Constants::SMART_APP_ID,
        Constants::REAL_APP_ID,
    ];
    const IS_FIRST_DAY_PUSH = 'first_day';
    const IS_LAST_DAY_PUSH  = 'last_day';
    private $isCurrentPush   = '';
    private $time            = 0;
    private $last_student_id = [];
    const LogTitle = 'ScriptLimitTimeActivityPush';

    public function __construct($params)
    {
        if (empty($params['1'])) {
            SimpleLogger::info(self::LogTitle . ' params error.', $params);
            return false;
        }
        if ($params['1'] == self::IS_FIRST_DAY_PUSH) {
            $this->isCurrentPush = self::IS_FIRST_DAY_PUSH;
        } else {
            $this->isCurrentPush = self::IS_LAST_DAY_PUSH;
        }
        return true;
    }

    /**
     * @param $msg
     * @param array $data
     * @param $errLevel
     * @return void
     */
    private function saveLog($msg, array $data = [], $errLevel = 'info')
    {
        SimpleLogger::info(self::LogTitle . ' step msg:' . $msg, $data);
    }

    private function checkParams()
    {
        if (empty($this->isCurrentPush)) {
            return false;
        }
        if (in_array($this->isCurrentPush, [self::IS_LAST_DAY_PUSH, self::IS_FIRST_DAY_PUSH])) {
            return false;
        }
        return true;
    }

    public function run()
    {
        $this->saveLog("start");
        if (!$this->checkParams()) {
            return false;
        }
        $this->time = time();
        // 获取可推送活动消息列表
        $activityList = $this->getActivityList();
        $this->saveLog("activity list", $activityList);
        if (empty($activityList)) {
            return true;
        }
        $studentPushActivity = [];
        foreach ($activityList as $_activity) {
            $studentPushActivity[$_activity['app_id']][] = $_activity;
        }

        foreach ($studentPushActivity as $key => $item) {
            $this->pushData($key, $item);
        }
        unset($key, $item);
        return true;
    }

    // 获取可参与活动列表
    public function getActivityList()
    {
        $where = [
            'app_id'        => self::APP_LIST,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        if ($this->isCurrentPush == self::IS_LAST_DAY_PUSH) {
            $where['start_time[>=]'] = strtotime("Y-m-d 00:00:00");
            $where['start_time[<=]'] = strtotime("Y-m-d 23:59:59");
        } else {
            $where['end_time[>=]'] = strtotime("Y-m-d 00:00:00");
            $where['end_time[<=]'] = strtotime("Y-m-d 23:59:59");
        }
        return LimitTimeActivityModel::getRecords($where);
    }

    public function getStudentIds($appId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
            $dssTable = DssStudentModel::getTableNameWithDb();
            $studentIds = $db->get(
                $dssTable . '(s)',
                [
                    '[><]' . DssUserWeiXinModel::getTableNameWithDb() . '(wx)' => ['wx.user_id' => 's.id'],
                ],
                [
                    's.id',
                ],
                [
                    's.id[>]'             => $this->last_student_id[$appId] ?? 0,
                    's.has_review_course' => DssStudentModel::REVIEW_COURSE_1980,
                    'wx.app_id'           => $appId,
                    'wx.status'           => DssUserWeiXinModel::STATUS_NORMAL,
                    'ORDER'               => ['s.id' => 'ASC'],
                    'LIMIT'               => [0, 2000],
                ]
            );
        } elseif ($appId == Constants::REAL_APP_ID) {
            $db = MysqlDB::getDB(MysqlDB::CONFIG_ERP_SLAVE);
        }
        return $studentIds ?? [];
    }

    public function pushData($appId, $activityList)
    {
        $studentIds = $this->getStudentIds($appId);
    }
}

(new ScriptLimitTimeActivityPush($argv))->run();
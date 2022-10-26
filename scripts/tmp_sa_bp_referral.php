<?php

/**
 * 推送到神策 - 转介绍人数 - 清洗数据的脚本
 * 是属性（更新记录）
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
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\MorningReferralStatisticsModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\Queue\QueueService;
use App\Services\Queue\SaBpDataTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptTmpSaBpReferral
{
    private $appId          = null;
    private $limit          = 1000;
    private $time           = 0;
    private $lastIdCatchKey = '';

    /**
     * 初始化参数
     * @param $params
     */
    public function __construct($params)
    {
        $this->appId = $params[1] ?? null;
        $this->time = time();
        $this->lastIdCatchKey = 'script_tmp_sa_bp_referral_last_id_' . $this->appId;
        return true;
    }

    private function checkParams()
    {
        if (empty($this->appId)) {
            return false;
        }
        return true;
    }

    public function run()
    {
        SimpleLogger::info("ScriptTmpSaBpReferral start", []);
        if (!$this->checkParams()) {
            return false;
        }
        do {
            // 获取转介绍列表
            $studentList = $this->getStudentList();
            if (!empty($studentList)) {
                // 投递
                QueueService::sendLeadsData($this->appId, $studentList, SaBpDataTopic::CLEAN_NSQ);
                sleep(1);
            } else {
                echo 'student list is empty' . PHP_EOL;
            }
        } while (!empty($studentList));


        return true;
    }


    public function getLastId()
    {
        $id = RedisDB::getConn()->get($this->lastIdCatchKey);
        return intval($id);
    }

    public function setLastId($lastId)
    {
        return RedisDB::getConn()->set($this->lastIdCatchKey, $lastId, 'EX', Util::TIMESTAMP_ONEWEEK);
    }

    public function getStudentList()
    {
        if ($this->appId == Constants::SMART_APP_ID) {
            $refIdList = StudentReferralStudentStatisticsModel::getRecords([
                'referee_id[>]' => $this->getLastId(),
                'GROUP'         => 'referee_id',
                'ORDER'         => ['referee_id' => 'ASC'],
                'LIMIT'         => [0, $this->limit],
            ], ['referee_id']);
            if (!empty($refIdList)) {
                $refIdList = array_column($refIdList, 'referee_id');
                $this->setLastId(intval(max($refIdList)));
                $data = StudentReferralStudentStatisticsModel::getReferralCount($refIdList);
            }
        } elseif ($this->appId == Constants::REAL_APP_ID) {
            $refIdList = ErpReferralUserRefereeModel::getRecords([
                'referee_id[>]' => $this->getLastId(),
                'GROUP'         => 'referee_id',
                'ORDER'         => ['referee_id' => 'ASC'],
                'LIMIT'         => [0, $this->limit],
            ], ['referee_id']);
            if (!empty($refIdList)) {
                $refIdList = array_column($refIdList, 'referee_id');
                $this->setLastId(intval(max($refIdList)));
                $data = ErpReferralUserRefereeModel::getReferralCountList($refIdList);
            }
        } elseif ($this->appId == Constants::QC_APP_ID) {
            $refIdList = MorningReferralStatisticsModel::getRecords([
                'id[>]' => $this->getLastId(),
                'ORDER' => ['id' => 'ASC'],
                'LIMIT' => [0, $this->limit],
            ], ['referee_student_uuid', 'id']);
            if (!empty($refIdList)) {
                $idList = array_column($refIdList, 'id');
                $this->setLastId(intval(max($idList)));
                $data = MorningReferralStatisticsModel::getReferralCountList(array_column($refIdList, 'referee_student_uuid'));
            }
        }
        return $data ?? [];
    }
}

(new ScriptTmpSaBpReferral($argv))->run();
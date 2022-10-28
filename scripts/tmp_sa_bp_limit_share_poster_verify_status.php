<?php

/**
 * 推送到神策 - 限时活动审核通过情况 - 同步数据的脚本
 * 是事件（每次投递新增记录， 不是覆盖）
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
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\LimitTimeActivity\LimitTimeActivitySharePosterModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\SharePosterModel;
use App\Services\Queue\QueueService;
use App\Services\Queue\SaBpDataTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptTmpSaBpLimitSharePosterVerifyStatus
{
    private $appId          = null;
    private $limit          = 500;
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
        $this->lastIdCatchKey = 'script_tmp_sa_bp_limit_share_poster_verify_status_last_id_' . $this->appId;
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
        SimpleLogger::info("ScriptTmpSaBpLimitSharePosterVerifyStatus start", []);
        if (!$this->checkParams()) {
            return false;
        }
        do {
            // 获取审核通过记录
            $studentList = $this->getStudentList();
            if (!empty($studentList)) {
                // 投递
                QueueService::sendSharePosterVerifyStatusData($this->appId, $studentList, SaBpDataTopic::CLEAN_NSQ);
                echo 'send ' . count($studentList) . ' success; and lastId:' . $this->getLastId() . PHP_EOL;
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
        $shareTable = LimitTimeActivitySharePosterModel::$table;
        $actTable = OperationActivityModel::$table;
        $sql = 'select sp.id,sp.student_uuid,sp.activity_id, oa.name activity_name,sp.verify_time' .
            ' from ' . $shareTable . ' as sp' .
            ' left join ' . $actTable . ' oa on sp.activity_id=oa.id' .
            ' where sp.app_id=' . $this->appId . ' and sp.verify_status=2 and sp.id>' . $this->getLastId() . ' order by sp.id asc limit 0,' . $this->limit;
        $data = MysqlDB::getDB()->queryAll($sql);
        if (!empty($data)) {
            $idsList = array_column($data, 'id');
            $this->setLastId(intval(max($idsList)));
        }
        return $data ?? [];
    }
}

(new ScriptTmpSaBpLimitSharePosterVerifyStatus($argv))->run();

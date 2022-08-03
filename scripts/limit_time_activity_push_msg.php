<?php

/**
 * 限时领奖活动消息推送
 * 15分钟执行一次，每次读取出指定数量，然后记录偏移量
 * 当读到的数据为空时停止执行
 * 每次处理数量 5000
 * 预计处理量 500000
 * 预计耗时  500000/(15*5000*4)
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
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\Erp\ErpReferralWeixinOpenidModel;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentAttributeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserWeiXinModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Services\Queue\Activity\LimitTimeAward\LimitTimeAwardConsumerService;
use App\Services\Queue\Activity\LimitTimeAward\LimitTimeAwardProducerService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptLimitTimeActivityPush
{
    const APP_LIST = [
        Constants::SMART_APP_ID,
        Constants::REAL_APP_ID,
    ];
    private $isCurrentPush   = '';
    private $time            = 0;
    private $secondSendNum   = 0;
    private $last_student_id = [];
    const LogTitle = 'ScriptLimitTimeActivityPush';
    private $limit = 0;

    /**
     * 初始化参数
     * @param $params
     */
    public function __construct($params)
    {
        if (empty($params[1])) {
            SimpleLogger::info(self::LogTitle . ' params error.', $params);
            return false;
        }
        if ($params[1] == LimitTimeAwardConsumerService::IS_FIRST_DAY_PUSH) {
            $this->isCurrentPush = LimitTimeAwardConsumerService::IS_FIRST_DAY_PUSH;
        } else {
            $this->isCurrentPush = LimitTimeAwardConsumerService::IS_LAST_DAY_PUSH;
        }
        $this->limit = DictConstants::get(DictConstants::LIMIT_TIME_ACTIVITY_CONFIG, 'push_wx_msg_every_time_limit');
        $this->secondSendNum = $this->limit / (15 * 60);
        return true;
    }

    /**
     * 保存日志
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
        if (!in_array($this->isCurrentPush, [LimitTimeAwardConsumerService::IS_LAST_DAY_PUSH, LimitTimeAwardConsumerService::IS_FIRST_DAY_PUSH])) {
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
        // 按照业务线区分活动
        $studentPushActivity = [];
        foreach ($activityList as $_activity) {
            $studentPushActivity[$_activity['app_id']][] = $_activity;
        }
        // 推送活动
        foreach ($studentPushActivity as $key => $item) {
            $this->pushData($key, $item);
        }
        unset($key, $item);
        return true;
    }

    /**
     * 获取可参与活动列表
     * @return array
     */
    public function getActivityList()
    {
        $where = [
            'app_id'        => self::APP_LIST,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        list($startTime, $endTime) = Util::getStartEndTimestamp($this->time);
        if ($this->isCurrentPush == LimitTimeAwardConsumerService::IS_FIRST_DAY_PUSH) {
            $where['start_time[>=]'] = $startTime;
            $where['start_time[<=]'] = $endTime;
        } else {
            $where['end_time[>=]'] = $startTime;
            $where['end_time[<=]'] = $endTime;
        }
        return LimitTimeActivityModel::getRecords($where);
    }

    public function getLastId($appId)
    {
        $id = RedisDB::getConn()->get($this->getKey($appId));
        return intval($id);
    }

    public function setLastId($appId, $lastId)
    {
        return RedisDB::getConn()->set($this->getKey($appId), $lastId, 'EX', Util::TIMESTAMP_ONEWEEK);
    }

    public function getKey($appId)
    {
        $day = date("Ymd");
        return 'limit_time_activity_push_last_id_' . $appId . '_' . $this->isCurrentPush . '_' . $day;
    }

    public function getStudentIds($appId)
    {
        $lastId = $this->getLastId($appId);
        if ($appId == Constants::SMART_APP_ID) {
            $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
            $dssTable = DssStudentModel::getTableNameWithDb();
            $wxTable = DssUserWeiXinModel::getTableNameWithDb();
            $subTable = DssWechatOpenIdListModel::getTableNameWithDb();
            $sql = 'SELECT s.id as student_id,s.country_code FROM ' .
                ' ' . $dssTable . ' as s' .
                ' INNER JOIN ' . $wxTable . ' as wx on wx.user_id=s.id' .
                ' INNER JOIN ' . $subTable . ' as ol on ol.openid=wx.open_id' .
                ' WHERE s.id >' . $lastId .
                ' AND s.has_review_course=' . DssStudentModel::REVIEW_COURSE_1980 .
                ' AND wx.app_id=' . $appId .
                ' AND wx.status=' . DssUserWeiXinModel::STATUS_NORMAL .
                ' AND ol.status=' . DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT;
        } elseif ($appId == Constants::REAL_APP_ID) {
            $db = MysqlDB::getDB(MysqlDB::CONFIG_ERP_SLAVE);
            $studentTable = ErpStudentModel::getTableNameWithDb();
            $studentAppTable = ErpStudentAppModel::getTableNameWithDb();
            $wxTable = ErpUserWeiXinModel::getTableNameWithDb();
            $studentAttrTable = ErpStudentAttributeModel::getTableNameWithDb();
            $subTable = ErpReferralWeixinOpenidModel::getTableNameWithDb();
            $attributeDict = DictConstants::get(DictConstants::LIMIT_TIME_ACTIVITY_CONFIG, 'real_student_is_normal_attr_id');
            $sql = 'SELECT s.id as student_id,s.country_code,s.uuid as student_uuid,max(sattr.attribute_id) as student_attribute  FROM ' .
                ' ' . $studentTable . ' as s' .
                ' INNER JOIN ' . $studentAppTable . ' as sapp on sapp.student_id=s.id' .
                ' INNER JOIN ' . $wxTable . ' as wx on wx.user_id=s.id' .
                ' INNER JOIN ' . $studentAttrTable . ' as sattr on sattr.user_uuid=s.uuid' .
                ' INNER JOIN ' . $subTable . ' as wo on wo.open_id=wx.open_id' .
                ' WHERE sapp.app_id=' . $appId .
                ' AND s.id >' . $lastId .
                ' AND wx.app_id=' . $appId .
                ' AND wx.status=' . ErpUserWeiXinModel::STATUS_NORMAL .
                ' AND sattr.attribute_id in (' . $attributeDict . ')' .
                ' AND wo.status=' . ErpReferralWeixinOpenidModel::SUBSCRIBE_WE_CHAT .
                ' GROUP BY s.id';
        } else {
            $this->saveLog("get student where app id error", [$appId]);
            return [];
        }

        $sql .= ' ORDER BY s.id ASC  LIMIT 0,' . $this->limit;
        $studentIds = $db->queryAll($sql);
        return $studentIds ?? [];
    }

    public function pushData($appId, $activityList)
    {
        $studentIds = $this->getStudentIds($appId);
        if (empty($studentIds)) {
            // 查不到学生退出
            self::saveLog("student empty is app id : " . $appId);
            return true;
        }
        // 重新设定延迟时间
        $this->setLastId($appId, end($studentIds)['student_id']);
        $sendNum = 0;
        foreach ($studentIds as $item) {
            $sendNum += 1;
            $pushMsg = [
                'student_info'  => $item,
                'activity_list' => $activityList,
                'push_type'     => $this->isCurrentPush,
                'app_id'        => $appId,
                'def_time'      => $this->computeDefTime($sendNum)
            ];
            LimitTimeAwardProducerService::pushWxMsgProducer($pushMsg);
        }
        unset($item);
        return true;
    }

    public function computeDefTime($sendNum)
    {
        return intval($sendNum / $this->secondSendNum);
    }
}

(new ScriptLimitTimeActivityPush($argv))->run();
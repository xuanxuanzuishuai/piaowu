<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/29
 * Time: 15:38
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\BillMapModel;
use App\Models\Dss\DssStudentModel;
use App\Services\Queue\SaBpDataTopic;
use Dotenv\Dotenv;
use Exception;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();

/**
 * 批量清洗学生注册&首次购买体验课渠道数据，上报神策
 * 数据量过大，分批次执行
 * **/
$washObj = new wash();
$startTime = time();
$washObj->registerDeal();

//$washObj->buyTrailDeal();
$endTime = time();
SimpleLogger::info("register exec time", [$endTime - $startTime]);

class wash
{
	public $mysqlDb;
	public $topic;
	public $i;
	public $studentList;
	public $firstBuyTrailOrderStudentList;
	public $channelList;
	public $maxStudentId = 0;
	public $batchDealLimit = 500;
	public $studentCount = 0;
	public $billMapTableName;
	public $studentTableName;
	public $channelTableName;
	public $giftCodeTableName;


	public function __construct()
	{
		$this->billMapTableName = BillMapModel::getTableNameWithDb();
		$this->studentTableName = DssStudentModel::getTableNameWithDb();
		$this->channelTableName = DssChannelModel::getTableNameWithDb();
		$this->giftCodeTableName = DssGiftCodeModel::getTableNameWithDb();
		$this->studentCount = DssStudentModel::getCount(['id[>]' => 0, 'status' => 1]);
	}


	/**
	 * 注册属性处理逻辑
	 */
	public function registerDeal()
	{
		//设置数据库对象
		$this->setMysqlDb();
		//循环处理数据
		for ($this->i = 1; $this->i <= ceil($this->studentCount / $this->batchDealLimit); $this->i++) {
			$this->setMysqlDb();
			$this->batchGetStudentList();
			if (!empty($this->studentList)) {
				$this->batchGetChannelList(array_unique(array_column($this->studentList, 'channel_id')));
				$this->sendRegisterNsqData();
			}
			sleep(5);
		}
	}

	/**
	 * 批量查询学生数据
	 */
	public function batchGetStudentList()
	{
		$studentList = $this->mysqlDb->queryAll("select uuid,channel_id,id from " . $this->studentTableName . " where id>" . $this->maxStudentId . " limit " . $this->batchDealLimit);
		if (!empty($studentList)) {
			$this->studentList = $studentList;
			$this->maxStudentId = (end($studentList))['id'];
		}
		SimpleLogger::info("register max student id data ", [$this->maxStudentId]);
	}


	/**
	 * 投递注册渠道属性消息
	 */
	public function sendRegisterNsqData()
	{
		$this->initTopic();
		foreach ($this->studentList as $sv) {
			$tmpNsqMsgBody = [
				'uuid_v2' => $sv['uuid'],
				'every'   => [
					'ai_channel_second_name_v2' => $this->channelList[$sv['channel_id']]['name'] ?? "未知渠道",
					'ai_channel_first_name_v2'  => $this->channelList[$sv['channel_id']]['parent_name'] ?? "未知渠道",
				]
			];
			try {
				$this->topic->updateUserProfile($tmpNsqMsgBody)->publish();
			} catch (Exception $e) {
				SimpleLogger::error('update user profile publish to sc error', [
					'errMsg'  => $e->getMessage(),
					'msgBody' => $tmpNsqMsgBody,
				]);
			}
		}
	}


	/**
	 * 购买体验课渠道属性处理逻辑
	 */
	public function buyTrailDeal()
	{
		//设置数据库对象
		$this->setMysqlDb();
		//循环处理数据
		for ($this->i = 1; $this->i <= ceil($this->studentCount / $this->batchDealLimit); $this->i++) {
			$this->setMysqlDb();
			//体验课订单购买渠道数据
			$this->getStudentFirstBuyTrailOrder();
			if (!empty($this->firstBuyTrailOrderStudentList)) {
				$this->batchGetChannelList(array_unique(array_column($this->firstBuyTrailOrderStudentList, 'buy_channel')));
				$this->sendBuyTrailNsqData();
			}
			sleep(5);
		}
	}

	/**
	 * 获取学生首次购买体验课订单数据
	 */
	public function getStudentFirstBuyTrailOrder()
	{
		//获取数据
		$sql = "SELECT s.id,s.uuid,g.parent_bill_id,m.buy_channel
FROM " . $this->studentTableName . " AS s
left JOIN " . $this->giftCodeTableName . " AS g ON s.id = g.buyer and g.duration_type=1
LEFT JOIN " . $this->billMapTableName . " AS m on g.parent_bill_id=m.bill_id 
where s.id>" . $this->maxStudentId . "  group by s.id limit " . $this->batchDealLimit;
		$studentOrderData = $this->mysqlDb->queryAll($sql);
		if (!empty($studentOrderData)) {
			$this->firstBuyTrailOrderStudentList = array_column($studentOrderData, null, 'id');
			$this->maxStudentId = (end($studentOrderData))['id'];
			SimpleLogger::info("first buy trail order max student id ", [$this->maxStudentId]);
		} else {
			$this->firstBuyTrailOrderStudentList = [];
		}
	}


	/**
	 * 投递首次购买体验课渠道属性消息
	 */
	public function sendBuyTrailNsqData()
	{
		$this->initTopic();
		foreach ($this->firstBuyTrailOrderStudentList as $sv) {
			if (empty($sv['parent_bill_id'])) {
				continue;
			}
			$tmpNsqMsgBody = [
				'uuid_v2' => $sv['uuid'],
				'every'   => [
					'ai_channel_secondpay_name' => $this->channelList[$sv['buy_channel']]['name'] ?? "未知渠道",
					'ai_channel_firstpay_name'  => $this->channelList[$sv['buy_channel']]['parent_name'] ?? "未知渠道",
				]
			];
			try {
				$this->topic->updateUserProfile($tmpNsqMsgBody)->publish();
			} catch (Exception $e) {
				SimpleLogger::error('update user profile publish to sc error', [
					'errMsg'  => $e->getMessage(),
					'msgBody' => $tmpNsqMsgBody,
				]);
			}
		}

	}

	/**
	 * 获取数据库实例对象
	 */
	public function setMysqlDb()
	{
		unset($this->mysqlDb);
		$this->mysqlDb = new MysqlDB(MysqlDB::CONFIG_SLAVE, true);
	}

	/**
	 * 获取渠道数据
	 * @param $channelIds
	 */
	public function batchGetChannelList($channelIds)
	{
		foreach ($channelIds as $ck => $cv) {
			if (empty($cv)) {
				unset($channelIds[$ck]);
			}
		}
		if (empty($channelIds)) {
			$this->channelList = [];
		} else {
			$channelData = $this->mysqlDb->queryAll("SELECT a.name,a.id,b.name AS parent_name FROM " . $this->channelTableName . " AS a 
LEFT JOIN " . $this->channelTableName . " AS b 
ON a.parent_id = b.id 
WHERE a.id in(" . implode(',', $channelIds) . ")");

			$this->channelList = array_column($channelData, null, 'id');
		}

	}

	/**
	 * 初始化topic对象实例
	 * @return false|void
	 */
	public function initTopic()
	{
		try {
			unset($this->topic);
			$this->topic = new SaBpDataTopic(1, true);
		} catch (Exception $e) {
			SimpleLogger::error('update user profile topic error', [
				'errMsg' => $e->getMessage(),
			]);
			return false;
		}
	}
}


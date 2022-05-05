<?php

namespace App\Services\CrawlerOrder;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CrawlerOrderModel;
use GuzzleHttp\Client;

abstract class CrawlerBaseService
{
    public $source = 0;//数据来源
    public $limit = 20;//每次查询的数据量
    public $remainingDealCount = 2;//脚本每次执行解密数据的剩余额度
    public $shopId = '';//目标店铺ID:管易/抖店
    public $ddShopId = '';//抖店店铺ID：如果是抖店此值与$shopId一致，如果是管易则是对应的抖店ID
    public $accessToken = null;//第三方平台登陆成功票据
    public $commonHeader = [];//公用header头参数
    public $commonCookieJar = [];
    public $orderList = [];//第三方平台爬取的订单列表
    public $requestClientObj = null;
    public $insertData = [];//写入数据库的数据
    public $nowTime = 0;//当前时间戳
    public $currentCrawlerIsFail = false;//请求第三方失败标志：true代表着要当前这次数据爬取失败了
    public $mysqlDb = null;
    public $account = '';//爬取数据登陆的账号，一般是手机号
    public $realTimeCount = 0;//第三方平台数据总量
    public $mysqlDataCount = 0;//当前数据库中数据总量

    /**
     * @param $shopId
     */
    public function __construct($shopId)
    {
        $this->nowTime = time();
        $this->shopId = $shopId;
        $this->requestClientObj = new Client(['debug' => false]);
    }

    /**
     * 搜索订单列表
     */
    abstract public function searchOrderList();

    /**
     * 设置cookie
     */
    abstract public function setCommonCookie();

    /**
     * 设置header头
     */
    abstract public function setCommonHeaders();

    /**
     * 数据库写入
     * @return bool
     */
    public function addRecord(): bool
    {
        if (empty($this->insertData)) {
            return true;
        }
        $this->setMysqlDb();
        $this->mysqlDb->insert(CrawlerOrderModel::$table, array_reverse($this->insertData));
        SimpleLogger::info("insert crawler data res", []);
        return true;
    }

    /**
     * 检测当前订单是否符合条件
     * @param $goodsCode
     * @param $orderCode
     * @return bool
     */
    public function checkOrderGoodsIsValid($goodsCode, $orderCode): bool
    {
        //有可能是多个商品编码，按照当前使用场景取第一个商品的编码
        if (strlen($goodsCode) != 12) {
            return false;
        }
        $this->setMysqlDb();
        $data = $this->mysqlDb->get(CrawlerOrderModel::$table, ['id'],
            ['order_code' => $orderCode, 'receiver_tel[!]' => '']);
        if (!empty($data)) {
            return false;
        }
        return true;
    }

    /**
     * 设置数据库实例
     */
    public function setMysqlDb()
    {
        if (time() - $this->nowTime >= 600) {
            unset($this->mysqlDb);
        }
        $this->mysqlDb = MysqlDB::getDB();
    }

    /**
     * 设置爬取账号不可用
     * @param $accountNumber
     * @param $reason
     */
    public function setCrawlerAccountDisable($accountNumber, $platFormName, $reason)
    {
        $rdb = RedisDB::getNewConn();
        $rdb->setex(CrawlerOrderModel::ACCOUNT_CRAWLER_STATUS_CACHE_KEY . $this->shopId . '_' . $accountNumber,
            Util::TIMESTAMP_1H,
            Constants::STATUS_FALSE);
        Util::sendFsWaringText("账号: " . $accountNumber . ' 爬取[' . $platFormName . ']数据失败，原因：' . $reason . '。账户冷冻一小时，将在' . date("Y-m-d H:i:s
       ", strtotime("+1 hour")) . '自动重试', $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
    }

    /**
     * 检测第三方平台订单数量和已爬取的数据关系
     * @return bool
     */
    public function checkCrawlerOrderCount(): bool
    {
        if ($this->realTimeCount == $this->mysqlDataCount) {
            SimpleLogger::error("realTimeCount = mysqlDataCount",
                ["real_time_count" => $this->realTimeCount, "mysql_data_count" => $this->mysqlDataCount]);
            return false;;
        }
        return true;
    }

}
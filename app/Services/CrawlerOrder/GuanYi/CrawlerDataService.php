<?php

namespace App\Services\CrawlerOrder\GuanYi;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\CrawlerOrderModel;
use App\Services\CrawlerOrder\CrawlerBaseService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class CrawlerDataService extends CrawlerBaseService
{
    public $headersUserAgent = '';
    public $listSearchArr = [];
    //订单查询列表
    private $searchUri = 'http://v2.guanyierp.com/tc/trade/trade_order_header/data/list';
    //收货地址明文:needDecryptType不传此参数可以解密所有数据 needDecryptType=4 收货人明文needDecryptType=3
    private $decryptReceiveDataUri = 'http://v2.guanyierp.com/tc/trade/trade_order_header/desentizationDataView';
    //获取订单总数
    private $orderTotal = 'http://v2.guanyierp.com/tc/trade/trade_order_header/data/total';

    /**
     * @param $config
     */
    public function __construct($config)
    {
        $this->ddShopId = CrawlerOrderModel::GUAN_YI_DOU_DIAN_SHOP_ID_MAP[$config['shop_id']];
        parent::__construct($config['shop_id']);
        $loginObj = new GyAdminLoginService($config['account'], $config['pwd'], $config);
        $jsessionIdCache = $loginObj->getJsessionIdCache();
        if (empty($jsessionIdCache)) {
            $this->accessToken = $loginObj->login();
        } else {
            $this->accessToken = $jsessionIdCache;
        }
        $this->account = $config['account'];
        $this->nowTime = time();
        $this->shopId = $config['shop_id'];
        $this->source = CrawlerOrderModel::SOURCE_GY;
        $this->headersUserAgent = $loginObj->getHeadersUserAgent();
        $this->requestClientObj = new Client(['debug' => false]);
        $this->setMysqlDataCount();
    }

    /**
     * 开爬
     * @return bool
     */
    public function do(): bool
    {
        if (empty($this->accessToken)) {
            SimpleLogger::error("gy login fail", []);
            return false;
        }
        $this->setCommonCookie();
        $this->setCommonHeaders();
        $this->setListSearchArr();
        //设置订单数据总量
        $this->getOrderTotal();
        //检测订单数量，是否有新数据可爬取
        if ($this->checkCrawlerOrderCount() === false) {
            return false;
        }
        while (true) {
            if ($this->remainingDealCount <= 0 || $this->currentCrawlerIsFail === true) {
                break;
            }
            $this->searchOrderList();
            $this->listSearchArr['page'] += 1;
            if ($this->listSearchArr['page'] > ceil($this->realTimeCount / $this->limit)) {
                SimpleLogger::info("page is max value", []);
                break;
            }
            $this->listSearchArr['start'] = ($this->listSearchArr['page'] - 1) * $this->limit;
        }
        return $this->addRecord();
    }

    /**
     * 搜索订单列表
     */
    public function searchOrderList()
    {
        //经过实验得到的，轻易不要改
        sleep(mt_rand(5, 10));
        try {
            $response = $this->requestClientObj->request('POST', $this->searchUri, [
                'cookies' => $this->commonCookieJar,
                'query'   => $this->listSearchArr,
                'headers' => $this->commonHeader
            ]);
            $body = $response->getBody()->getContents();
            SimpleLogger::info("gy list data", ['query' => $this->listSearchArr, 'response_body' => $body]);
            //解析结果
            $responseData = json_decode($body, true);
            if (empty($responseData['rows'])) {
                $this->currentCrawlerIsFail = true;
                SimpleLogger::info('search list fail', []);
            } else {
                foreach ($responseData['rows'] as $dv) {
                    //一笔订单可能存在多个商品，我们取第一个商品的sku，解析获得goods code
                    $tmpGoodsCode = explode("||",$dv['goodsList'][0]['platformSkuName'])[0];
                    if (!$this->checkOrderGoodsIsValid($tmpGoodsCode, $dv['platformCode'])) {
                        continue;
                    }
                    if ($this->remainingDealCount <= 0) {
                        break;
                    }
                    $this->remainingDealCount--;
                    $decryptData = $this->decryptAddressInfo($dv['id']);
                    $this->insertData[] = [
                        'third_order_id'   => $dv['id'],
                        'gy_shop_id'       => $dv['shopId'],
                        'dd_shop_id'       => $this->ddShopId,
                        'receiver_address' => $decryptData['receiverAddress'],
                        'receiver_name'    => $decryptData['receiverName'],
                        'goods_code'       => $dv['itemCodeCombo'],
                        'source'           => $this->source,
                        'is_send_erp'      => Constants::STATUS_FALSE,
                        'crawler_time'     => $this->nowTime,
                        'receiver_tel'     => $decryptData['receiverMobile'],
                        'order_code'       => $dv['platformCode'],
                    ];
                }
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $ge) {
            SimpleLogger::error("guzzleHttp error", ['err_msg' => $ge->getMessage()]);
        }
    }

    /**
     * 解析收货地址
     * @param $orderId
     * @return array
     */
    private function decryptAddressInfo($orderId): array
    {
        //经过实验得到的，轻易不要改
        sleep(mt_rand(80, 110));
        //查询参数
        $decryptQueryData = [
            "id"   => $orderId,
            "type" => "/tc/trade/trade_order_approve",
        ];
        $responseData = [];
        try {
            $response = $this->requestClientObj->request('POST', $this->decryptReceiveDataUri, [
                'headers' => $this->commonHeader,
                'cookies' => $this->commonCookieJar,
                'query'   => $decryptQueryData
            ]);
            $body = $response->getBody()->getContents();
            SimpleLogger::info("gy decrypt address data", ['query' => $decryptQueryData, 'response_body' => $body]);
            //解析结果
            $responseData = json_decode($body, true);
        } catch (\GuzzleHttp\Exception\GuzzleException $ge) {
            SimpleLogger::error("decrypt guzzleHttp error", ['err_msg' => $ge->getMessage()]);
        }
        //账户触发第三方规则，不能爬取数据
        if (empty($responseData['data']['receiverMobile'])) {
            $this->setCrawlerAccountDisable($this->account, "管易", $responseData['message'].$responseData['data']['frequencyResult']['errMsg']);
            return [];
        } else {
            return $responseData['data'];
        }
    }

    /**
     * 设置搜索参数
     */
    private function setListSearchArr()
    {
        $this->listSearchArr = [
            'page'     => 1,
            'limit'    => $this->limit,
            'start'    => 0,//目标页面数据的开始下标
            'dateType' => 0,//目前不知道这个参数意义
            'shopIds'  => $this->shopId, //指定的店铺ID。多个使用逗号分割即可
            //            'beginTime' => "2022-04-25 10:34:52",
            //            'endTime'   => "2022-04-26 10:34:52",
            //            "hasInvoice"    => true,//发票
            //            "approve"       => true,//审核
            //            "refund"        => false,//退款订单不考虑
            //            "financeReject" => false,//财审驳回订单不考虑
            "cancel"   => false,//作废订单不考虑
            "hold"     => false,//拦截订单不考虑
        ];
    }

    /**
     * 设置cookie
     */
    public function setCommonCookie()
    {
        $this->commonCookieJar = CookieJar::fromArray([
            'JSESSIONID' => $this->accessToken,
        ], 'v2.guanyierp.com');
    }

    /**
     * 设置header头
     */
    public function setCommonHeaders()
    {
        $this->commonHeader = [
            'User-Agent'   => $this->headersUserAgent,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host'         => 'v2.guanyierp.com',
            'Origin'       => 'http://v2.guanyierp.com',
            'Referer'      => 'http://v2.guanyierp.com/tc/trade/trade_order_header',
        ];
    }

    /**
     * 设置当前已爬取数据总量
     */
    public function setMysqlDataCount()
    {
        $this->setMysqlDb();
        $this->mysqlDataCount = $this->mysqlDb->count(CrawlerOrderModel::$table,
            ['gy_shop_id' => $this->shopId, 'receiver_tel[!]' => '']);
    }

    /**
     * 获取订单总数
     */
    private function getOrderTotal()
    {
        //查询参数
        $totalQueryData = $this->listSearchArr;
        unset($totalQueryData['page']);
        unset($totalQueryData['limit']);
        unset($totalQueryData['start']);
        $responseData = [];
        try {
            $response = $this->requestClientObj->request('POST', $this->orderTotal, [
                'headers' => $this->commonHeader,
                'cookies' => $this->commonCookieJar,
                'query'   => $totalQueryData
            ]);
            $body = $response->getBody()->getContents();
            SimpleLogger::info("gy data total count", ['query' => $totalQueryData, 'response_body' => $body]);
            //解析结果
            $responseData = json_decode($body, true);
        } catch (\GuzzleHttp\Exception\GuzzleException $ge) {
            SimpleLogger::error("total count guzzleHttp error", ['err_msg' => $ge->getMessage()]);
        }
        $this->realTimeCount = (int)$responseData['total'];
    }
}
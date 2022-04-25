<?php

namespace App\Services\CrawlerOrder\DouDian;

use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\CrawlerOrderModel;
use App\Services\CrawlerOrder\CrawlerBaseService;
use GuzzleHttp\Cookie\CookieJar;
use function GuzzleHttp\Psr7\parse_query;

class DdCrawlerDataService extends CrawlerBaseService
{
    //订单列表
    private $orderListUri = 'https://fxg.jinritemai.com/api/order/searchlist';
    //收货信息
    private $orderReceiveInfoUri = 'https://fxg.jinritemai.com/api/order/receiveinfo';
    private $listQueryData = [];//订单列表查询条件
    private $svWebId = '';
    private $phpSessid = '';

    public function __construct($config)
    {
        $this->ddShopId = $config['shop_id'];
        parent::__construct($config['shop_id']);
        $settingData = $this->checkDynamicSetting();
        if (!empty($settingData)) {
            $this->account = $config['account'];
            $this->source = CrawlerOrderModel::SOURCE_DD;
            $this->svWebId = $settingData['s_v_web_id'];
            $this->phpSessid = $settingData['PHPSESSID'];
            $this->listQueryData = parse_query($settingData['list_query_params']);
            $this->accessToken = true;
            $this->setCommonCookie();
            $this->setCommonHeaders();
        }
    }

    /**
     * 检测设置动态登陆数据
     * @return array
     */
    private function checkDynamicSetting(): array
    {
        //获取动态设置的账户登陆信息
        return RedisDB::getConn()->hgetall(CrawlerOrderModel::DD_DYNAMIC_SETTING_LOGIN_CACHE_KEY) ?? [];
    }


    /**
     * 开爬
     */
    public function do()
    {
        if (empty($this->accessToken)) {
            SimpleLogger::error("dd login fail", []);
            return false;
        }
        while (true) {
            $this->searchOrderList();
            if ($this->mysqlDataCount = $this->realTimeCount || $this->currentCrawlerIsFail === true) {
                break;
            }
            $this->listQueryData["page"] += 1;
        }
        $this->addRecord();
        return true;
    }

    /**
     * 获取订单列表
     */
    public function searchOrderList()
    {
        sleep(mt_rand(60, 120));
        //查询参数
        try {
            $response = $this->requestClientObj->request('GET', $this->orderListUri, [
                'headers' => $this->commonHeader,
                'cookies' => $this->commonCookieJar,
                'query'   => $this->listQueryData
            ]);
            $body = $response->getBody()->getContents();
            SimpleLogger::info("dd order list data", ['query' => $this->listQueryData, 'response_body' => $body]);
            //解析结果
            $responseData = json_decode($body, true);
            if ($responseData['code'] != 0) {
                $this->currentCrawlerIsFail = true;
            } else {
                $tmpOrderList = [];
                $this->realTimeCount = (int)$responseData['total'];
                if (!empty($responseData['data'])) {
                    foreach ($responseData['data'] as $dv) {
                        if (!$this->checkOrderGoodsIsValid($dv['product_item'][0]['merchant_sku_code'],
                            $dv['shop_order_id'])) {
                            continue;
                        }

                        $tmpAddressInfo = $this->decryptAddressInfo($dv['shop_order_id']);
                        $tmpOrderList[] = [
                            'order_code'       => $dv['shop_order_id'],
                            'third_order_id'   => $dv['shop_order_id'],
                            'gy_shop_id'       => '',
                            'dd_shop_id'       => $this->ddShopId,
                            'source'           => $this->source,
                            'crawler_time'     => $this->nowTime,
                            'is_send_erp'      => Constants::STATUS_FALSE,
                            'goods_code'       => $dv['product_item'][0]['merchant_sku_code'],
                            'receiver_address' => $tmpAddressInfo['receiver_address'] ?? '',
                            'receiver_name'    => $tmpAddressInfo['receiver_name'] ?? '',
                            'receiver_tel'     => $tmpAddressInfo['receiver_tel'] ?? '',
                        ];
                        $this->mysqlDataCount++;
                    }
                    $this->insertData += $tmpOrderList;
                }
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $ge) {
            SimpleLogger::error("dd order list guzzleHttp error", ['err_msg' => $ge->getMessage()]);
        }
    }

    /**
     * 解密收货信息
     * @param $orderId
     * @return array
     */
    public function decryptAddressInfo($orderId): array
    {
        sleep(mt_rand(120, 240));
        //查询参数
        $totalQueryData = [
            "order_id"   => $orderId,
            "ome_from"   => "pc",
            "aid"        => $this->listQueryData['aid'],
            "appid"      => $this->listQueryData['appid'],
            "__token"    => $this->listQueryData['__token'],
            "_bid"       => $this->listQueryData['_bid'],
            "_lid"       => $this->listQueryData['_lid'],
            "msToken"    => $this->listQueryData['msToken'],
            "X-Bogus"    => $this->listQueryData['X-Bogus'],
            "_signature" => $this->listQueryData['_signature'],
        ];
        try {
            $response = $this->requestClientObj->request('GET', $this->orderReceiveInfoUri, [
                'headers' => $this->commonHeader,
                'cookies' => $this->commonCookieJar,
                'query'   => $totalQueryData
            ]);
            $body = $response->getBody()->getContents();
            SimpleLogger::info("dd order receive info data", ['query' => $totalQueryData, 'response_body' => $body]);
            //解析结果
            $responseData = json_decode($body, true);
            return [
                'receiver_tel'     => (string)$responseData['data']['receive_info']['post_tel'] ?? '',
                'receiver_address' => implode('', [
                    $responseData['data']['receive_info']['post_addr']['province']['name'],
                    (string)$responseData['data']['receive_info']['post_addr']['city']['name'],
                    (string)$responseData['data']['receive_info']['post_addr']['town']['name'],
                    (string)$responseData['data']['receive_info']['post_addr']['street']['name'],
                    (string)$responseData['data']['receive_info']['post_addr']['detail']
                ]),
                'receiver_name'    => (string)$responseData['data']['receive_info']['post_receiver'],
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $ge) {
            SimpleLogger::error("dd order list guzzleHttp error", ['err_msg' => $ge->getMessage()]);
        }
        return [];
    }

    /**
     * 设置cookie
     */
    public function setCommonCookie()
    {
        $this->commonCookieJar = CookieJar::fromArray([
            's_v_web_id' => $this->svWebId,
            'PHPSESSID'  => $this->phpSessid,
            'SHOP_ID'    => $this->shopId,
        ], 'fxg.jinritemai.com');
    }

    /**
     * 设置header头
     */
    public function setCommonHeaders()
    {
        $this->commonHeader = [
            'User-Agent'     => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'sec-fetch-site' => 'same-origin',
            'referer'        => 'https://fxg.jinritemai.com/ffa/morder/order/list',
        ];
    }
}
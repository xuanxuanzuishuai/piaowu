<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2018/11/23
 * Time: 下午12:29
 */

namespace App\Libs;

use App\Services\DictService;
use GuzzleHttp;
use Slim\Http\StatusCode;

class Outcome
{
    public $success = false;
    public $data    = [];
    public $msg     = '';
}

class PayCenter
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    const CREATE_BILL_URL = 'createBill';//自主创建订单
    const MANUAL_CREATE_BILL_URL = 'manCreateBill';//人工录入订单
    const REFUND_BILL_URL = 'refundBill';//退单

    private $payCenterHost;

    public function __construct()
    {
        $prefix = DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DICT_KEY_CODE_PAY_CENTER_HOST);
//        $prefix = "http://127.0.0.1:9000/pay/v1/";
        if (empty($prefix) || substr($prefix, -1) != '/') {
            throw new \Exception('PAY_CENTER_HOST can not be empty,and must end with "/"');
        }
        $this->payCenterHost = $prefix;
    }

    private function strictRequest($method, $api, $data)
    {
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "data" => $data]);

        $client  = new GuzzleHttp\Client();
        $res     = $client->request(strtoupper($method), $api, [
            'body'    => json_encode($data),
            'headers' => ['content-type' => 'application/json']
        ]);
        $status  = $res->getStatusCode();
        $body    = $res->getBody()->getContents();
        $outcome = new Outcome();

        if (StatusCode::HTTP_OK == $status) {
            $res = json_decode($body, true);
            if (!is_null($res) && isset($res['code']) && $res['code'] == 0) {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, ['res' => $res, 'result' => 'success']);
                $outcome->success = true;
                $outcome->data    = $res['data'];
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, ['res' => $res, 'result' => 'fail']);
                $outcome->success = false;
                $outcome->msg     = $res['msg'];
            }
        } else {
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['body' => $body, 'result' => 'fail']);
            $outcome->success = false;
            $outcome->msg     = "http status is {$status}";
        }

        return $outcome;
    }

    /**
     * 创建订单
     * @param $bill
     * @return Outcome
     */
    public function createBill($bill)
    {
        return $this->strictRequest(self::METHOD_POST, $this->payCenterHost . self::CREATE_BILL_URL, $bill);
    }

    /**
     * 手工录入订单
     * @param $bill
     * @return outcome
     */
    public function manualCreateBill($bill)
    {
        return $this->strictRequest(self::METHOD_POST, $this->payCenterHost . self::MANUAL_CREATE_BILL_URL, $bill);
    }

    /**
     * 退单(退费)
     * @param RefundBill $bill
     * @return Outcome
     */
    public function refundBill(RefundBill $bill)
    {
        $params = [
            'data'          => $bill->data,
            'operator_id'   => $bill->operatorId,
            'operator_name' => $bill->operatorName,
        ];
        return $this->strictRequest(self::METHOD_POST, $this->payCenterHost . self::REFUND_BILL_URL, $params);
    }
}
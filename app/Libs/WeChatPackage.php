<?php

namespace App\Libs;

use Exception;
use GuzzleHttp\Client;

class WeChatPackage
{
    private $client;
    protected $values = array();
    public $signKey;

    function __construct()
    {
        $this->client = new Client();
    }

    /**
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return string 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * @param $xmlData
     * @return string|string[]
     */
    public static function _getSign($xmlData)
    {
        $res = @simplexml_load_string($xmlData, NULL, LIBXML_NOCDATA);
        $res = json_decode(json_encode($res), true);
        unset($res['sign']);
        ksort($res);
        $stringA = "";
        foreach ($res as $key => $value) {
            if (!empty($value)) {
                $stringA .= "{$key}=$value&";
            }
        }

        $stringSignTemp = "{$stringA}key=" . $_ENV['API_SIGH_KEY'];
        $sign = md5($stringSignTemp);

        $xmlData = str_replace("{sign}", $sign, $xmlData);
        return $xmlData;
    }

    public function set_mch_billno($mchBillNo)
    {
        $this->values['mch_billno'] = $mchBillNo;
    }

    public function get_mch_billno()
    {
        return $this->values['mch_billno'];
    }

    public function get_mch_id()
    {
        return $_ENV['WECHAT_MCHID'];
    }

    public function get_wxappid()
    {
        return $_ENV['WECHAT_APPID'];
    }

    public function set_nick_name($nick_name)
    {
        $this->values['nick_name'] = $nick_name;
    }

    public function get_nick_name()
    {
        return $this->values['nick_name'];
    }

    public function set_send_name($sendName)
    {
        $this->values['send_name'] = $sendName;
    }

    public function get_send_name()
    {
        return $this->values['send_name'];
    }

    public function set_re_openid($re_openid)
    {
        $this->values['re_openid'] = $re_openid;
    }

    public function get_re_openid()
    {
        return $this->values['re_openid'];
    }

    public function set_total_amount($total_amount)
    {
        $this->values['total_amount'] = $total_amount;
    }

    public function get_total_amount()
    {
        return $this->values['total_amount'];
    }

    public function set_amt_type($amt_type)
    {
        $this->values['amt_type'] = $amt_type;
    }

    public function get_amt_type()
    {
        return $this->values['amt_type'];
    }

    public function set_amt_list($amt_list)
    {
        $this->values['amt_list'] = $amt_list;
    }

    public function get_amt_list()
    {
        return $this->values['amt_list'];
    }

    public function set_watermark_imgurl($watermark_imgurl)
    {
        $this->values['watermark_imgurl'] = $watermark_imgurl;
    }

    public function set_banner_imgurl($banner_imgurl)
    {
        $this->values['banner_imgurl'] = $banner_imgurl;
    }

    public function set_min_value($min_value)
    {
        $this->values['min_value'] = $min_value;
    }

    public function get_min_value()
    {
        return $this->values['min_value'];
    }

    public function set_max_value($max_value)
    {
        $this->values['max_value'] = $max_value;
    }

    public function get_max_value()
    {
        return $this->values['max_value'];
    }

    public function set_total_num($total_num)
    {
        $this->values['total_num'] = $total_num;
    }

    public function get_total_num()
    {
        return $this->values['total_num'];
    }

    public function set_wishing($wishing)
    {
        $this->values['wishing'] = $wishing;
    }

    public function get_wishing()
    {
        return $this->values['wishing'];
    }

    public function set_client_ip($client_ip)
    {
        $this->values['client_ip'] = $client_ip;
    }

    public function get_client_ip()
    {
        return $this->values['client_ip'];
    }

    public function set_act_name($actName)
    {
        $this->values['act_name'] = $actName;
    }

    public function get_act_name()
    {
        return $this->values['act_name'];
    }

    public function set_remark($remark)
    {
        $this->values['remark'] = $remark;
    }

    public function get_remark()
    {
        return $this->values['remark'];
    }

    public function set_logo_imgurl($logo_imgurl)
    {
        $this->values['logo_imgurl'] = $logo_imgurl;
    }

    public function get_logo_imgurl()
    {
        return $this->values['logo_imgurl'];
    }


    public function set_share_content($share_content)
    {
        $this->values['share_content'] = $share_content;
    }

    public function get_share_content()
    {
        return $this->values['share_content'];
    }


    public function set_share_url($share_url)
    {
        $this->values['share_url'] = $share_url;
    }

    public function get_share_url()
    {
        return $this->values['share_url'];
    }

    public function set_share_imgurl($share_imgurl)
    {
        $this->values['share_imgurl'] = $share_imgurl;
    }

    public function get_share_imgurl()
    {
        return $this->values['share_imgurl'];
    }

    public function set_nonce_str($nonce_str)
    {
        $this->values['nonce_str'] = $nonce_str;
    }

    public function get_nonce_str()
    {

        return $this->values['nonce_str'];

    }

    public function get_mch_appid()
    {
        return $_ENV['WECHAT_APPID'];

    }

    public function get_mchid()
    {
        return $_ENV['WECHAT_APPID'];

    }

    public function set_partner_trade_no($partner_trade_no)
    {
        $this->values['partner_trade_no'] = $partner_trade_no;
    }

    public function get_partner_trade_no()
    {
        return $this->values['partner_trade_no'];
    }

    public function set_openid($openid)
    {
        $this->values['openid'] = $openid;
    }

    public function get_openid()
    {
        return $this->values['openid'];
    }

    public function set_check_name($check_name)
    {
        $this->values['check_name'] = $check_name;
    }

    public function get_check_name()
    {
        return $this->values['check_name'];
    }

    public function set_re_user_name($re_user_name)
    {
        $this->values['re_user_name'] = $re_user_name;
    }

    public function get_re_user_name()
    {
        return $this->values['re_user_name'];
    }

    public function set_amount($amount)
    {
        $this->values['amount'] = $amount;
    }

    public function get_amount()
    {
        return $this->values['amount'];
    }

    public function set_desc($desc)
    {
        $this->values['desc'] = $desc;
    }

    public function get_desc()
    {
        return $this->values['desc'];
    }

    public function set_spbill_create_ip($spbill_create_ip)
    {
        $this->values['spbill_create_ip'] = $spbill_create_ip;
    }

    public function get_spbill_create_ip()
    {
        return $this->values['spbill_create_ip'];
    }

    /**
     * 企业付款 xml 数据包
     * @param WeChatPackage $inputObj 传入数据
     * @return mixed 带签名的完整 xml 数据
     */
    public function getSendTransfersXml($inputObj)
    {
        $xml = <<<eof
            <xml>
                <mch_appid>{$inputObj->get_mch_appid()}</mch_appid>
                <mchid>{$inputObj->get_mchid()}</mchid>
                <nonce_str>{$inputObj->get_nonce_str()}</nonce_str>
                <partner_trade_no>{$inputObj->get_partner_trade_no()}</partner_trade_no>
                <openid>{$inputObj->get_openid()}</openid>
                <check_name>{$inputObj->get_check_name()}</check_name>
                <re_user_name>{$inputObj->get_re_user_name()}</re_user_name>
                <amount>{$inputObj->get_amount()}</amount>
                <desc>{$inputObj->get_desc()}!</desc>
                <spbill_create_ip>{$inputObj->get_spbill_create_ip()}</spbill_create_ip>
                <sign>{sign}</sign>
            </xml>
eof;
        $newXmlData = self:: _getSign($xml);
        $data['api_url'] = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $data['xml_data'] = $newXmlData;
        return $data;
    }

    /**
     * @param $mchBillNo
     * @param $openid
     * @param $amount
     * @param $wishing
     * @param $sendType
     * @return array|mixed
     */
    public function sendPackage($mchBillNo, $openid, $amount, $wishing, $sendType)
    {
        $getNewData = "";
        switch ($sendType) {
            case "transfer":
                $this->set_nonce_str(self::getNonceStr());        // 随机字符串
                $this->set_partner_trade_no($mchBillNo);                    // 商户订单号，需保持唯一性
                $this->set_openid($openid);                                // 用户在wxappid下的openid
                $this->set_check_name('NO_CHECK');                            // 是否校验真实姓名
                $this->set_re_user_name('');                                // 真实姓名
                $this->set_amount($amount);                                    // 企业付款金额，单位为分
                $this->set_desc($wishing);                                    // 企业付款操作说明信息。必填
                $this->set_spbill_create_ip($this->getServerIp());  // 调用接口的机器Ip地址
                $getNewData = $this->getSendTransfersXml($this);
                break;
            default:
                break;
        }

        // 得到签名和其它设置的 xml 数据

        try {
            $data =  $this->curl_post_ssl($getNewData['api_url'], $getNewData['xml_data']);
            $obj = simplexml_load_string($data,"SimpleXMLElement", LIBXML_NOCDATA);
            return json_decode(json_encode($obj),true);
        } catch (Exception $e) {
            SimpleLogger::error("wechat package erors", [$e->getMessage()]);
            return [];
        }
    }

    public function curl_post_ssl($url, $vars, $second = 30, $aHeader = array())
    {
        $vars = trim($vars);
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //cert 与 key 分别属于两个.pem文件
        curl_setopt($ch, CURLOPT_SSLCERT, $_ENV['API_CERT_PEM']);
        curl_setopt($ch, CURLOPT_SSLKEY, $_ENV['API_KEY_PEM']);

        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function getServerIp()
    {
        return '127.0.0.1';
    }

}
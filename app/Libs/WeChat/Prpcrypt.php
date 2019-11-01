<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/4
 * Time: 上午11:43
 */

namespace App\Libs\WeChat;

// 因为微信官方提供SDK中调用的函数较老，新的php版本已经不再支持，所以在原来的基础上作了改动
// https://blog.csdn.net/qq_32080545/article/details/102520167

class Prpcrypt
{
    public $key;

    function __construct($k)
    {
        $this->key = base64_decode($k . "=");
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return mixed 加密后的密文
     */
    public function encrypt($text, $appid)
    {
        try {
            $key = $this->key;
            $random = $this->getRandomStr();
            $text = $random.pack('N', strlen($text)).$text.$appid;
            $padAmount = 32 - (strlen($text) % 32);
            $padAmount = 0 !== $padAmount ? $padAmount : 32;
            $padChr = chr($padAmount);
            $tmp = '';
            for ($index = 0; $index < $padAmount; ++$index) {
                $tmp .= $padChr;
            }
            $text = $text.$tmp;
            $iv = substr($key, 0, 16);
            $encrypted = openssl_encrypt($text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
            return array(MsgErrorCode::$OK, base64_encode($encrypted));
        } catch (\Exception $e) {
            return array(MsgErrorCode::$EncryptAESError, null);
        }
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encrypted, $appid)
    {
        try {
            $key = $this->key;
            $ciphertext = base64_decode($encrypted, true);
            $iv = substr($key, 0, 16);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
        } catch (\Exception $e) {
            return array(MsgErrorCode::$DecryptAESError, null);
        }

        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);
        } catch (\Exception $e) {
            return array(MsgErrorCode::$IllegalBuffer, null);
        }

        if ($from_appid != $appid) {
            return array(MsgErrorCode::$ValidateAppidError, null);
        }

        return array(MsgErrorCode::$OK, $xml_content);
    }


    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    function getRandomStr()
    {
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/5
 * Time: 下午5:09
 */

namespace App\Libs\WeChat;

use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;

class Helper
{
    const EXAM_ACCESS_TOKEN_KEY = 'exam.access_token';

    //回复客服消息
    //https://www.jianshu.com/p/b663d3aede02
    public static function sendMsg(array $json, $appId = null, $secret = null)
    {
        if (empty($appId)) {
            $appId = $_ENV['EXAM_MINAPP_ID'];
        }
        if (empty($secret)) {
            $secret = $_ENV['EXAM_MINAPP_SECRET'];
        }
        $access_token = self::accessToken($appId, $secret);
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;

        $client = new Client(['debug' => false]);
        $response = $client->request('POST', $url, [
            'body'    => json_encode($json, JSON_UNESCAPED_UNICODE),
            'headers' => [
                'content-type' => 'application/x-www-form-urlencoded; utf-8'
            ]
        ]);

        SimpleLogger::info('send server content', ['content' => $json]);
        SimpleLogger::info('send server msg result:',
            ['status' => $response->getStatusCode(), 'body' => $response->getBody()->getContents()]);
    }

    public static function accessToken($appId, $secret)
    {
        $conn = RedisDB::getConn();
        $at = $conn->get(self::EXAM_ACCESS_TOKEN_KEY.$appId);
        if(!empty($at)) {
            return $at;
        }

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$secret}";
        $str = file_get_contents($url);
        $array = json_decode($str, 1);

        $token = $array['access_token'];

        if(!empty($token)) {
            $conn->set(self::EXAM_ACCESS_TOKEN_KEY.$appId, $token);
            $conn->expire(self::EXAM_ACCESS_TOKEN_KEY.$appId, $array['expires_in'] - 100);
        }

        SimpleLogger::info('we chat token', ['content' => $str]);

        return $token;
    }
}
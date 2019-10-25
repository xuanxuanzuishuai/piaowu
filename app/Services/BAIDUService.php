<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/25
 * Time: 下午8:17
 */

namespace App\Services;

use App\Libs\RedisDB;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;
use App\Libs\Exceptions\RunTimeException;

class BAIDUService
{
    private static $cacheAudioTokenKey = 'baidu_audio_token';

    public static function obtainBAIDUToken()
    {
        $conn = RedisDB::getConn();
        $token = $conn->get(self::$cacheAudioTokenKey);
        if(!empty($token)) {
            return $token;
        }

        $client = new Client();
        $url = $auth_url = "https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id=".$_ENV['BAIDU_AUDIO_API_KEY']."&client_secret=".$_ENV['BAIDU_AUDIO_SECRET_KEY'];
        $res = $client->request('GET', $url);
        $status = $res->getStatusCode();
        $body = $res->getBody()->getContents();

        if($status == StatusCode::HTTP_OK) {
            $obj = json_decode($body, 1);
            if(!isset($obj['access_token'])) {
                throw new RuntimeException(['obtain_token_error']);
            }
            if(!isset($obj['scope'])) {
                throw new RunTimeException(['obtain_scope_error']);
            }

            $token = $obj['access_token'];
            $expire = $obj['expires_in'];
            $conn->set(self::$cacheAudioTokenKey, $token);
            $conn->expire(self::$cacheAudioTokenKey, $expire - 100);

            return $token;
        } else {
            throw new RunTimeException([$body]);
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/5/29
 * Time: 上午10:12
 */

namespace App\Libs;


use GuzzleHttp\Client;

class Megatron
{
    const URL_SCORE_SOURCE = 'http://resource-repo-prod.oss-cn-beijing.aliyuncs.com/';

    const API_ALBUMS = '/api/1.0/albums'; //获取全部曲集
    const API_SCORES_BY_ALBUM = '/api/1.0/album/<int:id>/scores';//根据曲集ID获取曲谱基本信息
    const API_SEARCH = '/api/1.0/score/search';//曲谱搜索API
    const API_SCORE_BY_ID = '/api/1.0/score/<int:score_id>';//根据曲谱ID获取曲谱
    const API_ARTISTS = '/api/1.0/artists';//获取全部艺术家
    const API_ARTIST_BY_ID = '/api/1.0/artist/<int:id>';//根据艺术家ID获取艺术家
    const API_SCORES_BY_ARTIST = '/api/1.0/artist/<int:id>/scores';//获取某一个艺术家名下的所有曲谱

    private static function request($api,$method= 'GET') {
        $client = new Client();
        $api = $_ENV['MEGATRON_URL'] . $api;
        SimpleLogger::info(__FILE__ . ':' . __LINE__ . '同步曲谱曲谱', ['api'=>$api]);
        $response = $client->request($method,$api,['debug' => false]);
        $body = $response->getBody()->getContents();
        SimpleLogger::info(__FILE__ . ':' . __LINE__ . '同步曲谱曲谱', ['body'=>$body]);
        $status = $response->getStatusCode();
        SimpleLogger::info(__FILE__ . ':' . __LINE__ . '同步曲谱曲谱', ['status'=>$status]);
        if (200 == $status) {
            $res = json_decode($body,true);
            if(is_null($res) || empty($res['meta']) || $res['meta']['code'] > 0) {
                SimpleLogger::info(__FILE__ . ':' . __LINE__ . '同步曲谱曲谱', ['res'=>$res]);
                return false;
            }
            return $res['data'];
        } else {
            SimpleLogger::info(__FILE__ . ':' . __LINE__ . '同步曲谱曲谱', ['body'=>$body]);
            return false;
        }
    }

    public static function getAlbums() {
        return self::request(self::API_ALBUMS);
    }

    public static function getScoresByAlbum($albumId) {
        return self::request(str_replace('<int:id>', $albumId, self::API_SCORES_BY_ALBUM));
    }

    public static function getScoresByName($name) {
        return self::request(self::API_SEARCH . "?value=" . $name);
    }

    public static function getScoreByScoreId($scoreId) {
        return self::request(str_replace('<int:score_id>', $scoreId, self::API_SCORE_BY_ID));
    }
}
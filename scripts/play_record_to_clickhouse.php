<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/12/11
 * Time: 下午5:03
 */

namespace ERP;

use App\Libs\CHDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\AIPlayRecordCHModel;
use Dotenv\Dotenv;


date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$now = time();
$redis = RedisDB::getConn();
$key = $_ENV['CHDB_REDDIS_PRE_KEY'] . ($now - 1);
$result = $redis->lrange($key, 0, -1);
$chdb = CHDB::getDB();
$arr = [];
if (!empty($result)) {
    foreach ($result as $value) {
        $value = json_decode($value, true);
        $duration = ceil($value['duration']);
        $create_time = intval($value['created_at']);
        if ($value['created_at'] > 2000000000) {
            $create_time = intval($value['created_at']/1000);
        }
        $arr[] = array(
            'id' => 0,
            'student_id' => intval($value['student_id']),
            'lesson_id' => intval($value['lesson_id']),
            'score_id' => intval($value['score_id']),
            'record_id' => intval($value['record_id']),
            'is_phrase' => intval($value['is_phrase']),
            'phrase_id' => intval($value['phrase_id']),
            'practice_mode' => intval($value['practice_mode']),
            'hand' => intval($value['hand']),
            'ui_entry' => intval($value['ui_entry']),
            'input_type' => intval($value['input_type']),
            'old_format' => intval($value['old_format']),
            'create_time' => $now - 1,
            'end_time' => $create_time + $duration,
            'duration' => $duration,
            'audio_url' => strval($value['audio_url']),
            'score_final' => floatval($value['score_final']),
            'score_complete' => floatval($value['score_complete']),
            'score_pitch' => intval($value['score_pitch']),
            'score_rhythm' => intval($value['score_rhythm']),
            'score_speed' => intval($value['score_speed']),
            'score_speed_average' => intval($value['score_speed_average']),
            'data_type' => intval($value['data_type']),
            'track_id' => strval($value['track_id']),
            'score_rank' => floatval($value['score_rank']),
            'is_join_ranking' => intval($value['is_join_ranking']),
            'platform' => strval($value['platform']),
            'version' => strval($value['version']),
            'uuid' => strval($value['uuid']),
            'create_date' => date('Y-m-d',$now - 1)
        );
    }
    $res = $chdb->insert(AIPlayRecordCHModel::$table, $arr);
    SimpleLogger::info('batch insert '.$key,[$arr,$result,$res]);
}
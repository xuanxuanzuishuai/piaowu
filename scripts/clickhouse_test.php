<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/12/11
 * Time: 下午5:03
 */

namespace ERP;

use App\Models\AIPlayRecordCHModel;
use Dotenv\Dotenv;
use ClickHouseDB;


date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();

/**
 * 原始用法
 */
$config = [
    'host' => 'cc-2ze931ehsugy9w777o.ads.aliyuncs.com',
    'port' => '8123',
    'username' => 'xiaoyezi',
    'password' => 'XiaoYeZi123'
];
$db = new ClickHouseDB\Client($config);
$db->database('laohe');
$db->setTimeout(1.5);      // 1500 ms
$db->setTimeout(10);       // 10 seconds
$db->setConnectTimeOut(5); // 5 seconds

$statement = $db->select('
    SELECT     apr.student_id, s.name,MAX(apr.score_final) score FROM     ai_play_record_5 AS apr     inner join student as s on s.id = apr.student_id WHERE     (apr.lesson_id = 11467)         AND (apr.ui_entry = 2)         AND (apr.is_phrase = 0)         AND (apr.hand = 3)         AND (apr.score_final >= 60) GROUP BY student_id,s.name ORDER BY score DESC LIMIT 10  
');
print_r($statement->rows());
$statement->sql();

/**
 * 封装CHModel
 */
$ret = AIPlayRecordCHModel::testLessonRank(16087);
print_r($ret);
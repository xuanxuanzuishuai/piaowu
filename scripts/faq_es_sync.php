<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\FaqModel;
use Dotenv\Dotenv;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use App\Libs\ElasticsearchDB;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$sql = "select id,title,`desc` from faq where status = 1";

$res = MysqlDB::getDB()->queryAll($sql);
$client = ElasticsearchDB::getDB();

//$client->indices()->close(['index'=> ElasticsearchDB::getIndex(OpnSearcher::INDEX_LESSON) ]);

$params = [];
foreach ($res as $v) {
    $params['body'][] = ['index' => ['_index' => ElasticsearchDB::getIndex(FaqModel::$table), '_id' => $v['id']]];
    $params['body'][] = $v;
}
$response = $client->bulk($params);
//$client->indices()->open(['index' => ElasticsearchDB::getIndex(OpnSearcher::INDEX_LESSON)]);
SimpleLogger::info("faq_es_sync ", [date("Y-m-d H:i:s"),$response]);

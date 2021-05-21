<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-05-19
 * Time: 16:22:33
 */
/*
 * 海报文案迁移
 */
namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use Dotenv\Dotenv;
use App\Libs\MysqlDB;
use App\Models\EmployeeModel;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$maxId = 0;
while (true){
	$where = [
		'id[>]' => $maxId,
		'ORDER' => [
			'id' => 'ASC'
		],
		'LIMIT' => [0,100]
	];
	
	$dssWordData = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE)->select('template_poster_word', '*', $where);
	if (empty($dssWordData)) {
		break;
	}
	$maxId = max( array_column($dssWordData, 'id') );
	
	foreach ($dssWordData as &$dssWordDataE){
        $dssWordDataE['operate_id'] = EmployeeModel::SYSTEM_EMPLOYEE_ID;
		//unset($dssWordDataE['id']);
	}
	unset($dssWordDataE);
	
	$pdo = MysqlDB::getDB()->insert('template_poster_word', $dssWordData);
    if ($pdo->errorCode() != \PDO::ERR_NONE) {
        SimpleLogger::error('INSTER ERROR', $pdo->errorInfo());
    }
	//echo $res->queryString . PHP_EOL;
}




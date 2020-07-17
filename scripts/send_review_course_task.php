<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\AIPLClass;
use App\Libs\SimpleLogger;
use App\Models\ReviewCourseTaskModel;
use App\Models\StudentModel;
use App\Services\Queue\QueueService;
use App\Services\ReviewCourseTaskService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

$date = date('Ymd');
SimpleLogger::info('send review course task [START]', [
    'review_date' => $date,
]);

$tasks = ReviewCourseTaskModel::getRecords([
    'review_date' => $date,
    'status' => ReviewCourseTaskModel::STATUS_INIT,
], '*', false);

$total = count($tasks);
SimpleLogger::info('need send', [
    'total' => $total,
]);

$result = [ // 'type' => [count, ids]
    'success' => ['count' => 0, 'ids' => []],
    'report_not_found' => ['count' => 0, 'ids' => []],
    'fail' => ['count' => 0, 'ids' => []],
];

foreach ($tasks as $task) {
    $student = StudentModel::getById($task['student_id']);
    $report = AIPLClass::getClassReport($student['uuid'], strtotime($task['play_date']));
    if (empty($report)) {
        $result['report_not_found']['count']++;
        $result['report_not_found']['ids'][] = $task['id'];
        continue;
    }

    //添加到消息队列
    $queueRes = QueueService::pushTaskReview($task['id']);
    if (!$queueRes) {
        $result['fail']['count']++;
        $result['fail']['ids'][] = $task['id'];
    } else {
        $result['success']['count']++;
        $result['success']['ids'][] = $task['id'];
    }

}
SimpleLogger::info('send review course task [END]', $result);

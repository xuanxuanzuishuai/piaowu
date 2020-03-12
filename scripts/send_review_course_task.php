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
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\ReviewCourseTaskModel;
use App\Models\StudentModel;
use App\Services\ReviewCourseService;
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
    'wx_msg_fail' => ['count' => 0, 'ids' => []],
    'invalid_practice' => ['count' => 0, 'ids' => []],
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

    if (in_array('VERY_UNSKILLED', $report['tts_report_code'])
        || in_array('NO_VALID_PRACTICE', $report['tts_report_code'])) {
        $result['invalid_practice']['count']++;
        $result['invalid_practice']['ids'][] = $task['id'];
        continue;
    }

    try {
        $retMsg = ReviewCourseService::sendTaskReview($task['id']);
        SimpleLogger::info('send review course task ret', [
            'task' => $task,
            'ret' => $retMsg,
        ]);
        $resultType = ($retMsg == '发送成功') ? 'success' : 'wx_msg_fail';
        $result[$resultType]['count']++;
        $result[$resultType]['ids'][] = $task['id'];

    } catch (RunTimeException $e) {
        SimpleLogger::error('send review course task error', [
            'task' => $task,
            'e' => $e->getWebErrorData(),
        ]);
        $result['fail']['count']++;
        $result['fail']['ids'][] = $task['id'];
    }
}

SimpleLogger::info('send review course task [END]', $result);
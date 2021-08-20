<?php
/**
 *周周领奖白名单执行脚本
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Dss\DssStudentModel;
use App\Services\Queue\QueueService;
use App\Services\WhiteGrantRecordService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();


/**
 * 2021/8/13
 */


(new obj())->run();

class obj{

    public $msg = '';

    public function run(){

        $studentList = $this->getAwardList();

        foreach ($studentList as $uuid => $list){
            $student = DssStudentModel::getRecord(['uuid' => $uuid, 'status'=>DssStudentModel::STATUS_NORMAL], ['id','uuid','mobile','course_manage_id','status']);
            $data = [
                'uuid'=>$uuid,
                'list'=>$list,
                'student'=>$student,
            ];

            QueueService::weekWhiteGrandLeaf($data);
        }
    }
    /**
     * 获取奖励列表（生产者）
     * @return mixed
     */
    public function getAwardList(){
        return WhiteGrantRecordService::getAwardList();
    }
}

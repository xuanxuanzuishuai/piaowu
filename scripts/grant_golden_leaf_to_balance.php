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

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Models\BillMapModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\ParamMapModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\UserWeiXinModel;
use App\Models\WhiteGrantRecordModel;
use App\Services\ErpReferralService;
use App\Services\WhiteGrantRecordService;
use App\Services\WxSendMoneyToUserService;
use Dotenv\Dotenv;
use Medoo\Medoo;

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
            try {
                WhiteGrantRecordService::grant($data);
            }catch (RuntimeException $e){
                WhiteGrantRecordService::create($student, $e->getData(),WhiteGrantRecordModel::STATUS_GIVE_FAIL);
            }

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

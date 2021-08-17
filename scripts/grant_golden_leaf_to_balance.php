<?php
/**
 *
 * dss.template_poster => op.template_poster
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

































//wechat_openid_list

//user_weixin










die;
$slaveDB = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
$db = MysqlDB::getDB();

// start
//1.获取student_referral_student_statistics列表 11w

$page = $argv[1] ?? 1;

$page = $page-1;

$pageSize = $argv[2] ?? 10000;
$split = $argv[3] ?? 1000;

//1. 每次默认查询1w条
echo '查询statistics  ' . $pageSize . '  条数据' . PHP_EOL;

$s_limit = $page * $pageSize;
$statisticsSql = "select student_id from student_referral_student_statistics limit $s_limit, $pageSize";
$statisticsList = $db->queryAll($statisticsSql);

$count_statisticsList = count($statisticsList);

echo '实际查出 statistics  共：' . $count_statisticsList . '  条数据' . PHP_EOL . PHP_EOL ;
if($count_statisticsList<1){
    echo '停止执行';
    return ;
}

//2. dss 查询1w个学生的channel_id
$studentIds = array_unique(array_column($statisticsList, 'student_id'));

echo '查询student_id  '.count($studentIds).'  条数据' . PHP_EOL;

$studentSql = "SELECT id,channel_id FROM student where id in (" . implode(',', $studentIds) . ')';
$studentList = $slaveDB->queryAll($studentSql);
$count_studentList = count($studentList);


echo '实际查出student_id  '.$count_studentList.'  条数据' . PHP_EOL . PHP_EOL ;

if($count_statisticsList != $count_studentList){
    $tmp_s_ids = array_column($studentList, 'id');
    $diff = array_diff($studentIds, $tmp_s_ids);
    echo '未被查到的ID：' . json_encode(array_values($diff)) . PHP_EOL . PHP_EOL ;
}


$y = ceil(count($studentList) / $split);

echo '共计执行 ' . $y . ' 次' . PHP_EOL . PHP_EOL;


for($i=0;  $i < $y; $i++){
    $offset = $i * $split;
    $tmpList = array_slice($studentList, $offset, $split);

    $ids = array_column($tmpList,'id');
    $exec_count = count($ids);
    $ids = implode(',', $ids);

    $whens = '';

    foreach ($tmpList as $item){
        $whens .= ' when student_id = '.$item['id'].' then ' . $item['channel_id'];
    }

    $sql = "UPDATE student_referral_student_statistics set buy_channel = (
        CASE 
            $whens
        END
    
    ) where student_id in ($ids)";

    $db->queryAll($sql);

    echo '第' . ($i+1) . '次更新' . $exec_count . '条数据' . PHP_EOL;

}



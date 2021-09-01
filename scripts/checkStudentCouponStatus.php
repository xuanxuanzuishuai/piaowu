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
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentCouponV1Model;
use App\Models\RtCouponReceiveRecordModel;
use App\Services\CommonServiceForApp;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

(new main())->run();

class main{

    public $dbRO, $rtCouponReceIve, $erpStudentCoupon, $dssStudent, $startTime, $endTime, $maxExec, $smsObj;
    public function __construct(){
        $this->dbRO             = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
        $this->smsObj           = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $this->dssStudent       = DssStudentModel::getTableNameWithDb();
        $this->rtCouponReceIve  = RtCouponReceiveRecordModel::getTableNameWithDb();
        $this->erpStudentCoupon = ErpStudentCouponV1Model::getTableNameWithDb();

        $this->maxExec   = 200; //最大执行条数

        $this->startTime = strtotime(date('Y-m-d H:00:00') . '+1 hours');
        $this->endTime   = $this->startTime + 86400;

    }

    public function run(){

        $uuids = $this->getUuids();

        if(empty($uuids)){
            SimpleLogger::info('emptyUUids', ['uuids' => $uuids]);
            return;
        }

        $uuidsList = array_unique(array_column($uuids, 'uuid'));

        $count = count($uuidsList);

        //超过maxExec最大值则用分页
        $num = ceil($count / $this->maxExec);
        for ($i=0; $i < $num; $i++){
            $s = $i * $this->maxExec;
            $uuids = array_slice($uuidsList, $s, $this->maxExec);

            $mobiles = $this->getMobiles($uuids);
            $this->sendSms($mobiles);
        }
    }

    /**
     * 获取未使用优惠券的uuid
     * @return array|null
     */
    public function getUuids(){
        $fields = 'e.uuid, e.expired_end_time';
        //获取学生未使用的优惠券
        $sql = 'SELECT '.$fields.' FROM '
            . $this->rtCouponReceIve . ' as r LEFT JOIN ' . $this->erpStudentCoupon .
            ' as e ON r.student_coupon_id = e.id WHERE r.status = ' . RtCouponReceiveRecordModel::REVEIVED_STATUS .
            ' AND e.status = ' . ErpStudentCouponV1Model::STATUS_UNUSE .
            ' AND expired_end_time BETWEEN ' . $this->startTime . ' AND ' . $this->endTime;
        return $this->dbRO->queryAll($sql);
    }

    /**
     * 获取手机号
     * @param $uuids
     * @return array|null
     */
    public function getMobiles($uuids){
        $sql = 'SELECT mobile FROM ' . $this->dssStudent . ' WHERE uuid in (' . implode(',', $uuids) . ')';
        return $this->dbRO->queryAll($sql);
    }

    /**
     * 发送短信
     * @param $mobiles
     */
    public function sendSms($mobiles){
        $msg = '尊敬的小叶子用户，您领取的500元年卡代金券有效期不足24小时了，赶快联系您的助教购买吧，购买后还有好礼相送。如已使用，请忽略。';
        $sign = CommonServiceForApp::SIGN_WX_STUDENT_APP;
        foreach ($mobiles as $one){
            $this->smsObj->sendCommonSms($msg, $one['mobile'], $sign);
        }
    }
}
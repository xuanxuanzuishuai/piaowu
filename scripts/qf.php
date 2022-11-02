<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-05-19
 * Time: 16:22:33
 */

/*
 * 端午节限时营销活动
 * http://jira.xiaoyezi.com/browse/AIPL-15492
 */

namespace Script;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealWeekActivityModel;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\WeekActivityModel;
use App\Models\WeekActivityUserAllocationABModel;
use App\Services\Activity\RealWeekActivity\RealWeekActivityClientService;
use App\Services\ActivityDuanWuService;
use App\Services\Queue\QueueService;
use App\Services\RealWeekActivityService;
use App\Services\SharePosterService;
use App\Services\StudentService;
use App\Services\StudentServices\ErpStudentService;
use App\Services\UserService;
use App\Services\WeekActivityService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// header TOKEN=qingfeng-test
// $userInfo = DssStudentModel::getRecord(['mobile'=>'19700019938']);
// $userInfo['user_type'] = 1;
// $userInfo['user_id'] = $userInfo['id'];
// RedisDB::getConn()->set("op_wechat_token_qingfeng-test", json_encode($userInfo));
// exit;



// $a = (new Erp())->getStudentCourses('310707171778154879');
// var_dump($a);exit;


// 16123471750 10月25日之前付费已耗尽  - 只购买了陪练课
// 16123483787 10月25日之前付费未消耗完  - 只购买了陪练课
// 14400004410 10月25日之后付费未消耗我那  - 只购买了陪练课
// 19700080178  2022-05-24 16:33:21
// $userInfo= ErpStudentModel::getRecord(['mobile' => '19700019938']);
// var_export(UserService::getStudentCourseData($userInfo['uuid']));exit;

// $studentDetail = DssStudentModel::getRecord(['uuid' => '338094239583883647']);  // 命中的活动不对
// $studentDetail = DssStudentModel::getRecord(['uuid' => '336352871232622975']);      // 应该不能参加周周
// $studentDetail = DssStudentModel::getRecord(['mobile' => '19700080196']);
// $a = RealWeekActivityService::getStudentCanPartakeWeekActivityList($studentDetail);
// var_dump([$a, $studentDetail['id']]);exit;



// 338094239583883647  两个接口返回的首次付费时间不一样
// $studentIdAttribute = ErpStudentService::getStudentCourseData($studentUUID);
$studentIdAttribute = ErpStudentService::getStudentCourseData('326067509910360447');
// $studentIdAttribute = UserService::getStudentIdentityAttributeById(Constants::REAL_APP_ID, 336567, '326067509910360447');
var_dump($studentIdAttribute);exit;


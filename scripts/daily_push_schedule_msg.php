<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/25
 * Time: 14:53
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\ClassUserModel;
use App\Models\CourseModel;
use App\Models\OrganizationModel;
use App\Models\ScheduleModel;
use App\Models\ScheduleUserModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Libs\UserCenter;
use Dotenv\Dotenv;
use App\Services\WeChatService;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();
$startTime = strtotime(date('Y-m-d', strtotime('+1 day')));
$endTime = $startTime + 86400;
$sql = "select user_weixin.open_id,`schedule`.start_time,course.name course_name,course.duration,student.name
from " . ScheduleModel::$table . " schedule
inner join ". CourseModel::$table ." course on schedule.course_id = course.id
inner join " . ClassUserModel::$table . " class_user on schedule.class_id = class_user.class_id
                                                    and class_user.user_role = ". ClassUserModel::USER_ROLE_S ."
                                                    and class_user.`status` = ". ClassUserModel::STATUS_NORMAL ."
                                                    inner join " . UserWeixinModel::$table . " user_weixin on class_user.user_id = user_weixin.user_id
                                                        and user_weixin.user_type = ". UserWeixinModel::USER_TYPE_STUDENT_ORG ."
                                                        and user_weixin.busi_type = ". UserWeixinModel::BUSI_TYPE_STUDENT_SERVER ."
                                                        and user_weixin.app_id = " . UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . "
left join " . ScheduleUserModel::$table . " schedule_user on `schedule`.id = schedule_user.schedule_id
                                                            and class_user.user_id = schedule_user.user_id
                                                            and schedule_user.user_role = ". ScheduleUserModel::USER_ROLE_STUDENT ."
                                                            and schedule_user.user_status = ". ScheduleUserModel::STUDENT_STATUS_BOOK ."
                                                            and schedule_user.status = " . ScheduleUserModel::STATUS_NORMAL . "
inner join " . StudentModel::$table . " student on class_user.user_id = student.id
where schedule.start_time >= {$startTime} and schedule.end_time <= {$endTime} and schedule.org_id = " . OrganizationModel::ORG_ID_DIRECT;

$info = $db->queryAll($sql, []);

if (!empty($info)) {
    foreach ($info as $value) {
        $data = [
            'first' => [
                'value' => "{$value['name']}你好，新的课程安排来啦!!!",
                'color' => "#FF8A00"
            ],
            'keyword1' => [
                'value' => $value['course_name']
            ],
            'keyword2' => [
                'value' => date('Y-m-d H:i', $value['start_time'])
            ],
            'remark' => [
                'value' => '此课程预计时长' .intval($value['duration'] / 60) . '分钟，请提前做好上课准备'
            ]
        ];
        $ret = WeChatService::notifyUserWeixinTemplateInfo(
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT_ORG,
            $value["open_id"],
            $_ENV["WECHAT_SCHEDULE_MSG_REMIND"],
            $data
        );
        SimpleLogger::info("result:", $ret);
    }
}

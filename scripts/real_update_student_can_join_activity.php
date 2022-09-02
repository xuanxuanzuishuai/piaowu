<?php
/**
 * 更新学生参加活动信息
 * author: qingfeng.lian
 * date: 2022/8/30
 */

namespace App;

use App\Libs\Constants;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealStudentCanJoinActivityHistoryModel;
use App\Models\RealStudentCanJoinActivityModel;
use App\Models\RealWeekActivityModel;
use App\Services\ErpUserService;
use App\Services\SyncTableData\CheckStudentIsCanActivityService;
use App\Services\SyncTableData\TraitService\StatisticsStudentReferralBaseAbstract;
use Dotenv\Dotenv;

date_default_timezone_set('PRC');
// 不限时
set_time_limit(0);
// 设置内存
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptRealUpdateStudentCanJoinActivity
{
    protected $payStudentList    = [];
    protected $weekActivityList  = [];
    protected $handleStudentUuid = [];
    protected $sleepSecond       = 2;
    protected $sleepHandleNum    = 5000;

    public function run()
    {
        // 读取所有付费并且有剩余正式课的用户
        $this->payStudentList = ErpUserService::getIsPayAndCourseRemaining();
        // 获取白名单用户
        $whitList = StatisticsStudentReferralBaseAbstract::getAppObj(Constants::REAL_APP_ID)->getRealWhiteList();
        if (!empty($whitList)) {
            // 在用户列表中加入白名单用户
            foreach ($whitList as $item) {
                $this->payStudentList[] = [
                    'id' => $item['student_id'],
                    'country_code' =>$item['country_code'],
                    'uuid' => $item['uuid'],
                    'first_pay_time' => $item['first_pay_time'],
                    'clean_time' => 0,
                ];
            }
            unset($item);
        }
        $this->weekActivityList = RealWeekActivityModel::getStudentCanSignWeekActivity(200, time() + 1, [
            'activity_id',
            'clean_is_join',
            'activity_country_code',
            'target_user_type',
            'target_use_first_pay_time_start',
            'target_use_first_pay_time_end',
            'award_prize_type',
            'start_time',
            'end_time',
        ]);
        $this->formatWeekActivityList();
        // 如果没有付费学员就不用检查活动了- 直接把所有用户命中的活动更新为0
        if (!empty($this->payStudentList)) {
            foreach ($this->payStudentList as $_num => $_student) {
                // 过滤重复的uuid
                if (isset($this->handleStudentUuid[$_student['uuid']])) {
                    continue;
                }
                // 处理一定数据后休眠
                if ($_num % $this->sleepHandleNum == 0) {
                    sleep($this->sleepSecond);
                }
                // 检查命中的周周领奖活动
                CheckStudentIsCanActivityService::checkWeekActivity($_student, $this->weekActivityList);
                // 记录处理过的uuid
                $this->handleStudentUuid[$_student['uuid']] = 1;
            }
            unset($_num, $_student);
        }
        // 更新所有更新日期不是今天的为不可参与
        RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId(['week_update_time[<]' => strtotime(date("Ymd"))]);

        // 没有付费学员时把命中当前正在运行的活动的用户参与进度更新为终止参与
        // 参与活动历史记录中当前正在运行的活动已经存在的并且当天没有更新的更新为参与终止
        if (!empty($this->weekActivityList)) {
            $activityIds = array_column($this->weekActivityList, 'activity_id');
            RealStudentCanJoinActivityHistoryModel::stopStudentJoinWeekActivityProgress([
                'activity_id'         => $activityIds,
                'week_update_time[<]' => strtotime(date("Ymd"))
            ]);
        }
    }

    public function formatWeekActivityList()
    {
        if (empty($this->weekActivityList)) return;
        $activityIds = array_column($this->weekActivityList, 'activity_id');
        $list = RealSharePosterPassAwardRuleModel::getActivityTaskCount($activityIds);
        $list = array_column($list, 'total', 'activity_id');
        foreach ($this->weekActivityList as &$item) {
            $item['activity_task_total'] = $list[$item['activity_id']] ?? 0;
            $item['target_user_first_pay_time_start'] = $item['target_use_first_pay_time_start'];
            $item['target_user_first_pay_time_end'] = $item['target_use_first_pay_time_end'];
        }
    }
}

(new ScriptRealUpdateStudentCanJoinActivity())->run();
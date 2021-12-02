<?php
/**
 * 真人业务线
 * 数据监测： 检查真人业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据
 * 脚本只计算新的周周领奖奖励规则产生的数据
 * 检测范围： 10天内产生奖励数据的活动
 * author: qingfeng.lian
 * date: 2021/12/2
 */

namespace Check;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Erp\ErpStudentModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\RealWeekActivityModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

use App\Libs\Constants;
use App\Services\CheckDataBaseService;

class RealCheckDataWeekAward extends CheckDataBaseService
{
    const LOG_TITLE = 'real_check_data_week_award';
    const APP_ID    = Constants::REAL_APP_ID;

    /**
     * 获取指定时间段内的产生的所有奖励
     * @return array|null
     */
    public static function getCheckData()
    {
        $awardTable = RealUserAwardMagicStoneModel::$table;
        $sql        = 'select award.activity_id,award.uuid,award.create_time,award.award_status,award.award_amount,count(*) as total from ' . $awardTable . ' as award' .
            ' where award.activity_id>' . self::$oldRuleLastActivityId .
            " and award_node='" . Constants::WEEK_SHARE_POSTER_AWARD_NODE . "'" .
            ' and create_time>=' . self::$checkStartTime . ' and create_time<=' . self::$checkEntTime .
            ' group by award.activity_id, award.uuid';
        return self::$db->queryAll($sql);
    }

    /**
     * 获取学生列表
     * @param $uuids
     * @return array
     */
    public static function getStudentList($uuids)
    {
        return array_column(ErpStudentModel::getRecords(['uuid' => array_unique($uuids)], ['id', 'uuid']), null, 'uuid');
    }

    /**
     * 获取活动列表
     * @param $activityIds
     * @return array
     */
    public static function getActivityList($activityIds)
    {
        return array_column(RealWeekActivityModel::getRecords(['activity_id' => array_unique($activityIds)], ['activity_id', 'send_award_time']), null, 'activity_id');
    }

    /**
     * 获取学生应得的奖励数量
     * @param $studentId
     * @param $activityId
     * @param $createAwardTime
     * @return int|mixed
     */
    public static function getStudentDeservedRewards($studentId, $activityId, $createAwardTime)
    {
        $sharePosterCount = RealSharePosterModel::getCount([
            'student_id'      => $studentId,
            'activity_id'     => $activityId,
            'verify_status'   => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time[<=]' => $createAwardTime
        ]);
        // var_dump($studentId, $activityId,$sharePosterCount, $createAwardTime);
        $activityPassAwardRule = self::getSharePosterPassAwardRule($activityId);
        $deservedRewards       = $activityPassAwardRule[$sharePosterCount] ?? [];
        return $deservedRewards['award_amount'] ?? 0;
    }

    /**
     * 获取分享截图审核通过奖励规则
     * @param $activityId
     * @return array|mixed
     */
    public static function getSharePosterPassAwardRule($activityId)
    {
        if (!empty(self::$sharePosterPassAwardRule[$activityId])) {
            return self::$sharePosterPassAwardRule[$activityId];
        }
        $ruleList = RealSharePosterPassAwardRuleModel::getRecords(['activity_id' => $activityId]);
        if (empty($ruleList)) {
            return [];
        }
        self::$sharePosterPassAwardRule[$activityId] = array_column($ruleList, null, 'success_pass_num');
        return self::$sharePosterPassAwardRule[$activityId];
    }

    /**
     * 格式化邮件数据
     * @param $abnormalData
     * @return array
     */
    public static function formatSendMail($abnormalData)
    {
        $subject = $_ENV['ENV_NAME'] . ' - 数据监测： 真人业务线周周领奖用户上传分享截图获得的奖励是否存在异常数据';
        $content = '';
        foreach ($abnormalData as &$item) {
            $item['err_msg'] = self::ERR_CODE_MSG[$item['err_code']] ?? '';
            $content         .= '活动id：' . $item['activity_id'] . '; 用户uuid：' . $item['uuid'] . '; 错误原因：' . $item['err_msg'] . '</br>';
        }
        unset($item);
        return [$subject, $content];
    }
}

(new RealCheckDataWeekAward())->run();

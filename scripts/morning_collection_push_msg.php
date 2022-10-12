<?php
/**
 * 清晨-5日打卡开班通知
 * 每天早上9点运行
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

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dawn\DawnCollectionModel;
use App\Models\Dawn\DawnLeadsModel;
use App\Services\Queue\MorningReferralTopic;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptMorningCollectionPushMsg
{
    const LogTitle = 'ScriptMorningCollectionPushMsg';
    private $time;
    private $secondSendNum    = 500;
    private $deferSecond      = 0;
    private $currentHandleNum = 0;

    /**
     * 初始化参数
     */
    public function __construct()
    {
        $this->time = time();
    }

    /**
     * 保存日志
     * @param $msg
     * @param array $data
     * @return void
     */
    private function saveLog($msg, array $data = [])
    {
        SimpleLogger::info(self::LogTitle . ' step msg:' . $msg, $data);
    }

    /**
     * 计算延迟时间
     * @return void
     */
    public function computeDefTime()
    {
        $this->currentHandleNum++;
        $this->deferSecond = intval($this->currentHandleNum / $this->secondSendNum);
    }

    public function run()
    {
        $this->saveLog("start");
        // 获取所有当前处在开班期的班级列表
        $collList = $this->getDataList();
        foreach ($collList as $key => &$item) {
            // 检查班级当前已经开班几天
            $chaDay = $this->computeOpenDay($item['teaching_start_time']);
            $item['cha_day'] = $chaDay;
            if ($chaDay == 0) {
                // day0 - 推送开班通知
                $this->pushCollectionOpenMsg($item['id']);
            } elseif ($chaDay >= 1 && $chaDay <= 3) {
                // day1~day3 - 邀请达标用户参与互动
                $this->pushJoinStudentMsg($item['id'], $chaDay);
            } else {
                // 其他时间不处理
            }
        }
        unset($key, $item);

        return true;
    }

    /**
     * 获取开班期的班级列表
     * @return array
     */
    public function getDataList()
    {
        $list = DawnCollectionModel::getRecords([
            'teaching_start_time[<=]' => $this->time,
            'teaching_end_time[>]'    => $this->time
        ]);
        return is_array($list) ? $list : [];
    }

    /**
     * 计算开班第几天, 向上取整
     * @param $collectionStartTime
     * @return float
     */
    public function computeOpenDay($collectionStartTime)
    {
        $cha = $this->time - $collectionStartTime;
        return intval($cha / Util::TIMESTAMP_ONEDAY);
    }

    /**
     * 获取班级下的所有学员
     * @param $collectionId
     * @return mixed
     */
    public function getCollectionStudentList($collectionId)
    {
        return DawnLeadsModel::getRecords(['collection_id' => $collectionId], ['uuid']);
    }

    /**
     * 推送开班通知消息
     * @param $collectionId
     * @return bool
     */
    public function pushCollectionOpenMsg($collectionId)
    {
        try {
            // 获取所有班级内的学员
            $studentList = $this->getCollectionStudentList($collectionId);
            // 获取学员对应的openid
            $studentOpenid = (new Morning())->getStudentOpenidByUuid(array_column($studentList, 'uuid'));
            foreach ($studentList as $_stu) {
                if (empty($studentOpenid[$_stu['uuid']])) {
                    continue;
                }
                // 发送消息
                QueueService::morningPushMsg(MorningReferralTopic::EVENT_WECHAT_PUSH_MSG_TO_STUDENT,
                    [
                        'collection_id' => $collectionId,
                        'uuid'          => $_stu['uuid'],
                        'openid'        => $studentOpenid[$_stu['uuid']],
                    ]
                    , $this->deferSecond
                );
            }
        } catch (RunTimeException $e) {
            $this->saveLog("pushCollectionOpenMsg error", [$e->getMessage()]);
        } finally {
            $this->computeDefTime();
        }
        return true;
    }


    /**
     * 推送邀请学生参加活动通知
     * @param $collectionId
     * @param int $day 第几天的消息(从0开始)
     * @return bool
     */
    public function pushJoinStudentMsg($collectionId, $day)
    {
        try {
            // 获取所有班级内的学员
            $studentList = $this->getCollectionStudentList($collectionId);
            $uuids = array_column($studentList, 'uuid');
            // 获取学员对应的openid
            $studentOpenid = (new Morning())->getStudentOpenidByUuid($uuids);
            // 获取学生练琴曲目信息
            $studentLesson = (new Morning())->getStudentLessonSchedule($uuids);
            foreach ($studentList as $_stu) {
                if (empty($studentOpenid[$_stu['uuid']])) {
                    continue;
                }
                // 计算曲目信息
                list($isDoneNum, $dayLesson) = $this->computeLesson($studentLesson[$_stu['uuid']], $day);
                if (empty($dayLesson)) {
                    continue;
                }
                // 发送消息
                QueueService::morningPushMsg(MorningReferralTopic::EVENT_WECHAT_PUSH_MSG_JOIN_STUDENT,
                    [
                        'collection_id' => $collectionId,
                        'uuid'          => $_stu['uuid'],
                        'openid'        => $studentOpenid[$_stu['uuid']],
                        'day'           => $day,
                        'lesson'        => [
                            'report'      => $dayLesson['report'],
                            'lesson_name' => $dayLesson['lesson_name'],
                            'unlock_time' => $dayLesson['unlock_time'],
                        ],
                    ]
                    , $this->deferSecond
                );
            }
        } catch (RunTimeException $e) {
            $this->saveLog("pushCollectionOpenMsg error", [$e->getMessage()]);
        } finally {
            $this->computeDefTime();
        }
        return true;
    }

    /**
     * 计算曲目中有多少个已学习的课程，以及最后完成的课程信息
     * @param $lesson
     * @param number $day 从0开始 0：第一天
     * @return array
     */
    public function computeLesson($lesson, $day)
    {
        $isDoneNum = 0;
        $dayInfo = [];
        foreach ($lesson as $item) {
            foreach ($item as $key => $_info) {
                // 是否是已学习
                if ($_info['status'] == Constants::STUDENT_LESSON_SCHEDULE_STATUS_DONE) {
                    $isDoneNum++;
                    // 指定天的练琴信息
                    if ($day == $key) {
                        $dayInfo = $_info;
                    }
                }
            }
        }
        return [$isDoneNum, $dayInfo];
    }
}

(new ScriptMorningCollectionPushMsg())->run();
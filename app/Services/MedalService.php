<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/7
 * Time: 3:29 PM
 */

namespace App\Services;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\CategoryV1Model;
use App\Models\GoodsV1Model;
use App\Models\MedalReachNumModel;
use App\Models\ReportDataLogModel;
use App\Models\StudentMedalCategoryModel;
use App\Models\StudentMedalModel;

class MedalService
{
    //奖章活动类型
    const MEDAl_TASK = 7;
    //活动任务群体是时长有效用户(1所有2时长有效)
    const SUB_REMAIN_DURATION_VALID = 2;
    //奖章event设置field
    const EVERY_DAY_MEDAL_EVENT_SETTING = 'every_day_medal_event_setting';
    //奖章task设置field
    const EVERY_DAY_MEDAL_TASK_SETTING = 'every_day_medal_task_setting';
    //奖章task公用key
    const MEDAL_DAILY_KEY = 'medal_daily_key';
    //奖章信息公用key
    const MEDAL_INFO_KEY = 'medal_info_key';
    //奖章类别公用key
    const MEDAL_CATEGORY_KEY = 'medal_category_key';
    //天天成长奖章
    const DAY_DAY_GROW = 'sign_in_medal';
    //勤奋小标兵
    const DILIGENT_MODEL = 'play_piano_time_medal';
    //宣传小能手
    const ADVERTISE_EXPERT = 'share_grade_medal';
    //文艺大队长
    const ART_LEADER = 'both_hand_evaluate_medal';
    //曲谱小超人
    const OPERA_SUPERMAN = 'play_distinct_lesson_medal';
    //首次练琴
    const FIRST_PRACTICE_PIANO = 'finish_first_practice_medal';
    //签到玩家
    const SIGN_IN_PLAYER = 'receive_max_sign_award_medal';
    //王者起航
    const KING_SAIL = 'evaluate_zero_medal';
    //任务达人
    const TASK_TALENT = 'finish_var_task_count_medal';
    //音符大户
    const CREDIT_RICH = 'add_up_var_credit_medal';
    //知名人士奖章
    const FAMOUS_PERSON = 'change_thumb_and_name_medal';
    //奖章类别的总数
    const TOTAL_MEDAL_CATEGORY_NUM = 'total_medal_category_num';

    //每日上限计数的奖章field
    const EVERY_DAY_MEDAL_VALID_NUM = 'every_day_medal_valid_num';

    //这个类别有效计数存在erp积分账户表
    const CREDIT_RICH_CATEGORY_ID = 86;

    //所有奖章类型
    const ALL_MEDAL_RELATE_CLASS = [
        self::DAY_DAY_GROW => 'dayDayGrowAction',
        self::DILIGENT_MODEL => 'diligentModelAction',
        self::ADVERTISE_EXPERT => 'advertiseExpertAction',
        self::ART_LEADER => 'artLeaderAction',
        self::FIRST_PRACTICE_PIANO => 'firstPracticePianoAction',
        self::SIGN_IN_PLAYER => 'signInPlayerAction',
        self::KING_SAIL => 'kingSailAction',
        self::TASK_TALENT => 'taskTalentAction',
        self::CREDIT_RICH => 'creditRichAction',
        self::FAMOUS_PERSON => 'famousPersonAction'
    ];

    /**
     * @return array
     * 所有支持的奖章类型
     */
    private static function getAllMedalType()
    {
        return array_keys(self::ALL_MEDAL_RELATE_CLASS);
    }

    /**
     * @param $date
     * 设置奖章模板
     */
    public static function createEveryDayTask($date)
    {
        $redis = RedisDB::getConn();
        $erp = new Erp();
        $relateTasks = $erp->eventTaskList(0, self::MEDAl_TASK)['data'];
        //设置过期
        $endTime = strtotime($date) + 172800 - time();
        //整体奖章类别的总数
        $redis->set(self::TOTAL_MEDAL_CATEGORY_NUM, count($relateTasks));
        //事件对应奖章信息
        $allTaskArr = [];
        array_map(function ($item) use($redis, $date, $endTime, &$allTaskArr) {
            $allTaskArr = array_merge($item['tasks'], $allTaskArr);
            $key = self::getMedalRelateKey($item['id'], $date);
            $redis->hset($key, self::EVERY_DAY_MEDAL_EVENT_SETTING, $item['settings']);
            $redis->hset($key, self::EVERY_DAY_MEDAL_TASK_SETTING, json_encode($item['tasks']));
            return $redis->expire($key, $endTime);
        }, $relateTasks);
        //缓存奖章在弹窗的时候需要的信息
        $medalBaseInfo = array_column(GoodsV1Model::getMedalInfo(), NULL,'medal_id');
        //所有奖章的基本信息(用做奖章弹出的信息缓存)
        array_map(function ($info) use($redis, $medalBaseInfo) {
            $awardInfo = json_decode($info['award'], true)['awards'];
            if (!empty($awardInfo)) {
                foreach ($awardInfo as $award) {
                  if ($award['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                      $medalKey = self::getMedalInfoKey($award['course_id']);
                      $medalBase = $medalBaseInfo[$award['course_id']];
                      $medalBase['task_desc'] = $info['name'];
                      $redis->hset($medalKey,self::MEDAL_INFO_KEY, json_encode($medalBase));
                  }
                }
            }
        },$allTaskArr);
        //缓存奖章类别公共信息(用作展示提高速度)
        $allMedalCategoryInfo = CategoryV1Model::getMedalCategoryRelateInfo();
        $allMedalCategoryArr = [];
        if (!empty($allMedalCategoryInfo)) {
            foreach ($allMedalCategoryInfo as $medalInfo) {
                $allMedalCategoryArr[$medalInfo['category_id']][$medalInfo['medal_level'] ?: 0] = $medalInfo;
            }
        }
        array_walk($allMedalCategoryArr, function ($value, $k) use($redis) {
           $key = self::getMedalCategoryKey($k);
           $redis->hset($key, self::MEDAL_CATEGORY_KEY, json_encode($value));
        });
    }

    /**
     * @return string
     * 获取所有奖章类别的总数
     */
    public static function getTotalMedalCategoryNum()
    {
        $redis = RedisDB::getConn();
        return $redis->get(self::TOTAL_MEDAL_CATEGORY_NUM);
    }

    /**
     * @param $medalId
     * @return string
     * 某个奖章的详细信息
     */
    public static function getMedalIdInfo($medalId)
    {
        $key = self::getMedalInfoKey($medalId);
        $redis = RedisDB::getConn();
        return json_decode($redis->hget($key, self::MEDAL_INFO_KEY), true);
    }

    /**
     * @param null $date
     * @param $eventId
     * @return string|string[]
     * 活动模板相关key
     */
    private static function getMedalRelateKey($eventId, $date = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        return self::MEDAL_DAILY_KEY . '_' . $date . '_' . $eventId;
    }

    /**
     * @param null $date
     * @param $medalId
     * @return string|string[]
     * 奖章信息相关key
     */
    private static function getMedalInfoKey($medalId, $date = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        return self::MEDAL_INFO_KEY . '_' . $date . '_' . $medalId;
    }

    /**
     * @param null $date
     * @param $medalCategoryId
     * @return string|string[]
     * 奖章类别相关key
     */
    private static function getMedalCategoryKey($medalCategoryId, $date = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        return self::MEDAL_CATEGORY_KEY . '_' . $date . '_' . $medalCategoryId;
    }

    /**
     * @param $medalCategoryId
     * @return mixed
     * 获取某个奖章类别的信息
     */
    public static function getMedalCategoryInfo($medalCategoryId)
    {
        $key = self::getMedalCategoryKey($medalCategoryId);
        return json_decode(RedisDB::getConn()->hget($key, self::MEDAL_CATEGORY_KEY), true);
    }

    /**
     * @param $medalType
     * @return int
     * 定义奖章的类型在上报入库的时候对应的数字
     */
    public static function getMedalEnToNum($medalType)
    {
        $arr = [
            self::DAY_DAY_GROW => 83,
            self::DILIGENT_MODEL => 84,
            self::ADVERTISE_EXPERT => 85,
            self::ART_LEADER => 90,
            self::OPERA_SUPERMAN => 5, //此类奖章这次不做
            self::FIRST_PRACTICE_PIANO => 87,
            self::SIGN_IN_PLAYER => 88,
            self::KING_SAIL => 89,
            self::TASK_TALENT => 91,
            self::CREDIT_RICH => self::CREDIT_RICH_CATEGORY_ID,
            self::FAMOUS_PERSON => 92
        ];
        return $arr[$medalType] ?? 0;
    }

    /**
     * @param $type
     * @param $activityData
     * @return array
     * 处理奖章发放相关
     */
    public static function dealMedalGrantRelate($type, $activityData)
    {
        //当前上报的行为关联着那些奖章任务
        $medalTypeArr = self::getReportTypeRelateMedalType($type);
        $medalAward = [];
        if (!empty($medalTypeArr)) {
            foreach ($medalTypeArr as $medalType) {
                $action = self::ALL_MEDAL_RELATE_CLASS[$medalType];
                $medal = self::$action($medalType, $type, $activityData);
                (is_array($medal) && !empty($medal)) && $medalAward = $medalAward + $medal;
            }
        } else {
            SimpleLogger::info('not relate medal type: ', $type);
        }

        return $medalAward;
    }

    /**
     * @param $type
     * @return array|string[]
     * 上报不同的类型对应的可能获得的奖章
     */
    private static function getReportTypeRelateMedalType($type)
    {
        $relateMedal = [];
        $baseRelateMedal = [self::TASK_TALENT, self::CREDIT_RICH];
        if ($type == CreditService::SIGN_IN_TASKS) {
            $relateMedal = [self::DAY_DAY_GROW, self::SIGN_IN_PLAYER];
        } elseif ($type == CreditService::PLAY_PIANO_TASKS) {
            $relateMedal = [self::DILIGENT_MODEL];
        } elseif ($type == CreditService::BOTH_HAND_EVALUATE) {
            $relateMedal = [self::ART_LEADER, self::FIRST_PRACTICE_PIANO, self::KING_SAIL];
        } elseif ($type == CreditService::SHARE_GRADE) {
            $relateMedal = [self::ADVERTISE_EXPERT];
        } elseif ($type == CreditService::KNOW_CHART_PROMOTION) {
            $relateMedal = [self::FIRST_PRACTICE_PIANO];
        } elseif ($type == self::FAMOUS_PERSON) {
            return [self::FAMOUS_PERSON];
        }
        $returnInfo = [];
        !empty($baseRelateMedal) && $returnInfo = array_merge($relateMedal, $baseRelateMedal);
        return $returnInfo;
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 公共处理奖章(根据业务大部分可以走这个逻辑)
     */
    private static function commonDealMedal($medalType, $type, $activityData)
    {
        //当前是否已经超过单日计数上限，超过不再处理
        $relateMedalEvent = DictConstants::get(DictConstants::MEDAL_CONFIG, [
            $medalType
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        //当前上报超过每日上限/版本太低不再处理
        if (!self::judgeReportValidToMedal($relateMedalEventId, $activityData, $type)) {
            return;
        }
        //针对当前奖章对有效升级的累计有效计数
        $num = self::recordMedalReachNum($activityData['student_id'], $medalType);

        return self::relateVarReachNumGiveMedal($relateMedalEventId, $activityData, $num, $type);
    }

    /**
     * @param $relateMedalEventId
     * @param $activityData
     * @param $num
     * @param $type
     * @return array
     * 针对不同类型的奖章在用户达成不同数的情况下给不同等级
     */
    private static function relateVarReachNumGiveMedal($relateMedalEventId, $activityData, $num, $type)
    {
        $redis = RedisDB::getConn();
        //用来处理如果有每日计数上限的计数
        self::recordMedalDailyValidCount($relateMedalEventId, $activityData, $type);
        //记录相关上报信息(奖章增益有效数据记录)
        //因为同一个上报可能会触发多个奖章机制，存库数据缓存，防止同样的数据多次插入
        $key = $activityData['student_id'] . '_' . $type . '_' . md5(json_encode($activityData));
        $v = $redis->get($key);
        if (empty($v)) {
            $id = ReportDataLogModel::insertRecord(
                [
                    'report_type' => CreditService::getReportTypeZhToNum($type),
                    'student_id' => $activityData['student_id'],
                    'report_data' => json_encode($activityData),
                    'create_time' => time()
                ]
            );
            $redis->setex($key, 20, $id);
        } else {
            $id = $v;
        }
        //当前奖章的task
        $hasAchieveTask = [];
        $key = self::getMedalRelateKey($relateMedalEventId);
        $relateTask = json_decode($redis->hget($key, self::EVERY_DAY_MEDAL_TASK_SETTING), true);
        if (!empty($relateTask)) {
            //用户已经获取的奖章
            $studentMedalInfo = array_column(StudentMedalModel::getRecords(['student_id' => $activityData['student_id']]), 'medal_id');
            $erp = new Erp();
            foreach ($relateTask as $task) {
                $condition = json_decode($task['condition'], true);
                if ($num >= $condition['valid_num']) {
                    $awardInfo = json_decode($task['award'], true);
                    foreach ($awardInfo['awards'] as $award) {
                        if ($award['type'] == CategoryV1Model::MEDAL_AWARD_TYPE && !in_array($award['course_id'], $studentMedalInfo)) {
                            $hasAchieveTask[$task['id']] = $task['award'];
                            //记录用户所得奖章类别信息
                            $medalInfo = self::getMedalIdInfo($award['course_id']);
                            self::updateStudentMedalCategoryInfo($activityData['student_id'], $medalInfo['category_id']);
                            //记录用户所得奖章的详细信息
                            $isShow = self::getIsOrNotActive($type);
                            StudentMedalModel::insertRecord(
                                [
                                    'student_id' => $activityData['student_id'],
                                    'medal_id' => $award['course_id'],
                                    'medal_category_id' => $medalInfo['category_id'],
                                    'task_id' => $task['id'],
                                    'create_time' => time(),
                                    'report_log_id' => $id,
                                    'is_show' => $isShow
                                ]);
                            $erp->updateTask($activityData['uuid'], $task['id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
                        }
                    }
                }
            }
        }
        return $hasAchieveTask;
    }

    /**
     * @param $type
     * @return int
     * 主动上报会同步弹出，异步上报之后请求弹出
     */
    private static function getIsOrNotActive($type)
    {
        if (in_array($type, [CreditService::BOTH_HAND_EVALUATE, CreditService::PLAY_PIANO_TASKS])) {
            return StudentMedalModel::NOT_ACTIVE_SHOW;
        } else {
            return StudentMedalModel::IS_ACTIVE_SHOW;
        }
    }

    /**
     * @param $studentId
     * @param $medalCategoryId
     * 更新用户所得奖章类别信息
     */
    public static function updateStudentMedalCategoryInfo($studentId, $medalCategoryId)
    {
        $time = time();
        $info = StudentMedalCategoryModel::getRecord(['student_id' => $studentId, 'medal_category_id' => $medalCategoryId]);
        if (empty($info)) {
            StudentMedalCategoryModel::insertRecord([
                'student_id' => $studentId,
                'medal_category_id' => $medalCategoryId,
                'create_time' => $time,
                'update_time' => $time
            ]);
        } else {
            StudentMedalCategoryModel::updateRecord($info['id'], ['update_time' => $time]);
        }

    }


    /**
     * @param $studentId
     * @param $medalType
     * @return int|mixed
     * 各个奖章类别的有效计数(音符除外，在积分账户)
     */
    private static function recordMedalReachNum($studentId, $medalType)
    {
        $numInfo = MedalReachNumModel::getRecord(['student_id' => $studentId, 'medal_type' => self::getMedalEnToNum($medalType)]);
        $num = !empty($numInfo['valid_num']) ? $numInfo['valid_num'] + 1 : 1;
        if (empty($numInfo)) {
            MedalReachNumModel::insertRecord(['student_id' => $studentId, 'medal_type' => self::getMedalEnToNum($medalType), 'valid_num' => $num, 'create_time' => time(), 'update_time' => time()]);
        } else {
            MedalReachNumModel::updateRecord($numInfo['id'], ['valid_num' => $num, 'update_time' => time()]);
        }
        return $num;
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 天天成长
     */
    public static function dayDayGrowAction($medalType, $type, $activityData)
    {
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $eventId
     * @param $data
     * @param $reportType
     * @return bool
     * 检测当前的上报请求是否可用于奖章增益
     */
    public static function judgeReportValidToMedal($eventId, $data, $reportType = NULL)
    {
        $returnResult = true;
        $redis = RedisDB::getConn();
        $key = self::getMedalRelateKey($eventId);
        $eventSetting = json_decode($redis->hget($key, self::EVERY_DAY_MEDAL_EVENT_SETTING), true);
        //最小版本限制
        if (!CreditService::checkTaskQualification($eventSetting, $data)) {
            $returnResult = false;
        }

        //没有当日上限要求
        if (!empty($eventSetting['every_day_count'])){
            $recordKey = self::getMedalStudentRelateKey($eventId, $data['student_id']);
            $recordNum = $redis->hget($recordKey, self::EVERY_DAY_MEDAL_VALID_NUM);
            if (intval($eventSetting['every_day_count']) <= intval($recordNum)) {
                $returnResult = false;
            }
        }

        //上传的连续签到多少天的要求
        if (isset($eventSetting['continue_days']) && $data['continue_days'] < $eventSetting['continue_days']) {
            $returnResult = false;
        }
        //最低分数
        if (isset($eventSetting['min_grade_gt'])) {
            //特殊处理首次练琴奖章(对于特定的上报类型不校验分数)
            if (isset($eventSetting['green_light_report_type']) && !in_array($reportType, explode(',', $eventSetting['green_light_report_type']))) {
                if ($data['score_final'] <= $eventSetting['min_grade_gt']) {
                    $returnResult = false;
                }
            }
        }
        //王者起航0分
        if (isset($eventSetting['min_grade_eq']) && $data['score_final'] != $eventSetting['min_grade_eq']) {
            $returnResult = false;
        }

        if (!$returnResult) {
            SimpleLogger::info('now report info not satisfy medal setting:', ['event_id' => $eventId, 'activity_data' => $data]);
        }
        return $returnResult;
    }

    /**
     * @param $eventId
     * @param $activityData
     * @param $type
     * 有每日上限要求的奖章计数
     */
    private static function recordMedalDailyValidCount($eventId, $activityData, $type)
    {
        $redis = RedisDB::getConn();
        $key = self::getMedalRelateKey($eventId);
        $eventSetting = json_decode($redis->hget($key, self::EVERY_DAY_MEDAL_EVENT_SETTING), true);
        //如果当前的奖章event对当日上限有要求才记录，没有就直接跳回
        if (empty($eventSetting['every_day_count'])){
            return;
        }
        $recordKey = self::getMedalStudentRelateKey($eventId, $activityData['student_id']);
        $recordNum = $redis->hget($recordKey, self::EVERY_DAY_MEDAL_VALID_NUM);
        if (empty($recordNum)) {
            $redis->hset($recordKey, self::EVERY_DAY_MEDAL_VALID_NUM, 1);
        } else {
            $redis->hset($recordKey, self::EVERY_DAY_MEDAL_VALID_NUM, intval($recordNum) + 1);
        }
        //设置过期
        $endTime = strtotime(date('Y-m-d')) + 172800 - time();
        $redis->expire($recordKey, $endTime);
    }

    /**
     * @param $eventId
     * @param $studentId
     * @param null $data
     * @return string
     * 每日计数上限的event相关的用户key
     */
    private static function getMedalStudentRelateKey($eventId, $studentId, $data = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        return self::MEDAL_DAILY_KEY . '_' . $date . '_' . $eventId . '_' . $studentId;
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 签到玩家
     */
    public static function signInPlayerAction($medalType, $type, $activityData)
    {
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 任务达人
     */
    public static function taskTalentAction($medalType, $type, $activityData)
    {
        //当前是否已经超过单日计数上限，超过不再处理
        $relateMedalEvent = DictConstants::get(DictConstants::MEDAL_CONFIG, [
            $medalType
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        //当前上报超过每日上限/版本太低不再处理
        if (!self::judgeReportValidToMedal($relateMedalEventId, $activityData)) {
            return;
        }
        $numInfo = MedalReachNumModel::getRecord(['student_id' => $activityData['student_id'], 'medal_type' => self::getMedalEnToNum($medalType)]);
        $num = !empty($numInfo['valid_num']) ? $numInfo['valid_num'] : 0;
        return self::relateVarReachNumGiveMedal($relateMedalEventId, $activityData, $num, $type);
    }

    /**
     * @param $activityData
     * 每日活动完成以后，需要针对任务达人的奖章记录完成的任务数
     */
    public static function recordMedalRelateTaskCount($activityData)
    {
        $medalType = self::TASK_TALENT;
        $relateMedalEvent = DictConstants::get(DictConstants::MEDAL_CONFIG, [
            $medalType
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        //当前上报超过每日上限/版本太低不再处理
        if (!self::judgeReportValidToMedal($relateMedalEventId, $activityData)) {
            return;
        }
        //针对当前奖章对有效升级的累计有效计数
        self::recordMedalReachNum($activityData['student_id'], $medalType);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 音符大户
     */
    public static function creditRichAction($medalType, $type, $activityData)
    {
        //当前是否已经超过单日计数上限，超过不再处理
        $relateMedalEvent = DictConstants::get(DictConstants::MEDAL_CONFIG, [
            $medalType
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        //当前上报超过每日上限/版本太低不再处理
        if (!self::judgeReportValidToMedal($relateMedalEventId, $activityData)) {
            return;
        }
        //当前用户累计获得的音符
        $numInfo = (new Erp())->getUserAddUpCredit(['uuid' => $activityData['uuid']]);
        if ($numInfo['code'] != Erp::RSP_CODE_SUCCESS) {
            SimpleLogger::info('erp count num error:', ['info' => $numInfo]);
        }
        return self::relateVarReachNumGiveMedal($relateMedalEventId, $activityData, intval($numInfo['data']['total_num'] /100), $type);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 勤奋小标兵
     */
    public static function diligentModelAction($medalType, $type, $activityData)
    {
        $relateMedalEvent = DictConstants::get(DictConstants::MEDAL_CONFIG, [
            $medalType
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        $redis = RedisDB::getConn();
        $key = self::getMedalRelateKey($relateMedalEventId);
        $eventSetting = json_decode($redis->hget($key, self::EVERY_DAY_MEDAL_EVENT_SETTING), true);
        //检测是否通过此奖章硬性限制
        if (!empty($eventSetting['start_time']) && (time() <= strtotime($eventSetting['start_time']))) {
            SimpleLogger::info('not reach require time', ['activity data' => $activityData]);
        }
        //每日有效计数上限
        if (!empty($eventSetting['every_day_count'])){
            $recordKey = self::getMedalStudentRelateKey($relateMedalEventId, $activityData['student_id']);
            $recordNum = $redis->hget($recordKey, self::EVERY_DAY_MEDAL_VALID_NUM);
            if (intval($recordNum) >= intval($eventSetting['every_day_count'])) {
                SimpleLogger::info('gt every day limit', ['activity_data' => $activityData]);
                return;
            }
        }
        //弹奏时长
        if ($activityData['play_duration'] < $eventSetting['play_duration']) {
            SimpleLogger::info('not reach min play duration', ['activity data' => $activityData]);
        }
        //针对当前奖章对有效升级的累计有效计数
        $num = self::recordMedalReachNum($activityData['student_id'], $medalType);
        //用户版本在最低版本之上才可以触发发放奖章
        if (!CreditService::checkTaskQualification($eventSetting, $activityData)) {
            SimpleLogger::info('not reach min version', ['activity data' => $activityData, 'medal type' => $medalType]);
        }
        return self::relateVarReachNumGiveMedal($relateMedalEventId, $activityData, $num, $type);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 文艺大队长
     */
    public static function artLeaderAction($medalType, $type, $activityData)
    {
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 王者起航
     */
    public static function kingSailAction($medalType, $type, $activityData)
    {
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 宣传小能手
     */
    public static function advertiseExpertAction($medalType, $type, $activityData)
    {
        //此奖章需要去除已经产生增益的可分享成绩id
        $existInfo = ReportDataLogModel::getGradeRecord($activityData['student_id'], $activityData['play_grade_id']);
        if (!empty($existInfo)) {
            SimpleLogger::info('not repeat use', ['activity_data' => $activityData]);
            return;
        }
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 首次练琴
     */
    public static function firstPracticePianoAction($medalType, $type, $activityData)
    {
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $medalType
     * @param $type
     * @param $activityData
     * @return array|void
     * 知名人士奖章
     */
    public static function famousPersonAction($medalType, $type, $activityData)
    {
        //此奖章需要去除已经产生增益的分享类型
        $existInfo = ReportDataLogModel::getChangeRecord($activityData['student_id'], $activityData['change_type']);
        if (!empty($existInfo)) {
            SimpleLogger::info('not repeat use', ['activity_data' => $activityData]);
            return;
        }
        return self::commonDealMedal($medalType, $type, $activityData);
    }

    /**
     * @param $studentId
     * @return array|string[]
     * 当前学生需要弹出的奖章内容
     */
    public static function getNeedAlertMedal($studentId)
    {
        $returnInfo = [];
        $list = StudentMedalModel::getNeedAlertMedalInfo($studentId);
        if (!empty($list)) {
            $returnInfo = array_map(function ($item) {
                return self::formatMedalAlertInfo($item['medal_id']);
            }, $list);
        }
        StudentMedalModel::batchUpdateRecord(['is_show' => StudentMedalModel::IS_ACTIVE_SHOW], ['student_id' => $studentId, 'medal_category_id' => array_column($returnInfo, 'category_id')]);
        return $returnInfo;
    }

    /**
     * @param $medalId
     * @return string
     * 格式化奖章弹出需要的信息
     */
    public static function formatMedalAlertInfo($medalId)
    {
        $info = self::getMedalIdInfo($medalId);
        $info['thumbs'] = AliOSS::replaceCdnDomainForDss(json_decode($info['thumbs'], true)[0]);
        $info['medal_desc'] = (is_null($info['medal_level']) || $info['medal_level'] <= 1) ? '获得新奖章！' : '奖章升级！';
        return $info;
    }

    /**
     * @param $studentId
     * @param $medalCategoryId
     * @param $uuId
     * @return array
     * 用户对某类奖章的获取情况
     */
    public static function getUserMedalCategoryGainInfo($studentId, $medalCategoryId, $uuId)
    {
        //当前奖章类别的基础信息
        $medalCategoryBaseInfo = self::getMedalCategoryInfo($medalCategoryId);
        //奖章的有效计数(音符相关有效计数在erp)
        if ($medalCategoryId != self::CREDIT_RICH_CATEGORY_ID) {
            $num = MedalReachNumModel::getRecord(['student_id' => $studentId, 'medal_type' => $medalCategoryId], 'valid_num') ?: 0;
        } else {
            $num = intval((new Erp())->getUserAddUpCredit(['uuid' => $uuId])['data']['total_num'] / 100);
        }
        //已获得奖章
        $hasGetMedalIdInfo = array_column(StudentMedalModel::getRecords(['student_id' => $studentId, 'medal_category_id' => $medalCategoryId], ['medal_id', 'create_time']), 'create_time','medal_id');
        $returnInfo = [];
        if (!empty($medalCategoryBaseInfo)) {
            $returnInfo['medal_category_name'] = reset($medalCategoryBaseInfo)['category_name'];
            $returnInfo['category_desc'] = reset($medalCategoryBaseInfo)['category_desc'];
            $returnInfo['every_day_count'] = reset($medalCategoryBaseInfo)['every_day_count'] > 1 ? reset($medalCategoryBaseInfo)['every_day_count'] : '';
            $returnInfo['valid_num'] = $num;
            $medalGetStatus = [];
            foreach ($medalCategoryBaseInfo as $v) {
                //奖章的基本信息
                $medalGetStatus[] = [
                    'thumbs' => AliOSS::replaceCdnDomainForDss(json_decode($v['thumbs'], true)[0]),
                    'task_desc' => $v['name'],
                    'get_time' => !empty($hasGetMedalIdInfo[$v['medal_id']]) ? date('Y.m.d', $hasGetMedalIdInfo[$v['medal_id']]) : '',
                    'reach_num' => $v['reach_num']
                ];
            }
            $returnInfo['medal_category_detail'] = $medalGetStatus;
        }
        return $returnInfo;
    }

    /**
     * @param $studentId
     * @param $categoryId
     * @throws RunTimeException
     * 设置默认奖章
     */
    public static function setUserDefaultMedalCategory($studentId, $categoryId)
    {
        //是否拥有
        $record = StudentMedalCategoryModel::getRecord(['student_id' => $studentId, 'medal_category_id' => $categoryId]);
        if (empty($record)) {
            throw new RunTimeException(['user_not_own_medal_category']);
        }
        if ($record['is_default'] != StudentMedalCategoryModel::DEFAULT_SHOW) {
            $info = StudentMedalCategoryModel::getRecord(['student_id' => $studentId, 'is_default' => StudentMedalCategoryModel::DEFAULT_SHOW]);
            if (!empty($info)) {
                StudentMedalCategoryModel::updateRecord($info['id'], ['is_default' => StudentMedalCategoryModel::NOT_SHOW]);
            }
            StudentMedalCategoryModel::batchUpdateRecord(['is_default' => StudentMedalCategoryModel::DEFAULT_SHOW],['student_id' => $studentId, 'medal_category_id' => $categoryId]);
        }
    }

}
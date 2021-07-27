<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\CountingActivityModel;
use App\Models\CountingActivitySignModel;
use App\Models\CountingActivityUserStatisticModel;
use App\Models\SharePosterModel;
use Medoo\Medoo;

class CountingActivitySignService
{


    public static $now;
    public static $allActivity_list;

    /**
     * 统计参与的报名活动信息
     * @param $studentId
     * @param null $statistics_time
     * @return false|int|mixed|string|null
     */
    public static function countSign($studentId, $statistics_time = null){
        self::$now = time();
        //查询是否报名
        $signList = CountingActivitySignModel::getRecords(['student_id'=>$studentId, 'qualified_status'=>CountingActivitySignModel::QUALIFIED_STATUS_NO, 'award_status'=>CountingActivitySignModel::AWARD_STATUS_NOT_QUALIFIED]);

        if(empty($signList)){
            SimpleLogger::error('用户未报名活动',[$studentId]);
            return false;
        }
        $activityIds = array_column($signList, 'op_activity_id');

        //查询报名的活动是否有效
        $activityList = CountingActivityModel::getRecords(['op_activity_id[in]'=>$activityIds, 'status'=>CountingActivityModel::NORMAL_STATUS, 'sign_end_time[>]'=>self::$now,'join_end_time[>]'=>self::$now, 'start_time[<]'=>self::$now], ['op_activity_id','nums','rule_type']);
        $activityList = array_column($activityList, null, 'op_activity_id');

        if(empty($activityList)){
            SimpleLogger::error('用户报名的计数任务不存在',[$activityIds]);
            return false;
        }

        //全量统计
        list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum(['student_id' => $studentId],0,0);
        $res = self::incStatic($studentId, $cumulativeNum, $continuityNum);
        if(!$res){
            SimpleLogger::error('insert Statistic fail',[$studentId, $cumulativeNum, $continuityNum]);
        }

        foreach ($signList as $one){

            if(!isset($activityList[$one['op_activity_id']])) {
                SimpleLogger::error('empty counting_activity',$one);
                continue; //报名活动不存在则跳过
            }
            //获取连续和累计
            if(is_null($statistics_time)){//正常流程
                list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($one, $one['create_time'], 10);
                $res = self::updateSign($one, $activityList[$one['op_activity_id']], $cumulativeNum, $continuityNum);
                if(!$res){
                    SimpleLogger::error('edit sign fail',[$one, $cumulativeNum, $continuityNum]);
                }
            }else{//脚本执行指定时间
                list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($one, $statistics_time,10);
                $res = self::updateSign($one, $activityList[$one['op_activity_id']], $cumulativeNum, $continuityNum);
                if(!$res){
                    SimpleLogger::error('update history sign fail',[$one, $cumulativeNum, $continuityNum]);
                }
            }
        }
    }

    /**
     * 累加统计表
     * @param $student_id
     * @param $cumulativeNum
     * @param $continuityNum
     */
    public static function incStatic($student_id, $cumulativeNum, $continuityNum){
        $first = CountingActivityUserStatisticModel::getRecord(['student_id'=>$student_id]);

        $data = [
            'cumulative_nums'   => $cumulativeNum,
            'continue_nums'     => $continuityNum,
        ];

        if(empty($first)){
            $data['student_id'] = $student_id;
            return CountingActivityUserStatisticModel::insertRecord($data);
        }else{
            return CountingActivityUserStatisticModel::updateRecord($first['id'], $data);
        }
    }

    /**
     * 更新报名表
     * @param $sign
     * @param $activity
     * @param $now
     * @return int|null
     */
    public static function updateSign($sign, $activity, $cumulativeNum, $continuityNum){

        $now = self::$now;
//        echo json_encode([$cumulativeNum, $continuityNum]);die;
        $updateData = [
            'cumulative_nums' => $cumulativeNum,
            'continue_nums'   => $continuityNum,
        ];

        //是否累计 & 是否达标
        $cumulativeNumIsComplete = $cumulativeNum >= $activity['nums'] && $activity['rule_type'] == CountingActivityModel::RULE_TYPE_COUNT;
        $continuityNumIsComplete = $continuityNum >= $activity['nums'] && $activity['rule_type'] == CountingActivityModel::RULE_TYPE_CONTINU;

        if($cumulativeNumIsComplete || $continuityNumIsComplete){
            $updateData['cumulative_nums']  = $activity['nums'];
            $updateData['continue_nums']    = $activity['nums'];
            $updateData['qualified_status'] = CountingActivitySignModel::QUALIFIED_STATUS_YES;
            $updateData['award_status']     = CountingActivitySignModel::AWARD_STATUS_PENDING;
            $updateData['qualified_time']   = $now;
        }

        $where = [
            'student_id'=>$sign['student_id'],
            'op_activity_id'=>$activity['op_activity_id']
        ];

        return CountingActivitySignModel::batchUpdateRecord($updateData, $where);

    }

    public static function getAllActivity($pageSize){
        if(!isset(self::$allActivity_list[$pageSize])){
            $activityList = SharePosterModel::getAllWeekActivity($pageSize);
            self::$allActivity_list[$pageSize] = $activityList;
        }
        return self::$allActivity_list[$pageSize];
    }

    /**
     * 获取活动总数
     * @param $student_id
     * @return int
     */
    public static function getContinuityActivityNum($sign, $startTime, $pageSize){
        //查询所有周周领奖有效活动
        $activityList = self::getAllActivity($pageSize);
        $ids = implode(',', array_column($activityList, 'activity_id'));
        $sharePosterList = SharePosterModel::getStudentSignActivity($ids,$sign['student_id'],$startTime);
        $sharePosterList = array_column($sharePosterList,null, 'activity_id');


//        echo json_encode([$sharePosterList, $activityList]);die;
        $count = 0;
        foreach ($activityList as $one){
            if(isset($sharePosterList[$one['activity_id']])){
                $count ++;
            }else{
                if($one['end_time'] < self::$now){//如果没参加且当前活动未结束，则继续统计
                    break;
                }
            }
        }
        return [count($sharePosterList), $count];
    }


    /**
     * 刷新达标期数
     * @param $countingId
     * @return false
     */
    public static function refreshCountingNum($countingId){

        $activity = CountingActivityModel::getById($countingId);

        if(empty($activity)){
            SimpleLogger::error('活动不存在或已关闭',[$countingId, $activity]);
            return false;
        }
        $where = [
            'GROUP' => 'student_id',
            'op_activity_id'    => $activity['op_activity_id'],
            'qualified_status'  => CountingActivitySignModel::QUALIFIED_STATUS_NO
        ];
        $signList = CountingActivitySignModel::getRecords($where, ['student_id','create_time']);

        foreach ($signList as $sign){
            $where = [
                'student_id' => $sign['student_id'],
                'create_time[>=]' => $sign['create_time'],
                'GROUP' => 'student_id',
                'type'            => SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status'   => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                'HAVING'=>[
                    'c[>=]'=>$activity['nums'],
                ],
            ];

            $count = SharePosterModel::getRecord($where,['c'=>Medoo::raw('count(id)'),'student_id']);
            list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($sign, $sign['create_time'],10);
            if($count){
                self::updateSign($sign, $activity, $cumulativeNum, $continuityNum);
            }
        }
    }

    /**
     * 报名统计
     * @param $signId
     * @return mixed
     */
    public static function signAction($signId){
        $sign = CountingActivitySignModel::getById($signId);
        self::$now = time();

            if(empty($sign)){
                throw new RuntimeException(['empty sign']);
            }

            $activityInfo = CountingActivityModel::getRecord(['op_activity_id'=>$sign['op_activity_id'], 'status'=>CountingActivityModel::NORMAL_STATUS, 'sign_end_time[>]'=>self::$now,'join_end_time[>]'=>self::$now, 'start_time[<]'=>self::$now], ['op_activity_id','start_time','nums','rule_type']);

            if(empty($activityInfo)){
                throw new RuntimeException(['empty countingActivity']);
            }

            list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($sign, $activityInfo['start_time'],10);


            self::updateSign($sign, $activityInfo, $cumulativeNum, $continuityNum);


    }

}

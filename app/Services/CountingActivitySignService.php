<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
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
    public static $activity_ids_key = 'week_activity_id_list_cache_key';

    /**
     * 统计参与的报名活动信息
     * @param $studentId
     * @param null $statistics_time
     * @return false|int|mixed|string|null
     */
    public static function countSign($studentId, $statistics_time = null, $op_activity_id = 0){
        self::$now = time();

        self::cache_op_activity_ids([$op_activity_id]);

        //全量统计
        list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum(['student_id' => $studentId], 0, 0, self::$now);
         
        $res = self::incStatic($studentId, $cumulativeNum, $continuityNum);
        if(!$res){
            SimpleLogger::error('insert Statistic fail',[$studentId, $cumulativeNum, $continuityNum]);
        }

        //查询是否报名
        $signList = CountingActivitySignModel::getRecords(['student_id'=>$studentId, 'qualified_status'=>CountingActivitySignModel::QUALIFIED_STATUS_NO, 'award_status'=>CountingActivitySignModel::AWARD_STATUS_NOT_QUALIFIED]);
        if(empty($signList)){
            SimpleLogger::error('用户未报名活动',[$studentId]);
            return false;
        }
        $activityIds = array_column($signList, 'op_activity_id');

        //查询报名的活动是否有效
        $activityList = CountingActivityModel::getRecords(['op_activity_id[in]'=>$activityIds], ['op_activity_id','start_time','join_end_time','nums','rule_type']);
        $activityList = array_column($activityList, null, 'op_activity_id');

        if(empty($activityList)){
            SimpleLogger::error('用户报名的计数任务不存在',[$activityIds]);
            return false;
        }

        foreach ($signList as $one){

            if(!isset($activityList[$one['op_activity_id']])) {
                SimpleLogger::error('empty counting_activity',$one);
                continue; //报名活动不存在则跳过
            }

            $activity = $activityList[$one['op_activity_id']];

            //获取连续和累计
            if(is_null($statistics_time)){//正常流程

                list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($one, $activity['start_time'], 10, $activity['join_end_time']);

                $res = self::updateSign($one, $activity, $cumulativeNum, $continuityNum);
                if(!$res){
                    SimpleLogger::error('edit sign fail',[$one, $cumulativeNum, $continuityNum]);
                }
            }else{//脚本执行指定时间
                list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($one, $statistics_time, 10, self::$now);
                $res = self::updateSign($one, $activity, $cumulativeNum, $continuityNum);
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
            if(isset($sign['award_status']) && $sign['award_status'] == CountingActivitySignModel::AWARD_STATUS_NOT_QUALIFIED){
                $updateData['award_status'] = CountingActivitySignModel::AWARD_STATUS_PENDING;
            }

            $updateData['cumulative_nums']  = $activity['nums'];
            $updateData['continue_nums']    = $activity['nums'];
            $updateData['qualified_status'] = CountingActivitySignModel::QUALIFIED_STATUS_YES;
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
            $redis = RedisDB::getConn();
            $ids = $redis->smembers(self::$activity_ids_key);

            $activityList = SharePosterModel::getAllWeekActivity($ids, $pageSize);
            self::$allActivity_list[$pageSize] = $activityList;
        }
        return self::$allActivity_list[$pageSize];
    }

    /**
     * 获取活动总数
     * @param $sign
     * @param $startTime
     * @param $pageSize
     * @param $end_time
     * @return array
     */
    public static function getContinuityActivityNum($sign, $startTime, $pageSize, $end_time){
        //查询所有周周领奖有效活动
        $activityList = self::getAllActivity($pageSize);
        $ids = implode(',', array_column($activityList, 'activity_id'));
        $sharePosterList = SharePosterModel::getStudentSignActivity($ids, $sign['student_id'], $startTime, $end_time);
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
//            'qualified_status'  => CountingActivitySignModel::QUALIFIED_STATUS_NO
        ];
        $signList = CountingActivitySignModel::getRecords($where, ['student_id','create_time','award_status']);

        foreach ($signList as $sign){
            $where = [
                'student_id' => $sign['student_id'],
                'create_time[>=]' => $activity['start_time'],
                'GROUP' => 'student_id',
                'type'            => SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status'   => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                'HAVING'=>[
                    'c[>=]'=>$activity['nums'],
                ],
            ];

            $count = SharePosterModel::getRecord($where,['c'=>Medoo::raw('count(id)'),'student_id']);

            list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($sign, $activity['start_time'], 10,$activity['join_end_time']);


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
        try{
            if(empty($sign)){
                throw new RuntimeException(['empty sign']);
            }

            $activityInfo = CountingActivityModel::getRecord(['op_activity_id'=>$sign['op_activity_id']], ['op_activity_id','start_time','nums','rule_type','join_end_time']);

            if(empty($activityInfo)){
                throw new RuntimeException(['empty countingActivity']);
            }

            list($cumulativeNum, $continuityNum) = self::getContinuityActivityNum($sign, $activityInfo['start_time'], 10, $activityInfo['join_end_time']);

            self::updateSign($sign, $activityInfo, $cumulativeNum, $continuityNum);
        }catch (RuntimeException $e){
            SimpleLogger::error('报名更新计数任务失败',[$signId]);
        }

    }


    public static function cache_op_activity_ids(array $ids){
        $redis = RedisDB::getConn();

        if(empty($redis->smembers(self::$activity_ids_key))){
            $where = [
                'type'=>SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            ];
            $ids = SharePosterModel::getRecords($where,['activity_id'=>Medoo::raw('distinct activity_id')]);
            $ids = array_column($ids, 'activity_id');

        }

        $redis->sadd(self::$activity_ids_key, $ids);
    }
}

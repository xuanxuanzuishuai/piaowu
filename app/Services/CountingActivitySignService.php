<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\CountingActivityModel;
use App\Models\CountingActivitySignModel;
use App\Models\SharePosterModel;
use Medoo\Medoo;

class CountingActivitySignService
{
    /**
     * 统计参与的报名活动信息
     * @param int $studentId
     */
    public static function countSign($studentId){

        $now = time();

        $signList = CountingActivitySignModel::getRecords(['student_id'=>$studentId, 'qualified_status'=>CountingActivitySignModel::QUALIFIED_STATUS_NO, 'award_status'=>CountingActivitySignModel::AWARD_STATUS_NOT_QUALIFIED]);

        if(empty($signList)){
            SimpleLogger::error('用户未报名活动',[$studentId]);
            return false;
        }
        $activityIds = array_column($signList, 'op_activity_id');
        $activityList = CountingActivityModel::getRecords(['op_activity_id[in]'=>$activityIds, 'status'=>CountingActivityModel::NORMAL_STATUS, 'sign_end_time[>]'=>$now, 'start_time[<]'=>$now], ['op_activity_id','nums','rule_type']);
        $activityList = array_column($activityList, null, 'op_activity_id');

        if(empty($activityList)){
            SimpleLogger::error('用户报名的计数任务不存在',[$activityIds]);
            return false;
        }

        foreach ($signList as $one){
            if(!isset($activityList[$one['op_activity_id']])) {
                SimpleLogger::error('empty counting_activity',$one);
            }else{
                $res = self::updateSign($one, $activityList[$one['op_activity_id']], $now);
                if(!$res){
                    SimpleLogger::error('更新计数任务统计失败',[$one, $activityList[$one['op_activity_id']], $now]);
                }
                return $res;
            }
        }


    }

    /**
     * 更新报名表
     * @param $sign
     * @param $activity
     * @param $now
     * @return int|null
     */
    public static function updateSign($sign, $activity, $now){
        $cumulativeNum = self::getCumulativeNum($sign);
        $continuityNum = self::getContinuityActivityNum($sign);

//        echo json_encode([$cumulativeNum, $continuityNum]);die;
        $updateData = [
            'cumulative_nums' => $cumulativeNum,
            'continue_nums'   => $continuityNum,
        ];

        //是否累计 & 是否达标
        $cumulativeNumIsComplete = $cumulativeNum >= $activity['nums'] && $activity['rule_type'] == CountingActivityModel::RULE_TYPE_COUNT;
        $continuityNumIsComplete = $continuityNum >= $activity['nums'] && $activity['rule_type'] == CountingActivityModel::RULE_TYPE_CONTINU;

        if($cumulativeNumIsComplete || $continuityNumIsComplete){
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

    /**
     * 获取累计活动
     * @param $student_id
     * @return int|number
     */
    public static function getCumulativeNum($sign){
        $where = [
            'student_id' => $sign['student_id'],
            'type'       => SharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'create_time[>=]'=>$sign['create_time']
        ];
        return SharePosterModel::getCount($where);
    }

    /**
     * 获取连续性活动
     * @param $student_id
     * @return int
     */
    public static function getContinuityActivityNum($sign){
        //查询周周领奖有效活动
        $sql = "SELECT wa.activity_id,wa.start_time, wa.end_time FROM week_activity as wa INNER JOIN share_poster as sp 
                on wa.activity_id=sp.activity_id where type = " . SharePosterModel::TYPE_WEEK_UPLOAD
                . " AND verify_status= " . SharePosterModel::VERIFY_STATUS_QUALIFIED
                . " GROUP BY wa.activity_id ORDER BY wa.start_time DESC LIMIT 10";

        $db = MysqlDB::getDB();
        $activityList = $db->queryAll($sql);

        $ids = array_column($activityList, 'activity_id');

        $where = [
            'activity_id[in]' => $ids,
            'student_id'      => $sign['student_id'],
            'type'            => SharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status'   => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'ORDER'           => ['id'=>'DESC'],
            'create_time[>=]'=>$sign['create_time']
        ];
        //查询当前用户参加的活动
        $sharePosterList = SharePosterModel::getRecords($where, ['activity_id', 'create_time']);

        $count = 0;

        foreach ($sharePosterList as $k => $one){
            $week_activity = $activityList[$k];
            //1. 学生参与活动列表 和 所有活动列表 都按照时间降序排序
            //2. 对比用户参与活动和所有活动的 start_time、end_time、活动ID 是否都能按顺序对上
            //3. 为true表示存在连续性
            if($week_activity['activity_id'] == $one['activity_id']){
                $count ++;
            }else{
                //不存在表示连续性中断
                break;
            }
        }
        return $count;
    }


    public static function refreshCountingNum($countingId){

        $activity = CountingActivityModel::getById($countingId);

        if(empty($activity)){
            SimpleLogger::error('活动不存在或已关闭',[$countingId, $activity]);
            return false;
        }
        $where = [
            'op_activity_id' => $activity['op_activity_id'],
            'GROUP' => 'student_id',
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

            if($count){
                self::updateSign($sign, $activity, time());
            }
        }
    }


}

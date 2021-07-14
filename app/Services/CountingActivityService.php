<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\CountingActivityModel;
use App\Models\CountingActivityMutesModel;
use App\Models\CountingActivitySignModel;
use App\Models\CountingAwardConfigModel;
use App\Models\OperationActivityModel;


class CountingActivityService
{


    /**
     * 获取奖品列表
     * @param $params
     * @return array
     */
    public static function getAwardList($params){
        $where = [];
        if(!empty($params['goods_id'])){
            $where['goods_id'] = $params['goods_id'];
        }

        if(!empty($params['goods_name'])) {
            $where['goods_name'] = $params['goods_name'];
        }
        if(!empty($params['category_id'])) {
            $where['category_id'] = $params['category_id'];
        }

        if(empty($where)){
            return [];
        }

        $where['status'] = 1;

        $erp = new Erp();
        $list = $erp->getLogisticsGoodsList($where, $where);

        return compact('list');

    }


    /**
     * 更新活动状态
     * @param $op_activity_id
     * @param $status
     * @return false|int|null
     */
    public static function editStatus($op_activity_id, $status){
        if(!in_array($status, [CountingActivityModel::ENABLE_STATUS, CountingActivityModel::DISABLE_STATUS])){
           return false;
        }

        $activityInfo = CountingActivityModel::getRecord(['id'=>$op_activity_id],['first_effect_time','status']);
        if($activityInfo['status'] == $status){
            return false;
        }

        $data = [
            'status'=>$status
        ];

        $now = time();
        if($activityInfo['first_effect_time'] == 0 && $status == CountingActivityModel::ENABLE_STATUS){
            $data['first_effect_time'] = $now;
        }

        $data['update_time'] = $now;
        return CountingActivityModel::updateRecord($op_activity_id, $data);
    }
    /**
     * 获取列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getCountingList($params, $page, $pageSize){

        $where = [];
        if(!empty($params['name'])){
            $where['name[~]'] = $params['name'];
        }
        if(!empty($params['op_activity_id'])){
            $where['op_activity_id'] = $params['op_activity_id'];
        }
        if(!empty($params['type'])){
            $where['type'] = $params['type'];
        }
        if(!empty($params['status'])){
            $where['status'] = $params['status'];
        }
        if (!empty($params['sign_end_time_s'])) {
            $where['sign_end_time[>=]'] = $params['sign_end_time_s'];
        }
        if (!empty($params['sign_end_time_e'])) {
            $where['sign_end_time[<=]'] = $params['sign_end_time_e'];
        }
        if (!empty($params['join_end_time_s'])) {
            $where['join_end_time[>=]'] = $params['join_end_time_s'];
        }
        if (!empty($params['join_end_time_s'])) {
            $where['join_end_time[<=]'] = $params['join_end_time_s'];
        }

        $total = CountingActivityModel::getCount($where);

        if ($total <= 0) {
            return [[], 0];
        }
        $where['ORDER'] = ['id' => 'DESC'];

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];

        $list = CountingActivityModel::getRecords($where);

        return compact('list', 'total');

    }
    /**
     * 创建活动
     * @param $data
     * @param $operator_id
     * @return bool
     */
    public static function createCountingActivity($data, $operator_id){

        $now = time();
        $db = MysqlDB::getDB();

        //判断互斥活动是否合法
        if(!empty($data['mutex_activity_ids'])){
            $info = CountingActivityModel::getRecord(['op_activity_id'=>$data['mutex_activity_ids']], ['op_activity_id']);

            if(empty($info)){
                SimpleLogger::error('get mutex_activity_ids is empty', $data['mutex_activity_ids']);
                return false;
            }
            $diffArr = array_diff($data['mutex_activity_ids'], array_column($info, null, 'op_activity_id'));

            if(!empty($diffArr)){
                SimpleLogger::error('get mutex_activity_ids is empty', $diffArr);
                return false;
            }
        }

        try {
            $db->beginTransaction();

            //add 活动表
            $opActivityId = self::addActivity($data, $now);
            if(!$operator_id){
                throw new RunTimeException('error: insert OperationActivity fail');
            }

            //add counting_activity 主表信息
            $data['op_activity_id'] = $opActivityId;
            $countActivityId = CountingActivityModel::createActivity($data, $operator_id, $now);
            if(!$countActivityId){
                throw new RunTimeException('error: insert CountingActivity fail');
            }

            //add 互斥信息
            if (!empty($data['mutex_activity_ids'])){
                $inRes = self::addMutexInfo($data, $opActivityId, $now, $operator_id);
                if(!$inRes){
                    throw new RunTimeException('error: insert CountingActivityMutes fail');
                }
            }

            //add 奖品配置
            $awardRes = self::addAwardConfig($data, $opActivityId, $now, $operator_id);
            if(!$awardRes){
                throw new RunTimeException('error: insert CountingAwardConfig fail');
            }

            //add activity_ext
            $extRes = ActivityExtModel::insertRecord([
                'activity_id' => $opActivityId,
                'award_rule'  => $data['award_rule']
            ]);

            if(!$extRes){
                throw new RunTimeException('error: insert ActivityExt fail');
            }

            $db->commit();

            return true;

        }catch (RunTimeException $e){

            SimpleLogger::error("[CountingActivityService createCountingActivity] error", ['errors' => $e->getMessage()]);

            $db->rollBack();

            return false;
        }
    }

    public static function addActivity($data, $now){
        $opActivityId = OperationActivityModel::insertRecord(
            [
                'name'        => $data['name'],
                'create_time' => $now,
                'update_time' => $now,
                'app_id'      => $data['app_id'] ?? UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            ]
        );

        return $opActivityId;
    }


    public static function addMutexInfo($data, $opActivityId, $now, $operator_id){
        $mutexData = [];
        foreach ($data['mutex_activity_ids'] as $rowId){
            $row = [
                'op_activity_id'    => $opActivityId,
                'mutex_op_activity_id'  => $rowId,
                'status'                => CountingActivityMutesModel::INVALID_STATUS,
                'create_time'           => $now,
                'update_time'           => $now,
                'operator_id'           => $operator_id
            ];

            $mutexData[] = $row;
        }
        return CountingActivityMutesModel::batchInsert($mutexData);

    }

    public static function addAwardConfig($data, $opActivityId, $now, $operator_id){
        $award_config = [];
        $golden_leaf = $data['golden_leaf']['count'] ?? 0;
        $golden_leaf = intval($golden_leaf);

        if($golden_leaf){
            $leaf = [
                'op_activity_id' => $opActivityId,
                'type'           => CountingAwardConfigModel::LEAF_TYPE,
                'amount'         => $golden_leaf,
                'storage'        => 0,
                'status'         => CountingAwardConfigModel::INVALID_STATUS,
                'create_time'    => $now,
                'operator_id'    => $operator_id,
            ];
            $award_config[] = $leaf;
        }

        if(!empty($data['goods_list'])){
            foreach ($data['goods_list'] as $row){
                $goods = [
                    'op_activity_id' => $opActivityId,
                    'type'           => CountingAwardConfigModel::PRODUCT_TYPE,
                    'amount'         => $row['amount'],
                    'storage'        => $row['storage'],
                    'status'         => CountingAwardConfigModel::INVALID_STATUS,
                    'create_time'    => $now,
                    'operator_id'    => $operator_id,
                ];
                $award_config[] = $goods;
            }
        }

        return CountingAwardConfigModel::batchInsert($award_config);
    }

    public static function editActivity($params){
        $activitInfo = CountingActivityModel::getRecord(['op_activity_id'=>$params['op_activity_id']]);

        //已启用
        if($activitInfo['status'] == CountingActivityModel::ENABLE_STATUS){
            $data = [
                'name' => $params['name'],
                'remark'   => $params['remark'],
            ];

            if($activitInfo['sign_end_time'] != $params['sign_end_time'] && $params['sign_end_time'] < $activitInfo['sign_end_time']){

            }

        }

        echo json_encode($activitInfo);die;



    }

    /**
     * 用户参与列表
     * @param array $params
     * @return array
     */
    public static function getSignList($params = [])
    {
        list($list, $total) = CountingActivitySignModel::list($params);
        $list = self::formatSign($list);
        return [$list, $total];
    }

    /**
     * 用户参与详情
     * @param $userId
     * @param array $params
     * @return array
     */
    public static function getUserSignList($userId, $params = [])
    {
        list($student, $weekActivityDetail, $signRecordDetails) = CountingActivitySignModel::getUserRecords($userId, $params);
        return [$student, $weekActivityDetail, $signRecordDetails];
    }


    /**
     * 参与记录数据格式化
     * @param array $data
     * @return array|mixed
     */
    public static function formatSign($data = [])
    {
        foreach ($data as &$item) {
            $item['mobile'] = Util::hideUserMobile($item['mobile']);
            if ($item['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_NO) {
                $item['award_status'] = $item['qualified_status'];
            }
        }
        return $data;
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ActivityExtModel;
use App\Models\CountingActivityAwardModel;
use App\Models\CountingActivityModel;
use App\Models\CountingActivityMutexModel;
use App\Models\CountingActivitySignModel;
use App\Models\CountingAwardConfigModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\OperationActivityModel;
use App\Services\Queue\QueueService;

class CountingActivityService
{
    public static $EVENT_TYPE = 16; //op运营活动

    public static function getCountDetail($op_activity_id){
        //获取互动详情
        $detail = CountingActivityModel::getRecord(['op_activity_id'=>$op_activity_id]);

        if(empty($detail)){
            return [];
        }

        $detail['banner_url']           = $detail['banner'] ? AliOSS::replaceCdnDomainForDss($detail['banner']) : '';
        $detail['reminder_pop_url']     = $detail['reminder_pop'] ? AliOSS::replaceCdnDomainForDss($detail['reminder_pop']) : '';
        $detail['award_thumbnail_url']  = $detail['award_thumbnail'] ? AliOSS::replaceCdnDomainForDss($detail['award_thumbnail']) : '';

        $detail['mutex_activitys'] = []; //互斥活动
        $detail['golden_leaf'] = []; //金叶子
        $detail['goods_list']  = []; //实物商品


        //获取互斥活动
        $mutex_list = CountingActivityMutexModel::getRecords(['op_activity_id'=>$op_activity_id, 'status'=>CountingActivityMutexModel::NORMAL_STATUS],['id','op_activity_id','mutex_op_activity_id','status' ]);
        if($mutex_list){
            $mutexIds = array_column($mutex_list, 'mutex_op_activity_id');
            $mutex_activitys = CountingActivityModel::getRecords(['op_activity_id'=>$mutexIds], ['id','op_activity_id','remark','name','rule_type','nums','start_time','end_time']);
            foreach ($mutex_activitys as &$val){
                $val['rule_type_name'] = $val['rule_type'] == CountingActivityModel::RULE_TYPE_CONTINU ? '连续' : '累计';
            }
            $detail['mutex_activitys'] = $mutex_activitys;
        }

        //获取实物奖励
        $awardList = CountingAwardConfigModel::getRecords(['op_activity_id'=>$op_activity_id, 'status'=>CountingAwardConfigModel::EFFECTIVE_STATUS],['id','amount','storage','goods_id','goods_code','status','create_time','type']);

        if($awardList){
            $goodsIds = [];
            foreach ($awardList as $one){
                if($one['type'] == CountingAwardConfigModel::GOLD_LEAF_TYPE){
                    $detail['golden_leaf'] = $one;
                }else{
                    $goodsIds[] = $one['goods_id'];
                }
            }
            if($goodsIds){
                $erp = new Erp();
                $params = [
                    'goods_id' => implode(',', $goodsIds),
                ];
                $goodsData = $erp->getLogisticsGoodsList($params);
                $goodsList = $goodsData['data']['list'] ?? [];
                $goodsList = array_column($goodsList, null, 'id');

                foreach ($awardList as $one){
                    if($one['type'] == CountingAwardConfigModel::PRODUCT_TYPE){
                        $one['goodsInfo'] = $goodsList[$one['goods_id']] ?? [];
                        $detail['goods_list'][]  = $one;
                    }
                }
            }
        }

        //获取规则说明
        $activity_ext = ActivityExtModel::getRecord(['activity_id'=>$op_activity_id]);
        $detail['award_rule'] = !empty($activity_ext['award_rule']) ? Util::textDecode($activity_ext['award_rule']) : '';

        return $detail;
    }
    /**
     * 获取奖品列表
     * @param $params
     * @return array
     */
    public static function getAwardList($params){
        $erp = new Erp();
        $list = $erp->getLogisticsGoodsList($params);
        return $list;
    }


    /**
     * 更新活动状态
     * @param $op_activity_id
     * @param $status
     * @return false|int|null
     */
    public static function editStatus($op_activity_id, $status){

        if(!in_array($status, [CountingActivityModel::NORMAL_STATUS, CountingActivityModel::DISABLE_STATUS])){
            throw new RuntimeException(['status_is_invalid']);
        }

        $activityInfo = CountingActivityModel::getRecord(['id'=>$op_activity_id],['id','op_activity_id','first_effect_time','status','name','remark','start_time','end_time']);

        if(empty($activityInfo) || $activityInfo['status'] == $status){
            throw new RuntimeException(['update_failure']);
        }

        if($activityInfo['first_effect_time'] && $status == CountingActivityModel::NORMAL_STATUS){
            throw new RuntimeException(['activity_disable_not_start']);
        }

        $data = [];
        $now = time();
        if($activityInfo['first_effect_time'] == 0 && $status == CountingActivityModel::NORMAL_STATUS){
            $data = self::sendErpEvent($activityInfo);
            $data['first_effect_time'] = $now;
        }
        $data['status'] = $status;
        $data['update_time'] = $now;

        $res = CountingActivityModel::updateRecord($op_activity_id, $data);
        if($res){
            return true;
        }else{
            throw new RuntimeException(['update_disabled_fail']);
        }

    }

    /**
     * 向erp发送事件
     * @param $activityInfo
     * @return array
     * @throws RunTimeException
     */
    public static function sendErpEvent($activityInfo){
        $data = [];
        $erp = new Erp();

        $award['type'] = ErpEventTaskModel::OP_ACTIVITY;
        $award['amount'] = 0;
        $award['source_type'] = 0;
        $award['account_sub_type'] = 0;
        $award['to']= 0;
        $award['need_check']= 0;
        $award['task_flag']= 0;

        $event = [
            'name' => $activityInfo['name'] . 'op计数' . $activityInfo['op_activity_id'],
            'task_name' => '"' . $activityInfo['name'] . '"活动奖励',
            'desc' => $activityInfo['remark'],
            'type' => self::$EVENT_TYPE,
            'start_time' => $activityInfo['start_time'],
            'end_time' => $activityInfo['end_time'],
            'awards' => [$award],
        ];

        $res = $erp->addEventAndEventTask($event);
        if($res['code'] == Valid::CODE_SUCCESS){
            $data['event_id'] = $res['data']['event_id'];
            $data['task_id'] = $res['data']['tasks'][0];
        }else{
            throw new RuntimeException(['erp_create_user_event_task_award_fail']);
        }
        return $data;
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

        if(!empty($params['mutex_activity_id'])){
            $where['op_activity_id[!]'] = $params['mutex_activity_id'];
        }

        if(!empty($params['name'])){
            $where['name[~]'] = $params['name'];
        }
        if(!empty($params['op_activity_id'])){
            $where['op_activity_id'] = $params['op_activity_id'];
        }
        if(!empty($params['rule_type'])){
            $where['rule_type'] = $params['rule_type'];
        }
        if(!empty($params['status'])){
            $where['status'] = $params['status'];
        }
        if (!empty($params['sign_end_time_s'])) {
            $where['sign_end_time[>=]'] = strtotime($params['sign_end_time_s']);
        }
        if (!empty($params['sign_end_time_e'])) {
            $where['sign_end_time[<=]'] = strtotime($params['sign_end_time_e']);
        }
        if (!empty($params['join_end_time_s'])) {
            $where['join_end_time[>=]'] = strtotime($params['join_end_time_s']);
        }
        if (!empty($params['join_end_time_s'])) {
            $where['join_end_time[<=]'] = strtotime($params['join_end_time_s']);
        }

        $total = CountingActivityModel::getCount($where);

        if ($total <= 0) {
            return [[], 0];
        }
        $where['ORDER'] = ['id' => 'DESC'];

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        $list = CountingActivityModel::getRecords($where, ['id','op_activity_id','name','start_time','end_time','sign_end_time','join_end_time','remark','rule_type','nums','status','operator_id','create_time']);

        $operator_ids = array_column($list, 'operator_id');
        $employees = EmployeeModel::getRecords(['id'=>array_unique($operator_ids)],['id','name']);
        $employees = array_column($employees, null, 'id');

        foreach ($list as &$one){
            $one['status_text'] = $one['status'] == CountingActivityModel::NORMAL_STATUS ? '启用中' : '已禁用';
            $one['rule_type_name'] = $one['rule_type'] == CountingActivityModel::RULE_TYPE_CONTINU ? '连续' : '累计';
            $one['operator_name'] = $employees[$one['operator_id']]['name'];
        }

        return compact('list', 'total');

    }


    /**
     * 检测互斥活动是否合法
     * @param $mutexIds
     * @return bool
     */
    public static function checkMutex($mutexIds){
        $info = CountingActivityModel::getRecords(['op_activity_id'=>$mutexIds], ['op_activity_id']);

        if(empty($info)){
            SimpleLogger::error('get mutex_activity_ids is empty', $mutexIds);
            return false;
        }

        $diffArr = array_diff($mutexIds, array_column($info, 'op_activity_id'));
        if(!empty($diffArr)){
            SimpleLogger::error('get mutex_activity_ids is empty', $diffArr);
            return false;
        }

        return true;
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
        if(!empty($data['mutex_activity_ids']) && false === self::checkMutex($data['mutex_activity_ids'])){
            return false;
        }

        try {
            $db->beginTransaction();

            //add 活动表
            $opActivityId = self::addActivity($data, $now);
            if(!$operator_id){
                throw new RunTimeException(['insert_failure'],[$data]);
            }

            //add counting_activity 主表信息
            $data['op_activity_id'] = $opActivityId;
            $insertData = self::getInitInsertData($data, $operator_id, $now);
            $countActivityId = CountingActivityModel::insertRecord($insertData);
            if(!$countActivityId){
                throw new RunTimeException(['insert_failure'],[$insertData]);
            }

            //add 互斥信息
            if (!empty($data['mutex_activity_ids'])){
                $inRes = self::addMutexInfo($data['mutex_activity_ids'], $opActivityId, $now, $operator_id);
                if(!$inRes){
                    throw new RunTimeException(['insert_failure'],['msg'=>'添加互斥任务失败',$data['mutex_activity_ids'], $opActivityId, $now, $operator_id]);
                }
            }

            //add 奖品配置
            $awardRes = self::addAwardConfig($data, $opActivityId, $now, $operator_id);
            if(!$awardRes){
                throw new RunTimeException(['insert_failure'],['msg'=>'添加奖品失败',$data, $opActivityId, $now, $operator_id]);
            }


            //add activity_ext
            $extData = [
                'activity_id' => $opActivityId,
                'award_rule'  => $data['award_rule']
            ];
            $extRes = ActivityExtModel::insertRecord($extData);

            if(!$extRes){
                throw new RunTimeException(['insert_failure'],$extData);
            }

            $db->commit();

            return true;

        }catch (RunTimeException $e){
            SimpleLogger::error("[CountingActivityService createCountingActivity] error", ['errors' => $e->getData()]);
            $db->rollBack();
            return $e->getAppErrorData();
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
        foreach ($data as $rowId){
            $row = [
                'op_activity_id'    => $opActivityId,
                'mutex_op_activity_id'  => $rowId,
                'status'                => CountingActivityMutexModel::NORMAL_STATUS,
                'create_time'           => $now,
                'update_time'           => $now,
                'operator_id'           => $operator_id
            ];

            $mutexData[] = $row;
        }
        return CountingActivityMutexModel::batchInsert($mutexData);

    }

    public static function addAwardConfig($data, $opActivityId, $now, $operator_id){
        $award_config = [];
        $golden_leaf = $data['golden_leaf'] ?? 0;
        $golden_leaf = intval($golden_leaf);

        if($golden_leaf){
            $leaf = [
                'op_activity_id' => $opActivityId,
                'type'           => CountingAwardConfigModel::GOLD_LEAF_TYPE,
                'amount'         => $golden_leaf,
                'storage'        => 0,
                'goods_id'       => 0,
                'goods_code'     => '',
                'status'         => CountingAwardConfigModel::EFFECTIVE_STATUS,
                'create_time'    => $now,
                'operator_id'    => $operator_id,
            ];
            $award_config[] = $leaf;
        }


        if(!empty($data['goods_list'])){
            $goods_ids = array_column($data['goods_list'],'goods_id');
            $goodsList = self::getAwardList(['goods_id'=>implode(',',$goods_ids)]);
            $goodsList = array_column($goodsList['data']['list'], null, 'id');
            foreach ($data['goods_list'] as $row){
                $goods = [
                    'op_activity_id' => $opActivityId,
                    'type'           => CountingAwardConfigModel::PRODUCT_TYPE,
                    'amount'         => $row['amount'],
                    'storage'        => $row['storage'],
                    'goods_id'       => $row['goods_id'],
                    'goods_code'     => $goodsList[$row['goods_id']]['code'] ?? '',
                    'status'         => CountingAwardConfigModel::EFFECTIVE_STATUS,
                    'create_time'    => $now,
                    'operator_id'    => $operator_id,
                ];
                $award_config[] = $goods;
            }
        }

        if(empty($award_config)){
            throw new RuntimeException(['activity_award_is_disable']);
        }
        return CountingAwardConfigModel::batchInsert($award_config);
    }

    public static function editActivity($params, $operator_id){
        $sendQueue = false;
        try{
            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $activitInfo = CountingActivityModel::getRecord(['op_activity_id'=>$params['op_activity_id']]);

            if(empty($activitInfo)){
                throw new RuntimeException(['activity_award_is_disable']);
            }

            if($activitInfo['first_effect_time'] && $activitInfo['status'] == CountingActivityModel::DISABLE_STATUS){
                throw new RuntimeException(['activity_already_over']);
            }
            $now = time();

            //已启用
            if($activitInfo['status'] == CountingActivityModel::NORMAL_STATUS){

                $activityData = [
                    'remark'   => $params['remark'],
                    'update_time' => $now,
                    'banner'   => $params['banner'],
                    'reminder_pop'  => $params['reminder_pop'],
                    'award_thumbnail' => $params['award_thumbnail'],
                    'title'           => $params['title'],
                    'instruction'     => $params['instruction'],
                ];

                if($activitInfo['rule_type'] == CountingActivityModel::RULE_TYPE_COUNT && $params['rule_type'] == CountingActivityModel::RULE_TYPE_CONTINU){
                    throw new RuntimeException(['rules_cannot_be_edited']);
                }else{
                    $activityData['rule_type'] = $params['rule_type'];
                }

                if($activitInfo['nums'] < $params['nums']){
                    throw new RuntimeException(['cannot_be_greater_than_the_original_value']);
                }else{
                    $activityData['nums'] = $params['nums'];
                    $sendQueue = true;
                }


                if($activitInfo['sign_end_time'] != $params['sign_end_time'] && $params['sign_end_time'] > $activitInfo['sign_end_time']){
                    $activityData['sign_end_time'] = strtotime($params['sign_end_time']);
                }else{
                    throw new RuntimeException(['sign_end_time_not_in_advance']);
                }

                if($activitInfo['join_end_time'] != $params['join_end_time'] && $params['join_end_time'] > $activitInfo['join_end_time']){
                    $activityData['join_end_time'] = strtotime($params['join_end_time']);
                }else{
                    throw new RuntimeException(['join_end_time_not_in_advance']);
                }

                if($activitInfo['end_time'] != $params['end_time'] && $params['end_time'] > $activitInfo['end_time']){
                    $activityData['end_time'] = strtotime($params['end_time']);
                }else{
                    throw new RuntimeException(['end_time_not_in_advance']);
                }
            }else{//未启用
                $activityData = self::getInitInsertData($params, $operator_id, $now, $activitInfo['op_activity_id']);

                //获取互斥列表
                $mutexList = CountingActivityMutexModel::getRecords(['op_activity_id'=>$activitInfo['op_activity_id'],'status'=>CountingActivityMutexModel::NORMAL_STATUS], ['mutex_op_activity_id']);

                //删除互斥任务
                $delMutexIds = array_diff(array_column($mutexList, 'mutex_op_activity_id'), $params['mutex_activity_ids']);
                if($delMutexIds){
                    $delRes = CountingActivityMutexModel::batchUpdateRecord(['status'=>CountingActivityMutexModel::DISABLE_STATUS], ['op_activity_id'=>$activitInfo['op_activity_id'], 'mutex_op_activity_id'=>$delMutexIds]);
                    if(!$delRes){
                        throw new RuntimeException(['del_mutex_fail'],$delMutexIds);
                    }
                }

                //增加互斥任务
                $addMutexIds = array_diff($params['mutex_activity_ids'], array_column($mutexList, 'mutex_op_activity_id'));

                if($addMutexIds && self::checkMutex($addMutexIds)){
                    $addMutex = self::addMutexInfo($addMutexIds, $activitInfo['op_activity_id'], $now, $operator_id);
                    if(!$addMutex){
                        throw new RunTimeException(['add_mutex_fail']);
                    }
                }

                //获取金叶子配置并且更新
                $award_config = [];
                $leafInfo = CountingAwardConfigModel::getRecord(['status'=>CountingAwardConfigModel::EFFECTIVE_STATUS, 'type'=>CountingAwardConfigModel::GOLD_LEAF_TYPE, 'op_activity_id'=>$activitInfo['op_activity_id']]);
                $A = $params['golden_leaf'] ?? 0;
                $B = $leafInfo['amount'] ?? 0;

                if($A != $B){
                    //把金叶子奖励置为失效
                    CountingAwardConfigModel::batchUpdateRecord(['status'=>CountingAwardConfigModel::INVALID_STATUS,'update_time'=>$now], ['op_activity_id' => $activitInfo['op_activity_id'], 'status'=>CountingAwardConfigModel::EFFECTIVE_STATUS, 'type'=>CountingAwardConfigModel::GOLD_LEAF_TYPE]);
                    if($A){ //添加金叶子
                        $award_config['golden_leaf'] = $A;
                    }
                }

                $paramsGoods = array_column($params['goods_list'], null, 'goods_id');
                //获取现有奖品
                $goodsList   = CountingAwardConfigModel::getRecords(['status'=>CountingAwardConfigModel::EFFECTIVE_STATUS, 'op_activity_id' => $activitInfo['op_activity_id'], 'type'=>CountingAwardConfigModel::PRODUCT_TYPE],['goods_id','storage','amount']);
                $goodsList = array_column($goodsList, null, 'goods_id');

                //先把所有商品置为失效状态
                if($paramsGoods != $goodsList){
                    CountingAwardConfigModel::batchUpdateRecord(['status'=>CountingAwardConfigModel::INVALID_STATUS,'update_time'=>$now], ['op_activity_id' => $activitInfo['op_activity_id'],'status'=>CountingAwardConfigModel::EFFECTIVE_STATUS, 'type'=>CountingAwardConfigModel::PRODUCT_TYPE]);
                    if(!empty($params['goods_list'])){
                        $award_config['goods_list'] = $params['goods_list'] ?? [];
                    }
                }


                //添加奖品
                $addRes = self::addAwardConfig($award_config, $activitInfo['op_activity_id'], $now, $operator_id);
                if(!$addRes){
                    throw new RunTimeException(['add_award_fail']);
                }

            }
            //更新活动表
            $res = CountingActivityModel::updateRecord($activitInfo['id'], $activityData);

            if(!$res){
                throw new RunTimeException(['update_disabled_fail']);
            }
            ActivityExtModel::batchUpdateRecord([
                'award_rule'  => Util::textEncode($params['award_rule'])
            ],['activity_id' => $activitInfo['op_activity_id']]);

            $db->commit();
            if($sendQueue){
                QueueService::editQualified(['id'=>$activitInfo['id']]);
            }
            return true;
        }catch (RuntimeException $e){
            $db->rollBack();
            return $e->getAppErrorData();
        }

    }

    public static function getInitInsertData($data, $operator_id, $now, $activity_id = 0){
        $data = [
            'op_activity_id'    => $data['op_activity_id'],
            'name'              => $data['name'],
            'start_time'        => strtotime($data['start_time']),
            'end_time'          => strtotime($data['end_time']),
            'sign_end_time'     => strtotime($data['sign_end_time']),
            'join_end_time'     => strtotime($data['join_end_time']),
            'remark'            => $data['remark'],
            'rule_type'         => $data['rule_type'],
            'nums'              => $data['nums'],
            'title'             => $data['title'],
            'instruction'       => $data['instruction'],
            'banner'            => $data['banner'],
            'reminder_pop'      => $data['reminder_pop'],
            'award_thumbnail'   => $data['award_thumbnail'],
            'status'            => CountingActivityModel::DISABLE_STATUS,
            'operator_id'       => $operator_id
        ];
        if(!$activity_id){
            $data['create_time'] = $now;
        }else{
            $data['update_time'] = $now;
        }
        $count = CountingActivityModel::getCount(['name'=>$data['name'],'op_activity_id[!]'=>$activity_id]);
        if($count){
            throw new RuntimeException(['repeat_activity_name']);
        }

        return $data;
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
        // 补充物流数据
        $list = self::fillSignRecordsLogistics($list);
        return [$list, $total];
    }

    /**
     * @param array $params
     * @return array
     */
    public static function exportSignList($params = [])
    {
        $exportCount = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'export_amount');
        $exportTotal = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'export_total');
        $params['count'] = $exportCount;
        $resData = [];
        $counter = $exportCount;
        list($list, $total) = CountingActivitySignModel::list($params);
        $list = self::formatSign($list);
        $list = self::fillSignRecordsLogistics($list);
        $resData = array_merge($resData, $list);
        if ($total > $exportCount) {
            $totalPages = intval($total/$params['count']);
            if (!empty(fmod($total, $params['count']))) {
                $totalPages += 1;
            }
            for ($i = 1; $i <= $totalPages; $i++) {
                $params['page'] = $i;
                list($list, $total) = CountingActivitySignModel::list($params);
                $list = self::formatSign($list);
                $list = self::fillSignRecordsLogistics($list);
                $counter += $exportCount;
                if (!empty($list)) {
                    $resData = array_merge($resData, $list);
                }
                if ($counter > $exportTotal) {
                    break;
                }
            }
        }
        $header = ["学生id","学生uuid","学生昵称","学生手机号","参与活动id","参与活动名称","连续期数","累计期数","领取状态","领取时间","物流单号"];
        $rows = [$header];
        foreach ($resData as $item) {
            $logistics = [];
            foreach ($item['logistics'] as $logistic) {
                if (!empty($logistic['express_number'])) {
                    $logistics[] = $logistic['express_number'];
                }
            }
            $row = [
                $item['student_id'],
                "'".$item['uuid'],
                $item['student_name'],
                $item['mobile'],
                $item['op_activity_id'],
                $item['name'],
                $item['continue_nums'],
                $item['cumulative_nums'],
                $item['award_status_name'],
                Util::formatTimestamp($item['award_time'], '', 'Ymd H:i:s'),
                implode(',', $logistics)
            ];
            $rows[] = $row;
        }
        return $rows;
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
        $signRecordDetails = self::formatSign($signRecordDetails);
        $signRecordDetails = self::fillSignRecordsLogistics($signRecordDetails, true);
        return [$student, $weekActivityDetail, $signRecordDetails];
    }


    /**
     * 参与记录数据格式化
     * @param array $data
     * @return array|mixed
     */
    public static function formatSign($data = [])
    {
        if (empty($data)) {
            return [];
        }
        foreach ($data as &$item) {
            if (isset($item['mobile'])) {
                $item['mobile'] = Util::hideUserMobile($item['mobile']);
            }
            if ($item['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_NO) {
                $item['award_status'] = $item['qualified_status'];
            }
            $item['award_status_name'] = CountingActivitySignModel::AWARD_STATUS_DICT[$item['award_status']] ?? '';
            $item['qualified_status_name'] = CountingActivitySignModel::QUALIFIED_STATUS_DICT[$item['qualified_status']] ?? '';
        }
        return $data;
    }

    /**
     * 查询参与记录物流信息
     * @param array $data
     * @param bool $withGoods
     * @return array
     */
    public static function fillSignRecordsLogistics($data = [], $withGoods = false)
    {
        $allIds = array_column($data, 'id');
        if (empty($allIds)) {
            return [];
        }
        $allIds = array_unique($allIds);
        // 拼装物流信息
        $logisticsFields = ['id', 'goods_id', 'sign_id', 'address_detail', 'express_number', 'logistics_status', 'logistics_company', 'amount'];
        $logisticsData = [];
        $where = [
            'sign_id' => $allIds,
            'type' => CountingActivityAwardModel::TYPE_ENTITY,
        ];
        $awards = CountingActivityAwardModel::getRecords($where, $logisticsFields);
        // 拼装奖品及数量信息
        $goodsData = [];
        $goodsIds = array_unique(array_column($awards, 'goods_id'));
        if ($withGoods && !empty($goodsIds)) {
            $params = ['goods_id' => implode(',', $goodsIds)];
            $goods = self::getAwardList($params);
            $goods = $goods['data']['list'];
            foreach ($goods as $item) {
                $goodsData[$item['id']]['list'][] = $item;
            }
        }
        foreach ($awards as $award) {
            if (!empty($award['address_detail'])) {
                $award['address_detail'] = json_decode($award['address_detail'], true);
            }
            $award['logistics_status_name'] = !empty($award['logistics_status']) ? CountingActivityAwardModel::LOGISTICS_STATUS_MAP[$award['logistics_status']] : '';
            $award['goods'] = $goodsData[$award['goods_id']] ?? [];
            $logisticsData[$award['sign_id']][] = $award;

        }
        // 补全物流信息
        foreach ($data as &$item) {
            $item['logistics'] = $logisticsData[$item['id']] ?? [];
        }
        return $data;
    }

    /**
     * @return int
     */
    public static function isEnable($op_activity_id)
    {
        return CountingActivityModel::getCount(['op_activity_id'=>$op_activity_id, 'status'=>CountingActivityModel::NORMAL_STATUS]);
    }
}

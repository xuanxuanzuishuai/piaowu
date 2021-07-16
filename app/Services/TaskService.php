<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:39 AM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\CountingActivityAwardModel;
use App\Models\CountingActivityModel;
use App\Models\CountingActivityMutexModel;
use App\Models\CountingActivitySignModel;
use App\Models\CountingAwardConfigModel;
use App\Services\Queue\QueueService;

class TaskService
{

    const TASK_LIST_NEWEST_VISIT_TIME = 'task_list_newest_visit_time';

    /**
     * 任务列表
     *
     * @param int $studentId
     * @return array
     */
    public static function getCountingActivityList(int $studentId): array
    {
        $ret = [
            'popup' => false,
            'banner' => [],
            'list'  => []
        ];

        if (empty($studentId)) return $ret;

        $redis = RedisDB::getConn();
        $cacheTime = $redis->hget(self::TASK_LIST_NEWEST_VISIT_TIME, $studentId);
        if (empty($cacheTime)) $cacheTime = 0;

        $time = time();

        //时间段内有效任务
        $countingActivity = CountingActivityModel::getRecords(
            [
                'start_time[<=]' => $time,
                'end_time[>=]'   => $time,
                'status'         => CountingActivityModel::NORMAL_STATUS,
                'ORDER'          => ['start_time' => 'DESC'],
            ]
        );

        if (empty($countingActivity)) return $ret;

        $opActivityIds = [];
        $list = [];
        foreach ($countingActivity as $value){
            $opActivityIds[] = $value['op_activity_id'];
            $list[$value['op_activity_id']] = $value;
            //是否有新任务
            if ($value['first_effect_time'] > $cacheTime) $ret['popup'] = true;
        }

        //互斥任务
        $mutex = CountingActivityMutexModel::getRecords([
            'op_activity_id' => $opActivityIds,
            'status'         => CountingActivityMutexModel::NORMAL_STATUS
        ], ['op_activity_id', 'mutex_op_activity_id']
        );

        if (!empty($mutex)) {
            $mutexIds      = [];
            $mutexActivity = [];
            foreach ($mutex as $m) {
                $mutexActivity['mutex_op_activity_id'][] = $m['op_activity_id'];
                $mutexIds[]                              = $m['mutex_op_activity_id'];
            }

            if (!empty($mutexIds)) {
                //是否在互斥任务黑名单
                $sign = CountingActivitySignModel::getRecords([
                    'award_status'   => CountingActivitySignModel::AWARD_STATUS_RECEIVED,
                    'op_activity_id' => $mutexIds,
                    'student_id'     => $studentId
                ]);

                if (!empty($sign)) {
                    foreach ($sign as $s) {
                        //互斥活动 参与时间小于当前活动时间
                        if ($s['award_time'] < $list[$s['op_activity_id']]['first_effect_time']) {
                            unset($list[$s['op_activity_id']]);
                        }
                    }
                }
            }
        }

        if (empty($list)) return $ret;


        //参与的活动记录
        $activitySign = CountingActivitySignModel::getRecords([
            'op_activity_id' => array_keys($list),
            'student_id'     => $studentId
        ]);

        //格式化数据
        [$ret['list'],$ret['banner']] = self::formatListData($list,$activitySign);

        //记录任务中心访问时间
        $redis->hset(self::TASK_LIST_NEWEST_VISIT_TIME, $studentId, time());

        return $ret;

    }


    private static function formatListData(array $list, array $sign) :array
    {
        $statusList = [
            1 => '立即报名',
            2 => '前往参与',
            3 => '报名结束',
            4 => '领取奖励',
            5 => '已领奖',
            6 => '已结束',
        ];


        $activitySign = array_column($sign, null, 'op_activity_id');

        $activityExt = ActivityExtModel::getRecords([
            'activity_id' => array_keys($list)
        ], [
            'activity_id', 'award_rule'
        ]);

        $activityExt = array_column($activityExt, 'award_rule', 'activity_id');


        $ret = [];
        $banner = [];
        foreach ($list as $value){

            if(!empty($value['banner'])) $banner[] = AliOSS::replaceCdnDomainForDss($value['banner']);

            if (empty($activitySign[$value['op_activity_id']]) && $value['sign_end_time'] > time()){
                //未报名且在报名截止时间内
                $status = 1;
            } elseif(empty($activitySign[$value['op_activity_id']]) && $value['sign_end_time'] < time()){
                //未报名且过了报名截止时间
                $status = 3;
            } elseif ( $activitySign['award_status'] == CountingActivitySignModel::AWARD_STATUS_RECEIVED){
                $status = 5;
            }elseif ($activitySign['award_status'] == CountingActivitySignModel::AWARD_STATUS_PENDING && $activitySign['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_YES ){
                $status = 4;
            } elseif ($activitySign['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_NO && $value['join_end_time'] < time()){
                //未达标且过了参与截止时间
                $status = 6;
            }else{
                $status = 2;
            }

            $ret[] = [
                'reminder_pop' => $value['reminder_pop'] ?? AliOSS::replaceCdnDomainForDss($value['reminder_pop']),
                'award_thumbnail' => $value['award_thumbnail'] ?? AliOSS::replaceCdnDomainForDss($value['award_thumbnail']),
                'rule_type' =>$value['rule_type'],
                'nums' =>$value['nums'],
                'title' =>$value['title'],
                'name' =>$value['name'],
                'op_activity_id' =>$value['op_activity_id'],
                'instruction' =>$value['instruction'],
                'award_rule' => $activityExt[$value['op_activity_id']] ?? '',
                'sign_end_time' => $value['sign_end_time'],
                'sign_end_time_format' => date('Y') == date('Y',time()) ? date('m.d',$value['sign_end_time']) : date('Y.m.d',$value['sign_end_time']),
                'status' => $status,
                'show_status' => $statusList[$status],
                'cumulative_nums' => $activitySign[$value['op_activity_id']]['cumulative_nums'] ?? 0,
                'continue_nums' => $activitySign[$value['op_activity_id']]['continue_nums'] ?? 0,
            ];
        }

        if (empty($banner)) {
            $defaultBanner = DictConstants::get(DictConstants::TASK_DEFAULT_BANNER, 'default');
            $banner[] = AliOSS::replaceCdnDomainForDss($defaultBanner);
        }
        return [$ret,$banner];

    }

    /**
     * 领奖记录
     *
     * @param int $studentId
     * @return array
     */
    public static function getAwardRecord(int $studentId):array
    {

        $activity = CountingActivitySignModel::getActivitySignInfo($studentId);

        if (empty($activity)) return [];

        $activityList = array_column($activity,null,'op_activity_id');

        $opActivityIds = array_column($activity,'op_activity_id');

        $award = CountingActivityAwardModel::getRecords([
            'op_activity_id' => $opActivityIds,
            'student_id'     => $studentId,
            'ORDER'          => ['type' => 'DESC']
        ], ['op_activity_id', 'type', 'unique_id', 'shipping_status', 'goods_id', 'amount']);

        $goodsIds = array_column($award, 'goods_id');

        //TODO:: 商品信息

        $goodsInfo = (new Erp())->getGoodsDetails(['goods_id' => $goodsIds]);

        foreach ($award as $item) {
            if (!empty($activityList[$item['op_activity_id']]['status']) && $item['type'] == CountingActivityAwardModel::TYPE_ENTITY) {
                $activityList[$item['op_activity_id']]['status'] = $item['shipping_status'];
            } else {
                $activityList[$item['op_activity_id']]['status'] = $item['shipping_status'];
            }
            $activityList[$item['op_activity_id']]['goods'][] = [
                'type'            => $item['type'],
                'unique_id'       => $item['unique_id'],
                'shipping_status' => $item['shipping_status'],
                'goods_id'        => $item['goods_id'],
                'amount'          => $item['amount'],
                'name'            => $goodsInfo[$item['goods_id']]['name'] ?? '',
            ];

            $activityList[$item['op_activity_id']]['award_time_show'] = date('Y-m-d H:i:s', $activityList[$item['op_activity_id']]['award_time']);
        }

        return $activity;
    }


    /**
     * 领取详情
     *
     * @param int $activityId
     * @param int $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function getAwardDetails(int $activityId, int $studentId) :array
    {

        $sign = CountingActivitySignModel::getRecord([
            'student_id' => $studentId,
            'op_activity_id' => $activityId,
            'award_status' => CountingActivitySignModel::AWARD_STATUS_RECEIVED,
        ],[
            'award_time'
        ]);
        if (empty($sign)) throw new RunTimeException(['activity_award_is_disable']);

        //获取实物奖励数据
        $award = CountingActivityAwardModel::getRecords([
            'student_id' => $studentId,
            'op_activity_id' => $activityId,
            'type' => CountingActivityAwardModel::TYPE_ENTITY
        ],['goods_id','unique_id','amount','erp_address_id','address_detail','logistics_status','create_time']);

        if (empty($award)) throw new RunTimeException(['activity_award_is_disable']);

        $goodsIds = array_column($award,'goods_id');

        //TODO::获取商品信息 ---
        $goods = (new Erp())->getGoodsDetails(['goods_id' => $goodsIds]);


        foreach ($award as &$value) {
            $value['goods_name'] = $goods[$value['goods_id']]['name'];
            $value['goods_icon'] = $goods[$value['goods_id']]['icon'];
            $value['award_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
        }


        return $award;
    }

    /**
     * 获取物流信息
     *
     * @param int $activityId
     * @param int $studentId
     * @param string $uniqueId
     * @return array
     */
    public static function getExpressInfo(int $activityId, int $studentId, string $uniqueId) :array
    {
        //获取实物奖励数据
        $award = CountingActivityAwardModel::getRecord([
            'student_id' => $studentId,
            'op_activity_id' => $activityId,
            'unique_id' => $uniqueId,
            'type' => CountingActivityAwardModel::TYPE_ENTITY
        ],['goods_id','unique_id','amount','erp_address_id','address_detail','logistics_status','create_time']);

        $expressInfo = [];

        if (!empty($award['unique_id'])) {
            $expressInfo = (new Erp())->getExpressDetails($award['unique_id']);
        }

        $deliver = [
            'node' => '已发货',
            'time' => Util::formatTimeToChinese($award['create_time']),
            'details' => '小叶子已为您发货，包裹待揽收'
        ];

        $ret['express_record'] = array_merge(self::formatExpressRecord($expressInfo),$deliver);

        return $ret;
    }

    /**
     * 格式化物流信息
     *
     * @param array $record
     * @return array
     */
    private static function formatExpressRecord(array $record = []) :array
    {
        $nodeArr = [];
        foreach ($record as &$value){
            if (in_array($value['node'],$nodeArr)){
                $value['node'] = '';
            }
            $value['time'] = Util::formatTimeToChinese($value['time']);
        }
        return $record;
    }


    /**
     * 获取活动实物信息
     *
     * @param int $activityId
     * @return array
     */
    public static function getGoodsInfo(int $activityId):array
    {
        //请求接口获取物品信息

    }


    /**
     * 参加活动
     *
     * @param int $activityId
     * @param int $studentId
     * @return bool
     * @throws RunTimeException
     */
    public static function signUp(int $activityId, int $studentId): bool
    {

        $time             = time();
        $countingActivity = CountingActivityModel::getRecords([
            'op_activity_id'   => $activityId,
            'sign_end_time[>]' => $time,
            'status'           => CountingActivityModel::NORMAL_STATUS,
        ]);

        if (empty($countingActivity)) throw new RunTimeException(['activity_is_disable']);

        $sign = CountingActivitySignModel::getRecord([
            'op_activity_id' => $activityId,
            'student_id'     => $studentId,
        ], ['student_id']);

        if (!empty($sign)) throw new RunTimeException(['activity_repeat_sign_up']);

        $result = CountingActivitySignModel::insertRecord([
            'op_activity_id' => $activityId,
            'student_id'     => $studentId,
            'create_time'    => $time
        ]);

        if (empty($result)) {
            SimpleLogger::error('counting activity sign insert fail', ['op_activity_id' => $activityId, 'student_id' => $studentId]);
            throw new RunTimeException(['insert_failure']);
        }

        return true;
    }


    /**
     * 获取奖励
     *
     * @param int $activityId
     * @param int $studentId
     * @param int $erpAddressId
     * @param string $addressDetail
     * @return bool
     * @throws RunTimeException
     */
    public static function getRewards(int $activityId, int $studentId,int $erpAddressId = 0, string $addressDetail = '')
    {
        $countingActivity = CountingActivityModel::getRecords([
            'op_activity_id'   => $activityId,
            'status'           => CountingActivityModel::NORMAL_STATUS,
        ]);

        if (empty($countingActivity)) throw new RunTimeException(['activity_is_disable']);

        $sign = CountingActivitySignModel::getRecord([
            'op_activity_id' => $activityId,
            'student_id'     => $studentId,
            'qualified_status' => CountingActivitySignModel::QUALIFIED_STATUS_YES,
            'award_status' => CountingActivitySignModel::AWARD_STATUS_PENDING,
        ], ['id','student_id']);

        if (empty($sign)) throw new RunTimeException(['activity_not_qualified']);

        $awardConfig = CountingAwardConfigModel::getRecords([
            'op_activity_id' => $activityId,
            'status' => CountingAwardConfigModel::EFFECTIVE_STATUS,
        ]);

        if (empty($awardConfig)) throw new RunTimeException(['operator_failure']);

        $time = time();

        $activityAward = [];
        foreach ($awardConfig as $key => $item){
            if ($item['type'] == CountingAwardConfigModel::GOLD_LEAF_TYPE) {
                $activityAward[] = [
                    'sign_id'         => $sign['id'],
                    'type'            => $item['type'],
                    'unique_id'       => '',
                    'goods_id'        => 0,
                    'create_time'     => $time,
                    'amount'          => $item['amount'],
                    'op_activity_id'  => $activityId,
                    'erp_address_id'  => 0,
                    'address_detail'  => '{}',
                ];
            } else {
                $activityAward[] = [
                    'sign_id'         => $sign['id'],
                    'type'            => $item['type'],
                    'unique_id'       => CountingAwardConfigModel::UNIQUE_ID_PREFIX . sprintf("%08d",
                            $sign['id']) . sprintf("%02d", $key),
                    'goods_id'        => $item['goods_id'],
                    'create_time'     => $time,
                    'amount'          => $item['amount'],
                    'op_activity_id'  => $activityId,
                    'erp_address_id'  => $erpAddressId,
                    'address_detail'  => $addressDetail,
                ];
            }
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = CountingAwardConfigModel::grantAward($activityAward, $sign['id']);

        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['operator_failure']);
        } else {
            $db->commit();
        }

        QueueService::countingAward(['sign_id' => $sign['id']]);
        return true;
    }


}

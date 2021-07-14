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
use App\Models\CountingActivityBlackModel;
use App\Models\CountingActivityModel;
use App\Models\CountingActivityMutexModel;
use App\Models\CountingActivitySignModel;
use App\Models\CountingAwardConfigModel;
use App\Models\Dss\DssStudentModel;
use App\Services\Queue\QueueService;

class TaskService
{

    const TASK_LIST_NEWEST_VISIT_TIME = 'task_list_newest_visit_time';

    /**
     * 任务列表
     *
     * @param int $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function getCountingActivityList(int $studentId): array
    {
        $ret = [
            'popup'     => false,
            'gold_leaf' => 0,
            'banner'    => [],
            'list'      => [],
            'user_info' => [],
        ];

        if (empty($studentId)) return $ret;

        $userStatusInfo = StudentService::dssStudentStatusCheck($studentId);   // 获取用户当前状态
        $ret['user_info'] = [
            'uuid' => $userStatusInfo['student_info']['uuid'],
            'student_status' => $userStatusInfo['student_status'],
            'student_status_zh' => DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$userStatusInfo['student_status']] ?? '',
        ];

        $ret['gold_leaf'] = ErpUserService::getStudentAccountInfo($userStatusInfo['student_info']['uuid'],ErpUserService::ACCOUNT_SUB_TYPE_GOLD_LEAF);

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
            $mutexIds      = []; //互斥的活动id
            $mutexActivity = []; //互斥活动对应关系
            foreach ($mutex as $m) {
                $mutexActivity['mutex_op_activity_id'][] = $m['op_activity_id'];
                $mutexIds[]                              = $m['mutex_op_activity_id'];
            }

            if (!empty($mutexIds)) {

                //前置黑名单
                $blackList = CountingActivityBlackModel::getRecords([
                    'student_id' => $studentId,
                    'op_activity_id' => $mutexIds,
                    'status' => CountingActivityBlackModel::NORMAL_STATUS,
                ],['op_activity_id']);
                if (!empty($blackList)){
                    foreach ($blackList as $black){
                        unset($list[$black['op_activity_id']]);
                    }
                }

                //是否在互斥任务黑名单
                $sign = CountingActivitySignModel::getRecords([
                    'award_status'   => CountingActivitySignModel::AWARD_STATUS_RECEIVED,
                    'op_activity_id' => $mutexIds,
                    'student_id'     => $studentId
                ]);

                if (!empty($sign)) {
                    foreach ($sign as $s) {
                        foreach ($mutexActivity[$s['op_activity_id']] as $mutexActivityId){
                            //互斥活动 参与时间小于当前活动时间
                            if ($s['award_time'] < $list[$mutexActivityId]['first_effect_time']) {
                                unset($list[$mutexActivityId]);
                            }
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

        $activityIds = array_keys($list);

        $activityExt = ActivityExtModel::getRecords([
            'activity_id' => $activityIds
        ], [
            'activity_id', 'award_rule'
        ]);

        $activityExt = array_column($activityExt, 'award_rule', 'activity_id');

        $config = CountingAwardConfigModel::getRecords([
            'op_activity_id' => $activityIds,
            'status'         => CountingAwardConfigModel::EFFECTIVE_STATUS,
        ], [
            'type', 'op_activity_id'
        ]);

        foreach ($config as $item) {
            if ($item['type'] == CountingAwardConfigModel::PRODUCT_TYPE) {
                $list[$item['op_activity_id']]['goods_type'] = CountingAwardConfigModel::PRODUCT_TYPE;
            } elseif ($item['type'] != CountingAwardConfigModel::PRODUCT_TYPE && !isset($list[$item['op_activity_id']]['goods_type'])) {
                $list[$item['op_activity_id']]['goods_type'] = CountingAwardConfigModel::GOLD_LEAF_TYPE;
                $list[$item['op_activity_id']]['goods_gold_leaf_number'] = $item['amount'];
            }
        }

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
            } elseif ( $activitySign[$value['op_activity_id']]['award_status'] == CountingActivitySignModel::AWARD_STATUS_RECEIVED){
                $status = 5;
            }elseif ($activitySign[$value['op_activity_id']]['award_status'] == CountingActivitySignModel::AWARD_STATUS_PENDING && $activitySign[$value['op_activity_id']]['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_YES ){
                $status = 4;
            } elseif ($activitySign[$value['op_activity_id']]['qualified_status'] == CountingActivitySignModel::QUALIFIED_STATUS_NO && $value['join_end_time'] < time()){
                //未达标且过了参与截止时间
                $status = 6;
            }else{
                $status = 2;
            }

            $ret[] = [
                'reminder_pop' => $value['reminder_pop'] ? AliOSS::replaceCdnDomainForDss($value['reminder_pop']) : '',
                'award_thumbnail' => $value['award_thumbnail'] ? AliOSS::replaceCdnDomainForDss($value['award_thumbnail']) : '',
                'rule_type' =>$value['rule_type'],
                'rule_type_name' => CountingActivityModel::RULE_TYPE_MAP[$value['rule_type']],
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
                'goods_type' => $value['goods_type'],
                'goods_gold_leaf_number' => $value['goods_gold_leaf_number'] ?? 0,
            ];
        }

        if (empty($banner)) {
            $defaultBanner = DictConstants::get(DictConstants::STUDENT_TASK_DEFAULT, 'banner');
            $banner[] = AliOSS::replaceCdnDomainForDss($defaultBanner);
        }
        return [$ret,$banner];

    }

    /**
     * 领奖记录
     *
     * @param int $studentId
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getAwardRecord(int $studentId, int $page, int $count):array
    {

        $ret = [
            'count' => 0,
            'list' => [],
        ];

        $number = CountingActivitySignModel::getActivitySignCount($studentId);

        if (empty($number)) return $ret;

        $ret['count'] = $number;

        $activity = CountingActivitySignModel::getActivitySignInfo($studentId, $page, $count);

        if (empty($activity)) return $ret;

        $opActivityIds = array_column($activity,'op_activity_id');

        $award = CountingActivityAwardModel::getRecords([
            'op_activity_id' => $opActivityIds,
            'student_id'     => $studentId,
            'ORDER'          => ['type' => 'ASC']
        ], ['op_activity_id', 'type', 'unique_id', 'shipping_status', 'goods_id', 'amount']);

        $goodsIds = array_filter(array_unique(array_column($award, 'goods_id')));

        $goodsInfo = [];
        if (!empty($goodsIds)){
            //商品信息
            $goodsInfo = (new Erp())->getLogisticsGoodsList(['goods_id' => implode(',',$goodsIds)]);
            $goodsInfo = array_column($goodsInfo['data']['list'] ?? [],'name','id');
        }

        $record = [];
        foreach ($award as $item) {
            if ($item['type'] == CountingActivityAwardModel::TYPE_ENTITY) {

                if (!isset($record[$item['op_activity_id']][$item['express_number']])) {
                    $record[$item['op_activity_id']][$item['express_number']] = [
                        'type'        => $item['type'],
                        'status_show' => CountingActivityAwardModel::SHIPPING_STATUS_ENTITY_MAP[$item['shipping_status']],
                    ];
                }

                $record[$item['op_activity_id']][$item['express_number']]['goods'][] = [
                    'unique_id' => $item['unique_id'],
                    'amount'    => $item['amount'],
                    'goods_id'  => $item['goods_id'],
                    'name'      => $goodsInfo[$item['goods_id']] ?? '',
                ];

            } else {
                $record[$item['op_activity_id']][] = [
                    'type'        => $item['type'],
                    'status_show' => CountingActivityAwardModel::SHIPPING_STATUS_GOLD_LEAF_MAP[$item['shipping_status']],
                    'amount'      => $item['amount'],
                ];
            }
        }

        foreach ($activity as &$value){
            $value['award_time_show'] = date('Y-m-d H:i:s', $value['award_time']);
            $value['record'] = array_values($record[$value['op_activity_id']]);
        }

        $ret['list'] = $activity;

        return $ret;
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
            'student_id'
        ]);
        if (empty($sign)) throw new RunTimeException(['activity_award_is_disable']);

        //获取实物奖励数据
        $award = CountingActivityAwardModel::getRecords([
            'student_id' => $studentId,
            'op_activity_id' => $activityId,
            'type' => CountingActivityAwardModel::TYPE_ENTITY
        ],['goods_id','unique_id','amount','shipping_status','logistics_status','erp_address_id','address_detail','logistics_company','logistics_status','express_number','create_time']);

        if (empty($award)) throw new RunTimeException(['activity_award_is_disable']);


        $goodsIds = array_filter(array_unique(array_column($award, 'goods_id')));

        $goodsInfo = [];
        if (!empty($goodsIds)){
            //商品信息
            $goodsInfo = (new Erp())->getLogisticsGoodsList(['goods_id' => implode(',',$goodsIds)]);
            $goodsInfo = array_column($goodsInfo['data']['list'] ?? [],null,'id');
        }

        $logisticsStatus = DictConstants::getSet(DictConstants::MATERIAL_LOGISTICS_STATUS);

        $record = [];
        foreach ($award as $value) {

            if (!isset($record[$value['express_number']])){
                $value['status_show'] = CountingActivityAwardModel::SHIPPING_STATUS_ENTITY_MAP[$value['shipping_status']];
                if (!empty($value['express_number'])){
                    $value['status_show'] = $logisticsStatus[$value['logistics_status']] ?? '';
                }
                $value['award_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
                $record[$value['express_number']] = $value;
            }
            $record[$value['express_number']]['goods'][] = [
                'amount' => $value['amount'],
                'goods_id' => $value['goods_id'],
                'goods_name' => $goodsInfo[$value['goods_id']]['name'] ?? '',
                'thumb_url' => $goodsInfo[$value['goods_id']]['thumb_url'] ?? '',
                'goods_attribute' => $goodsInfo[$value['goods_id']]['goods_attribute'] ?? '',
            ];

            unset($record[$value['unique_id']]['goods_id'],$record[$value['unique_id']]['amount']);

        }

        return array_values($record);
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

        if (empty($award)) return [];

        $expressInfo = [];

        if (!empty($award['unique_id'])) {
            $expressInfo = (new Erp())->getExpressDetails($award['unique_id']);
        }

        $ret['logistics_no'] = $expressInfo['logistics_no'] ?? '';
        $ret['company'] = $expressInfo['company'] ?? '';
        $ret['address_detail'] = $award['address_detail'] ?? '{}';

        $deliver[] = [
            'node' => '已发货',
            'acceptTime' => Util::formatTimeToChinese($award['create_time']),
            'acceptStation' => '小叶子已为您发货，包裹待揽收'
        ];

        $ret['express_record'] = array_merge_recursive(self::formatExpressRecord($expressInfo['logistics_detail'] ?? []),$deliver);

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
            if (in_array($value['node'], $nodeArr)) {
                $value['node'] = '';
            } else {
                $nodeArr[] = $value['node'];
            }
            $value['acceptTime'] = Util::formatTimeToChinese(strtotime($value['acceptTime']));

        }
        return $record;
    }


    /**
     * 获取活动实物信息
     *
     * @param int $activityId
     * @return array
     */
    public static function getGoodsInfo(int $activityId): array
    {
        $awardConfig = CountingAwardConfigModel::getRecords([
            'op_activity_id' => $activityId,
            'type'           => CountingAwardConfigModel::PRODUCT_TYPE,
            'status'         => CountingAwardConfigModel::EFFECTIVE_STATUS,
        ], ['op_activity_id', 'goods_id', 'amount']);

        if (empty($awardConfig)) {
            return [];
        }
        $goodsIds = array_column($awardConfig, 'goods_id');
        //商品信息
        $goods     = (new Erp())->getLogisticsGoodsList(['goods_id' => implode(',',$goodsIds)]);
        $goodsInfo = array_column($goods['data']['list'] ?? [], null, 'id');

        foreach ($awardConfig as &$value) {
            $value['goods_name']      = $goodsInfo[$value['goods_id']]['name'] ?? '';
            $value['thumb_url']       = $goodsInfo[$value['goods_id']]['thumb_url'] ?? '';
            $value['goods_attribute'] = $goodsInfo[$value['goods_id']]['goods_attribute'] ?? '';
        }
        return $awardConfig;
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
    public static function getRewards(int $activityId, int $studentId,int $erpAddressId = 0, string $addressDetail = '{}')
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
                    'student_id'      => $studentId,
                    'shipping_status' => CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
                    'type'            => $item['type'],
                    'unique_id'       => '',
                    'goods_id'        => 0,
                    'goods_code'      => '',
                    'create_time'     => $time,
                    'amount'          => $item['amount'],
                    'op_activity_id'  => $activityId,
                    'erp_address_id'  => 0,
                    'address_detail'  => '{}',
                ];
            } else {
                if (empty($erpAddressId)) throw new RunTimeException(['address_id_must_be_integer']);
                $activityAward[] = [
                    'sign_id'         => $sign['id'],
                    'student_id'      => $studentId,
                    'shipping_status' => CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
                    'type'            => $item['type'],
                    'unique_id'       => CountingActivityAwardModel::UNIQUE_ID_PREFIX . sprintf("%08d",
                            $sign['id']) . sprintf("%02d", $key),
                    'goods_id'        => $item['goods_id'],
                    'goods_code'      => $item['goods_code'],
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

        $res = CountingActivityAwardModel::grantAward($activityAward, $sign['id']);

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

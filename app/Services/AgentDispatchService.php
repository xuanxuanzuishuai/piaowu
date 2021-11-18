<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/10
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\BillMapModel;
use App\Models\AgentDispatchLogModel;
use App\Models\AgentModel;
use App\Models\AgentUserModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\StudentReferralStudentStatisticsModel;


/**
 * 代理商关系绑定和订单归属资格分配器检测类
 * Class AgentBindAndAwardCheckService
 * @package App\Services
 */
class AgentDispatchService
{
    //绑定关系的代理商ID
    private static $bindAgentId = 0;
    //订单归属的代理商ID
    private static $billOwnAgentId = 0;
    //转介绍推荐人学生ID
    private static $referralStudentId = 0;
    //是否有代理商绑定关系：true有 false无
    private static $haveBindAgentData = false;
    //有效绑定关系代理商ID
    private static $validBindAgentId = 0;
    //无效绑定关系代理商ID
    private static $invalidBindAgentId = 0;
    //订单ID
    private static $parentBillId = 0;
    //学生ID
    private static $studentId = 0;
    //订单关联的代理商ID
    private static $billMapAgentId = 0;
    //订单关联的代理商分成模式
    private static $billMapAgentDivisionModel = 0;
    //课包类型:1体验卡 2年卡
    private static $packageType = 0;
    //学生是否是第一次购买订单:true是 false不是
    private static $isFirstBuyOrder = true;
    //学生是否购买过体验卡和年卡：true是 false不是
    private static $haveBuyTrailOrder = false;
    private static $haveBuyNormalOrder = false;
    //学生是否是第一次购买体验卡/年卡:true是 false不是
    private static $firstBuyTrailOrder = false;
    private static $firstBuyNormalOrder = false;
    //分配器分配失败的结果类型，对应数据表agent_dispatch_log中的result_type
    private static $dispatchResultType = 0;
    //当前时间戳
    private static $time = 0;
    //分配日志
    private static $dispatchLogData = [];
    //是否是代理渠道购买:true是 false不是
    private static $isAgentChannelBuy = true;


    /**
     * 构造方法
     * AgentBindAndAwardCheckService constructor.
     * @param $parentBillId
     * @param $studentId
     * @param $packageInfo
     */
    public function __construct($parentBillId, $studentId, $packageInfo)
    {
        self::$time = time();
        self::$studentId = $studentId;
        self::$parentBillId = $parentBillId;
        self::$packageType = $packageInfo['package_type'];
        self::setReferralId();
        self::setBillMapData();
        self::checkIsStudentFirstOrder();
    }

    /**
     * 析构函数：记录分配过程日志数据
     */
    public function __destruct()
    {
        self::$dispatchLogData = [
            'student_id' => self::$studentId,
            'parent_bill_id' => self::$parentBillId,
            'result_type' => self::$dispatchResultType,
            'create_time' => self::$time,
            'content' => json_encode([
                'package_type' => self::$packageType,
                'is_first_buy_order' => self::$isFirstBuyOrder,
                'have_buy_trail_order' => self::$haveBuyTrailOrder,
                'first_buy_trail_order' => self::$firstBuyTrailOrder,
                'have_buy_normal_order' => self::$haveBuyNormalOrder,
                'first_buy_normal_order' => self::$firstBuyNormalOrder,
                'referral_student_id' => self::$referralStudentId,
                'have_bind_agent_data' => self::$haveBindAgentData,
                'valid_bind_agent_id' => self::$validBindAgentId,
                'bill_map_agent_division_model' => self::$billMapAgentDivisionModel,
                'bill_map_agent_id' => self::$billMapAgentId,
            ])
        ];
        AgentDispatchLogModel::insertRecord(self::$dispatchLogData);
    }

    /**
     * 获取绑定关系和订单归属代理信息
     * @return array|mixed
     */
    public static function getBindAndBillOwnAgentData()
    {
        //订单无代理映射关系
        if (empty(self::$billMapAgentId)) {
            self::$dispatchResultType = AgentDispatchLogModel::RESULT_TYPE_BILL_NOT_AGENT_MAP_RELATION;
            SimpleLogger::info('bill not map agent relation', []);
            return [];
        }

        //体验卡禁止重复购买
        if (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
            if ((self::$haveBuyTrailOrder === true) && (self::$firstBuyTrailOrder === false)) {
                self::$dispatchResultType = AgentDispatchLogModel::RESULT_TYPE_REPEAT_BUY_TRAIL;
                SimpleLogger::info('trail stop repeat buy', []);
                return [];
            }
        }
        //已购买年卡，再次购买体验卡
        if ((self::$haveBuyNormalOrder === true) && (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL)) {
            self::$dispatchResultType = AgentDispatchLogModel::RESULT_TYPE_HAVE_BUY_NORMAL_AFTER_STOP_BUY_TRAIL;
            SimpleLogger::info('have buy normal, stop buy trail', []);
            return [];
        }
        //存在有效的绑定关系
        if (self::$validBindAgentId) {
            self::$billOwnAgentId = self::$bindAgentId = self::$validBindAgentId;
        } else {
            self::$billOwnAgentId = self::$bindAgentId = self::$billMapAgentId;
        }
        //检测是否需要执行绑定关系操作:1.存在学生推荐人,非首次购买 2.非代理商渠道购买,均不需要绑定关系操作
        if ((!empty(self::$referralStudentId) && (self::$isFirstBuyOrder !== true) && empty(self::$validBindAgentId) && empty(self::$invalidBindAgentId)) ||
            (self::$isAgentChannelBuy === false && empty(self::$validBindAgentId))) {
            //存在学生推荐人,非首次购买：不再绑定
            self::$bindAgentId = 0;
        }
        return ['own_agent_id' => self::$billOwnAgentId, 'bind_agent_id' => self::$bindAgentId];
    }

    /**
     * 设置订单映射关系数据
     */
    private static function setBillMapData()
    {
        $billMapInfo = BillMapModel::get(self::$parentBillId, self::$studentId, BillMapModel::USER_TYPE_AGENT);
        if (!empty($billMapInfo)) {
            self::$billMapAgentId = $billMapInfo['user_id'];
        } else {
            self::$isAgentChannelBuy = false;
            //无代理绑定关系
            if (empty(self::$haveBindAgentData)) {
                return;
            }
            //设置订单映射代理商关系优先级:有效绑定>无效绑定
            if (!empty(self::$validBindAgentId)) {
                self::$billMapAgentId = self::$validBindAgentId;
            } else {
                self::$billMapAgentId = self::$invalidBindAgentId;
            }
        }
        //设置分成模式
        self::$billMapAgentDivisionModel = AgentModel::getAgentParentData([self::$billMapAgentId])[0]['division_model'];
    }

    /**
     * 设置学生推荐人ID以及代理商绑定关系
     */
    private static function setReferralId()
    {
        //转介绍关系
        $referralInfo = StudentReferralStudentStatisticsModel::getRecord(['student_id' => self::$studentId], ['referee_id']);
        if (!empty($referralInfo)) {
            self::$referralStudentId = $referralInfo['referee_id'];
        }
        //代理绑定关系
        $agentBindData = AgentUserModel::getRecord(
            [
                'user_id' => self::$studentId,
                'stage[>=]' => AgentUserModel::STAGE_TRIAL,
                'ORDER' => ['id' => 'DESC'],
            ]
            ,
            ['agent_id', 'deadline', 'stage', 'bind_time']);
        if (!empty($agentBindData)) {
            self::$haveBindAgentData = true;
            if (($agentBindData['deadline'] >= self::$time)) {
                self::$validBindAgentId = $agentBindData['agent_id'];
            } else {
                self::$invalidBindAgentId = $agentBindData['agent_id'];
            }
        }
    }

    /**
     * 检测是否是学生首单
     */
    private static function checkIsStudentFirstOrder()
    {
        $order = DssGiftCodeModel::getStudentGiftCodeList(self::$studentId,
            [DssCategoryV1Model::DURATION_TYPE_TRAIL, DssCategoryV1Model::DURATION_TYPE_NORMAL],
            [UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserCenter::APP_ID_PRACTICE], 0);
        //标记是否是首次购买
        if (count($order) > 1) {
            self::$isFirstBuyOrder = false;
        }
        //订单按照类型分组
        $orderGroup = [];
        foreach ($order as $ov) {
            $orderGroup[$ov['sub_type']][] = $ov['parent_bill_id'];
        }

        //订单分类数量
        $orderCount = array_count_values(array_column($order, 'sub_type'));
        //标记是否购买过体验卡
        if (!empty($orderCount[DssCategoryV1Model::DURATION_TYPE_TRAIL])) {
            if ($orderCount[DssCategoryV1Model::DURATION_TYPE_TRAIL] >= 1) {
                self::$haveBuyTrailOrder = true;
            }
            if ($orderGroup[DssCategoryV1Model::DURATION_TYPE_TRAIL][0] === self::$parentBillId) {
                self::$firstBuyTrailOrder = true;
            }
        }
        //标记是否购买过年卡
        if (!empty($orderCount[DssCategoryV1Model::DURATION_TYPE_NORMAL])) {
            if ($orderCount[DssCategoryV1Model::DURATION_TYPE_NORMAL] >= 1) {
                self::$haveBuyNormalOrder = true;
            }
            if ($orderCount[DssCategoryV1Model::DURATION_TYPE_NORMAL] == 1) {
                self::$firstBuyNormalOrder = true;
            }
        }
    }
}

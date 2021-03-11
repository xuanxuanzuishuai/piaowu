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
use App\Models\AgentBillMapModel;
use App\Models\AgentModel;
use App\Models\AgentUserModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssPackageExtModel;
use App\Models\StudentInviteModel;


/**
 * 代理商关系绑定和订单归属资格检测类
 * Class AgentBindAndAwardCheckService
 * @package App\Services
 */
class AgentBindAndAwardCheckService
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
    //当前时间戳
    private static $time = 0;


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
     * 检测最终选择的代理商状态
     * @return array
     */
    private static function checkAgentStatus()
    {
        $agentIds = ['bill_own_agent_id' => self::$billOwnAgentId, 'bind_agent_id' => self::$bindAgentId];
        //检测当前代理是否有效:包括父级
        $agentValidStatus = AgentService::checkAgentStatusIsValid($agentIds);
        foreach ($agentIds as $ak => $aid) {
            if (empty($agentValidStatus[$aid])) {
                $agentIds[$ak] = 0;
            }
        }
        return $agentIds;
    }

    /**
     * 获取绑定关系和订单归属代理信息
     * @return array
     */
    public static function getBindAndBillOwnAgentData()
    {
        //根据不同条件选择代理商数据
        if (self::$isFirstBuyOrder === true) {
            //首单
            self::firstOrderLogic();
        } else {
            //非首单
            self::haveOrderLogic();
        }
        return self::checkAgentStatus();
    }

    /**
     * 首单处理逻辑
     */
    private static function firstOrderLogic()
    {
        //订单无代理映射关系，直接返回
        if (empty(self::$billMapAgentId)) {
            return;
        }
        //学生有学生推荐人
        if (!empty(self::$referralStudentId)) {
            //购买体验卡:直接返回
            if (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
                return;
            }
            //购买年卡:绑定关系无效	订单归属有效
            if ((self::$packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) && (self::$billMapAgentDivisionModel != AgentModel::DIVISION_MODEL_LEADS_AND_SALE)) {
                return;
            } else {
                self::$billOwnAgentId = self::$billMapAgentId;
                return;
            }
        } else {
            //学生无学生推荐人，购买体验卡&&年卡:绑定关系有效	订单归属有效
            self::$billOwnAgentId = self::$bindAgentId = self::$billMapAgentId;
        }
    }

    /**
     * 非首单处理逻辑
     */
    private static function haveOrderLogic()
    {
        if ((self::$haveBuyTrailOrder === true) && (self::$firstBuyTrailOrder === false) && (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL)) {
            //学生已购买过体验卡，再次购买体验卡:无后续操作直接返回
            return;
        } elseif ((self::$haveBuyNormalOrder === true) && (self::$firstBuyTrailOrder === true) && (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL)) {
            //学生已购买年卡，首次购买体验卡:无后续操作直接返回
            return;
        } elseif ((self::$haveBuyTrailOrder === true) && (self::$firstBuyNormalOrder === true) && (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL)) {
            //学生已购买过体验卡，首次购买年卡
            self::checkByReferralAndAgentStatus();
            return;
        } elseif ((self::$firstBuyNormalOrder === false) && (self::$packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL)) {
            //学生重复购买年卡
            self::checkByReferralAndAgentStatus();
        }
    }

    /**
     * 通过学生推荐人以及学生代理状态判断绑定关系和订单归属
     */
    private static function checkByReferralAndAgentStatus()
    {
        if (!empty(self::$referralStudentId) && (self::$billMapAgentDivisionModel == AgentModel::DIVISION_MODEL_LEADS_AND_SALE) && !empty(self::$billMapAgentId)) {
            //有学生推荐人，订单映射代理商数据不为空，并且代理商分成模式是（线索+售卖）
            self::$billOwnAgentId = self::$billMapAgentId;
        } else {//无推荐人
            if (empty(self::$referralStudentId) && empty(self::$haveBindAgentData) && (self::$billMapAgentDivisionModel == AgentModel::DIVISION_MODEL_LEADS_AND_SALE) && !empty(self::$billMapAgentId)) {
                //无推荐人，无代理，订单映射代理商数据不为空，并且代理商分成模式是（线索+售卖）
                self::$billOwnAgentId = self::$bindAgentId = self::$billMapAgentId;
            } elseif (empty(self::$referralStudentId) && !empty(self::$haveBindAgentData)) {
                //无推荐人，有代理
                if (!empty(self::$validBindAgentId) && (self::$validBindAgentId != self::$billMapAgentId)) {
                    //绑定期中:订单映射代理商与绑定期中的代理商不是用一个账户,直接返回
                    SimpleLogger::info('agent conflict', ['valid_bind_agent' => self::$validBindAgentId, 'bill_map_agent' => self::$billMapAgentId]);
                } elseif ((self::$billMapAgentDivisionModel == AgentModel::DIVISION_MODEL_LEADS) &&
                    (self::$firstBuyNormalOrder === false)) {
                    //线索获量模式中，年卡订单只绑定学员首次购买年卡订单；线索+售卖模式中，年卡订单绑定学员每次购买年卡订单
                    SimpleLogger::info('student not first normal order', [
                        'valid_bind_agent' => self::$validBindAgentId,
                        'bill_map_agent' => self::$billMapAgentId,
                        'division_model' => self::$billMapAgentDivisionModel]);
                } else {
                    //已解绑/其他条件：绑定关系/订单归属属于订单映射代理商
                    self::$billOwnAgentId = self::$bindAgentId = self::$billMapAgentId;
                }
            }
        }
    }


    /**
     * 设置订单映射关系数据
     */
    private static function setBillMapData()
    {
        $billMapInfo = self::getBillMapInfo();
        if (!empty($billMapInfo)) {
            self::$billMapAgentId = $billMapInfo['agent_id'];
            self::$billMapAgentDivisionModel = $billMapInfo['division_model'];
        } else {
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
            self::$billMapAgentDivisionModel = AgentModel::getById(self::$billMapAgentId)['division_model'];
        }
    }

    /**
     * 获取订单映射关系数据
     * @return array
     */
    private static function getBillMapInfo()
    {
        return AgentBillMapModel::getBillMapAgentData(self::$parentBillId, self::$studentId);
    }

    /**
     * 设置学生推荐人ID以及代理商绑定关系
     */
    private static function setReferralId()
    {
        //转介绍关系
        $referralInfo = self::getReferralInfo();
        if ($referralInfo['referee_type'] == StudentInviteModel::REFEREE_TYPE_STUDENT) {
            self::$referralStudentId = $referralInfo['referee_id'];
        }
        //代理绑定关系
        $agentBindData = AgentUserModel::getRecords(
            [
                'user_id' => self::$studentId,
                'stage[>=]' => AgentUserModel::STAGE_TRIAL]
            ,
            ['agent_id', 'deadline', 'stage']);
        if (!empty($agentBindData)) {
            self::$haveBindAgentData = true;
            foreach ($agentBindData as $abk => $abv) {
                if (($abv['deadline'] >= self::$time && $abv['stage'] == AgentUserModel::STAGE_TRIAL) ||
                    ($abv['stage'] == AgentUserModel::STAGE_FORMAL && $abv['stage'] == AgentUserModel::STAGE_FORMAL)) {
                    self::$validBindAgentId = $abv['agent_id'];
                } else {
                    self::$invalidBindAgentId = $abv['agent_id'];
                }
            }
        }
        SimpleLogger::info('referral record', [
            'referral_student_id' => self::$referralStudentId,
            'have_bind_agent_data' => self::$haveBindAgentData,
            'valid_bind_agent_id' => self::$validBindAgentId,
        ]);
    }

    /**
     * 获取学生转介绍关系数据
     * @return mixed
     */
    private static function getReferralInfo()
    {
        return StudentInviteModel::getRecord(['student_id' => self::$studentId, 'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT]);
    }

    /**
     * 检测是否是学生首单
     */
    private static function checkIsStudentFirstOrder()
    {
        $order = StudentService::getStudentGiftCodeList(self::$studentId);
        //标记是否是首次购买
        if (count($order) > 1) {
            self::$isFirstBuyOrder = false;
        }
        //订单分类数量
        $orderCount = [
            DssCategoryV1Model::DURATION_TYPE_TRAIL => 0,
            DssCategoryV1Model::DURATION_TYPE_NORMAL => 0,
        ];
        foreach ($order as $ok => $ov) {
            $orderCount[$ov['package_type']] += 1;
        }
        //标记是否购买过体验卡
        if ($orderCount[DssCategoryV1Model::DURATION_TYPE_TRAIL] >= 1) {
            self::$haveBuyTrailOrder = true;
        }
        if ($orderCount[DssCategoryV1Model::DURATION_TYPE_TRAIL] == 1) {
            self::$firstBuyTrailOrder = true;
        }
        //标记是否购买过年卡
        if ($orderCount[DssCategoryV1Model::DURATION_TYPE_NORMAL] >= 1) {
            self::$haveBuyNormalOrder = true;
        }
        if ($orderCount[DssCategoryV1Model::DURATION_TYPE_NORMAL] == 1) {
            self::$firstBuyNormalOrder = true;
        }
        SimpleLogger::info('buy record status', [
            'is_first_buy_order' => self::$isFirstBuyOrder,
            'have_buy_trail_order' => self::$haveBuyTrailOrder,
            'first_buy_trail_order' => self::$firstBuyTrailOrder,
            'have_buy_normal_order' => self::$haveBuyNormalOrder,
            'first_buy_normal_order' => self::$firstBuyNormalOrder]);
    }
}

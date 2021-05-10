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
use App\Libs\EventListener\AgentOpEvent;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\RC4;
use App\Libs\RedisDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\AgentApplicationModel;
use App\Models\AgentAwardBillExtModel;
use App\Models\AgentAwardDetailModel;
use App\Models\BillMapModel;
use App\Models\AgentDivideRulesModel;
use App\Models\AgentModel;
use App\Models\AgentOperationLogModel;
use App\Models\AgentSalePackageModel;
use App\Models\AgentUserModel;
use App\Models\AreaCityModel;
use App\Models\AreaProvinceModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssDictModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\GoodsResourceModel;
use App\Models\ParamMapModel;
use App\Models\UserWeiXinInfoModel;
use App\Models\PosterModel;
use App\Models\UserWeiXinModel;
use I18N\Lang;
use Medoo\Medoo;

class AgentService
{
    /**
     * 新增代理商
     * @param $params
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function addAgent($params, $employeeId)
    {
        $time = time();
        //agent数据
        $agentInsertData = [
            'employee_id' => $employeeId,
            'parent_id' => $params['parent_id'] ?? 0,
            'service_employee_id' => empty($params['service_employee_id']) ? 0 : $params['service_employee_id'],
            'uuid' => self::agentAuth($params['name'], $params['mobile']),
            'mobile' => $params['mobile'],
            'name' => $params['name'],
            'type' => $params['agent_type'] ?? 0,
            'division_model' => $params['division_model'] ?? 0,
            'country_code' => $params['country_code'],
            'create_time' => $time,
        ];
        if (self::checkAgentExists($agentInsertData['mobile'], $agentInsertData['country_code'])) {
            throw new RunTimeException(['agent_have_exist']);
        }
        //agent_divide_rules数据
        $agentDivideRulesInsertData = [
            'app_id' => $params['app_id'],
            'employee_id' => $employeeId,
            'type' => empty($params['divide_type']) ? AgentDivideRulesModel::TYPE_LEADS : (int)$params['divide_type'],
            'rule' => '{}',// 占位操作
            'create_time' => $time,
        ];
        //agent_info
        $agentInfoInsertData = [
            'country' => $params['country_id'],
            'province' => (int)$params['province_code'],
            'city' => (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
            'create_time' => $time,
        ];
        //agent_sale_package
        $packageIds = [];
        if (!empty($params['package_id'])) {
            $packageIds = explode(',',$params['package_id']);
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentModel::add($agentInsertData, $agentDivideRulesInsertData, $agentInfoInsertData,$packageIds, $params['app_id']);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        } else {
            $db->commit();
        }
        return true;
    }

    /**
     * 编辑代理商
     * @param $params
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function updateAgent($params, $employeeId)
    {
        $time = time();
        //agent数据
        $agentExists = AgentModel::getRecords(['OR' => ['id' => $params['agent_id'], 'AND' => ['mobile' => $params['mobile'], 'country_code' => $params['country_code']]]], ['id', 'mobile']);
        if (empty($agentExists)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        if (count($agentExists) > 1) {
            throw new RunTimeException(['agent_mobile_is_repeat']);
        }
        //agent数据
        $agentUpdateData = [
            'mobile' => $params['mobile'],
            'name' => $params['name'],
            'type' => $params['agent_type'],
            'service_employee_id' => empty($params['service_employee_id']) ? 0 : (int)$params['service_employee_id'],
            'country_code' => $params['country_code'],
            'update_time' => $time,
            'division_model' => $params['division_model'] ?? 0,
        ];
        //agent_divide_rules数据
        $agentDivideRulesInsertData = [
            'app_id' => $params['app_id'],
            'agent_id' => $params['agent_id'],
            'employee_id' => $employeeId,
            'type' => $params['divide_type'],
            'rule' => '{}',// 占位操作
            'create_time' => $time,
        ];
        //agent_info
        $agentInfoUpdateData = [
            'country' => $params['country_id'],
            'province' => empty($params['province_code']) ? 0 : (int)$params['province_code'],
            'city' => empty($params['city_code']) ? 0 : (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
            'update_time' => $time,
        ];
        //agent_sale_package
        $packageIds = [];
        if (!empty($params['package_id'])) {
            $packageIds = explode(',',$params['package_id']);
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentModel::update($params['agent_id'], $agentUpdateData, $agentDivideRulesInsertData, $agentInfoUpdateData, $packageIds, $params['app_id']);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        //操作日志记录
        self::agentDataUpdateOpLogEvent($params['agent_id'], $employeeId, ['agent_type' => $params['agent_type'], 'division_model' => $params['division_model']], AgentOperationLogModel::OP_TYPE_AGENT_DATA_UPDATE);
        return true;
    }


    /**
     * 代理运营统计数据
     * @param $agentId
     * @return array
     */
    public static function agentStaticsData($agentId)
    {
        $staticsData = [
            'detail' => [],
            'spread' => [],
            'secondary_agent' => [],
        ];
        //详情
        $staticsData['detail'] = self::detailAgent($agentId);
        if (!empty($staticsData['detail'])) {
            //推广数据统计
            $spreadData = self::agentSpreadData([$agentId]);
            $staticsData['spread'] = [
                "referral_student_count" => $spreadData[$agentId]['total']['s_count'],
                "referral_bill_count" => $spreadData[$agentId]['total']['b_count'],
                "direct_referral_student_count" => $spreadData[$agentId]['self']['s_count'],
                "direct_referral_bill_count" => $spreadData[$agentId]['self']['b_count'],
                "secondary_count" => $spreadData[$agentId]['son_num'],
            ];
            //二级代理
            $staticsData['secondary_agent'] = self::formatAgentData(self::agentSecondaryData([$agentId], $spreadData[$agentId]['son'])[$agentId]);
            $staticsData['detail'] = self::formatAgentData([$staticsData['detail']]);
        }
        return $staticsData;
    }


    /**
     * 获取一级代理的二级代理数据
     * @param $agentIds
     * @param $spreadData
     * @return array
     */
    private static function agentSecondaryData($agentIds, $spreadData)
    {
        $data = array_fill_keys($agentIds, []);
        $secondaryList = AgentModel::agentSecondaryData($agentIds);
        if (empty($secondaryList)) {
            return $data;
        }
        //推广数据
        array_map(function ($item) use (&$data, $spreadData) {
            $item['referral_student_count'] = $spreadData[$item['id']]['s_count'];
            $item['referral_bill_count'] = $spreadData[$item['id']]['b_count'];
            $data[$item['parent_id']][] = $item;
        }, $secondaryList);
        return $data;
    }

    /**
     * 代理推广订单:订单归属人/成单人去重统计
     * @param $params
     * @return array
     */
    public static function getAgentRecommendDuplicationBills($params)
    {
        //推广订单类型:1累计订单订单2直接推广订单
        if ($params['recommend_bill_type'] == AgentAwardBillExtModel::AGENT_RECOMMEND_BILL_TYPE_SELF) {
            $agentList = implode(',', array_column(AgentModel::getRecords(['OR' => ['id' => $params['agent_id'], 'parent_id' => $params['agent_id']], 'ORDER' => ['parent_id' => "ASC"]], ['id[Int]']), 'id'));
        } else {
            $agentList = $params['agent_id'];
        }
        $billList = AgentAwardDetailModel::getAgentRecommendDuplicationBill($agentList, false, $params['page'], $params['count']);
        if (empty($billList['count'])) {
            return $billList;
        }
        //查询订单的详细数据
        $giftCodeDetail = array_column(DssGiftCodeModel::getGiftCodeDetailByBillId(array_column($billList['list'], 'parent_bill_id')), null, 'parent_bill_id');
        //查询成单代理商数据&查询归属代理商数据
        $recommendBillsSignerAgentIdArr = array_column($billList['list'], 'signer_agent_id');
        $recommendBillsOwnAgentIdArr = array_column($billList['list'], 'own_agent_id');
        $recommendBillsAgentData = AgentModel::getAgentParentData(array_unique(array_merge($recommendBillsSignerAgentIdArr, $recommendBillsOwnAgentIdArr)));
        if (!empty($recommendBillsAgentData)) {
            $recommendBillsAgentData = array_column($recommendBillsAgentData, null, 'id');
        }
        //组合课包数据以及成单人数据
        foreach ($billList['list'] as $rsk => &$rsv) {
            //成单人
            $rsv['signer_first_agent_name'] = $rsv['signer_second_agent_name'] = $rsv['signer_first_agent_id'] = $rsv['signer_second_agent_id'] = "";
            if (!empty($rsv['signer_agent_id'])) {
                $rsv['signer_first_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['name'];
                $rsv['signer_second_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['name'] : "";
                $rsv['signer_first_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['p_id'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['id'];
                $rsv['signer_second_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['id'] : "";
            }
            //课包
            $rsv['bill_package_id'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_package_id'];
            $rsv['bill_amount'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_amount'];
            $rsv['code_status'] = $giftCodeDetail[$rsv['parent_bill_id']]['code_status'];
            $rsv['buy_time'] = $giftCodeDetail[$rsv['parent_bill_id']]['buy_time'];
            $rsv['package_name'] = $giftCodeDetail[$rsv['parent_bill_id']]['package_name'];
            //归属人
            $rsv['first_agent_name'] = !empty($recommendBillsAgentData[$rsv['own_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['own_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['own_agent_id']]['name'];
            $rsv['second_agent_name'] = !empty($recommendBillsAgentData[$rsv['own_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['own_agent_id']]['name'] : "";
            $rsv['first_agent_id'] = !empty($recommendBillsAgentData[$rsv['own_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['own_agent_id']]['p_id'] : $recommendBillsAgentData[$rsv['own_agent_id']]['id'];
            $rsv['second_agent_id_true'] = !empty($recommendBillsAgentData[$rsv['own_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['own_agent_id']]['id'] : "";
            $rsv['agent_type'] = $recommendBillsAgentData[$rsv['own_agent_id']]['agent_type'];
            $rsv['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        }
        array_map(function ($bv) use (&$parentBillIdStr) {
            $parentBillIdStr .= "'" . $bv['parent_bill_id'] . "',";
        }, $billList['list']);
        return self::formatRecommendBillsData($billList);
    }

    /**
     * 推广数据统计
     * @param $parentAgentIds
     * @return array
     */
    private static function agentSpreadData($parentAgentIds)
    {
        //代理数据
        $agentList = AgentModel::getRecords(['OR' => ['id' => $parentAgentIds, 'parent_id' => $parentAgentIds], 'ORDER' => ['parent_id' => "ASC"]], ['id', 'parent_id']);
        //推广人数量
        $agentIds = array_column($agentList, 'id');
        $agentIdStr = implode(',', $agentIds);
        $dataTree = array_fill_keys($agentIds, []);
        $referralStudents = array_column(AgentUserModel::getAgentStudentCount($agentIdStr), null, 'agent_id');
        //等级分组
        $levelGroup = [];
        foreach ($agentList as $kk => $vv) {
            if (empty($vv['parent_id'])) {
                $vv['parent_id'] = $vv['id'];
            }
            $levelGroup[$vv['parent_id']][] = $vv['id'];
        }

        array_walk($agentList, function ($item) use (&$dataTree, $referralStudents,$levelGroup) {
            if ($item['parent_id'] == 0) {
                //一级代理直接推广数据
                $dataTree[$item['id']]['son_num'] = 0;
                $dataTree[$item['id']]['total']['s_count'] = $dataTree[$item['id']]['self']['s_count'] = $referralStudents[$item['id']]['s_count'] ?? 0;
                $dataTree[$item['id']]['total']['b_count'] = AgentAwardDetailModel::getAgentRecommendDuplicationBill(implode(',', $levelGroup[$item['id']]))['count'];
                $dataTree[$item['id']]['self']['b_count'] = AgentAwardDetailModel::getAgentRecommendDuplicationBill($item['id'])['count'];
            } else {
                //一级代理的下属二级推广数据
                $dataTree[$item['parent_id']]['son'][$item['id']]['s_count'] = $referralStudents[$item['id']]['s_count'] ?? 0;
                $dataTree[$item['parent_id']]['son'][$item['id']]['b_count'] = AgentAwardDetailModel::getAgentRecommendDuplicationBill($item['id'])['count'];
                //推广数据汇总
                $dataTree[$item['parent_id']]['total']['s_count'] += $dataTree[$item['parent_id']]['son'][$item['id']]['s_count'];
                //一级代理发展的下属二级代理总数
                $dataTree[$item['parent_id']]['son_num'] += 1;
            }
        });
        return $dataTree;
    }


    /**
     * 获取代理账户详情
     * @param $agentId
     * @return array
     */
    public static function detailAgent($agentId)
    {
        //详情
        $detail = AgentModel::detail($agentId);
        //微信数据:是否绑定,昵称
        if (!empty($detail)) {
            $bindData = UserWeiXinModel::userBindData($agentId, UserWeiXinModel::USER_TYPE_AGENT, UserWeiXinModel::BUSI_TYPE_AGENT_MINI, UserCenter::AUTH_APP_ID_OP_AGENT);
            $detail['wx_bind_status'] = empty($bindData) ? 0 : 1;
        }
        //获取代理商售卖课包列表数据
        $detail['package_list'] = AgentSalePackageModel::getPackageData($agentId, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
        return $detail;
    }


    /**
     * 获取一级代理数据列表
     * @param $params
     * @param $currentEmployeeId
     * @return array
     */
    public static function listAgent($params, $currentEmployeeId)
    {
        $where = [AgentModel::$table . '.parent_id' => 0];
        $data = ['list' => [], 'count' => 0];
        if (!empty($params['agent_id'])) {
            $where[AgentModel::$table . '.id'] = $params['agent_id'];
        }
        if (!empty($params['mobile'])) {
            $where[AgentModel::$table . '.mobile'] = $params['mobile'];
        }
        if (!empty($params['agent_type'])) {
            $where[AgentModel::$table . '.type'] = $params['agent_type'];
        }
        if (!empty($params['status'])) {
            $where[AgentModel::$table . '.status'] = $params['status'];
        }
        if (!empty($params['create_start_time'])) {
            $where[AgentModel::$table . '.create_time[>=]'] = $params['create_start_time'];
        }
        if (!empty($params['create_end_time'])) {
            $where[AgentModel::$table . '.create_time[<=]'] = $params['create_end_time'];
        }
        if (!empty($params['employee_name'])) {
            $employeeId = EmployeeModel::getRecord(['name' => $params['employee_name']], ['id']);
            if (empty($employeeId)) {
                return $data;
            }
            $where[AgentModel::$table . '.employee_id'] = $employeeId['id'];
        }
        if (!empty($params['service_employee_name'])) {
            $serviceEmployeeIds = EmployeeModel::getRecords(['name[~]' => $params['service_employee_name']], ['id']);
            if (empty($serviceEmployeeIds)) {
                return $data;
            }
            $where[AgentModel::$table . '.service_employee_id'] = array_column($serviceEmployeeIds,'id');
        }
        if (!empty($params['name'])) {
            $where[AgentModel::$table . '.name[~]'] = $params['name'];
        }
        if (!empty($params['division_model'])) {
            $where[AgentModel::$table . '.division_model'] = $params['division_model'];
        }
        //数据权限
        if ($params['only_read_self']) {
            $where["OR"] = [
                AgentModel::$table . '.service_employee_id' => $currentEmployeeId,
                AgentModel::$table . '.employee_id' => $currentEmployeeId,
            ];
        }
        $agentList = AgentModel::list($where, $params['page'], $params['count']);
        if (empty($agentList['list'])) {
            return $agentList;
        }
        $firstAgentIds = array_column($agentList['list'], 'id');
        $spreadData = self::agentSpreadData($firstAgentIds);
        //推广数据
        array_walk($agentList['list'], function (&$agv) use ($spreadData) {
            //二级代理数量
            $agv['secondary_count'] = $spreadData[$agv['id']]['son_num'];
            //推广学员总数
            $agv['referral_student_count'] = $spreadData[$agv['id']]['total']['s_count'];
            //推广订单总数
            $agv['referral_bill_count'] = $spreadData[$agv['id']]['total']['b_count'];

        });
        $agentList['list'] = self::formatAgentData($agentList['list']);
        return $agentList;
    }

    /**
     * 格式化数据
     * @param $agentData
     * @return mixed
     */
    private static function formatAgentData($agentData)
    {
        $province = $city = [];
        $provinceIds = array_column($agentData, 'province');
        $cityIds = array_column($agentData, 'city');
        if (!empty($provinceIds)) {
            $province = array_column(AreaProvinceModel::getRecords(['id' => $provinceIds], ['id', 'province_name']), null, 'id');
        }
        if (!empty($cityIds)) {
            $city = array_column(AreaCityModel::getRecords(['id' => $cityIds], ['id', 'city_name']), null, 'id');
        }
        $dict = DictConstants::getTypesMap([DictConstants::AGENT_TYPE['type'], DictConstants::AGENT['type'], DictConstants::PACKAGE_APP_NAME['type'], DictConstants::AGENT_DIVISION_MODEL['type']]);
        foreach ($agentData as &$agv) {
            // 省
            if (!empty($agv['province'])) {
                $agv['province_name'] = $province[$agv['province']]['province_name'];
            }
            // 市
            if (!empty($agv['city'])) {
                $agv['city_name'] = $city[$agv['city']]['city_name'];
            }
            //代理模式
            $agv['agent_type_name'] = $dict[DictConstants::AGENT_TYPE['type']][$agv['type']]['value'];
            $agv['status_name'] = $dict[DictConstants::AGENT['type']][$agv['status']]['value'];
            $agv['app_id_name'] = empty($agv['app_id']) ? '' : $dict[DictConstants::PACKAGE_APP_NAME['type']][$agv['app_id']]['value'];
            $agv['division_model_name'] = empty($agv['division_model']) ? '' : $dict[DictConstants::AGENT_DIVISION_MODEL['type']][$agv['division_model']]['value'];
        }
        return $agentData;
    }

    /**
     * 冻结代理商账户
     * @param $agentId
     * @param $operatorId
     * @param $opType
     * @return bool
     * @throws RunTimeException
     */
    public static function freezeAgent($agentId, $operatorId, $opType)
    {
        $agentData = AgentModel::getById($agentId);
        if (empty($agentData)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        $res = AgentModel::updateRecord(
            $agentId,
            [
                'status' => AgentModel::STATUS_FREEZE,
                'update_time' => time(),
                'freeze_time' => time(),
            ]
        );
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
        //记录操作日志
        self::agentFreezeStatusOpLogEvent($agentId, $operatorId, AgentModel::STATUS_FREEZE, $opType);
        return true;
    }

    /**
     * 解除冻结
     * @param $agentId
     * @param $operatorId
     * @param $opType
     * @return bool
     * @throws RunTimeException
     */
    public static function unFreezeAgent($agentId, $operatorId, $opType)
    {
        $agentData = AgentModel::getById($agentId);
        if (empty($agentData)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        if ($agentData['status'] != AgentModel::STATUS_FREEZE) {
            throw new RunTimeException(['agent_not_freeze_status']);
        }
        $res = AgentModel::updateRecord(
            $agentId,
            [
                'status' => AgentModel::STATUS_OK,
                'update_time' => time(),
                'freeze_time' => 0,
             ]
        );
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
        //记录操作日志
        self::agentFreezeStatusOpLogEvent($agentId, $operatorId, AgentModel::STATUS_OK, $opType);
        return true;
    }

    /**
     * 代理商授权
     * @param $name
     * @param $mobile
     * @return mixed
     * @throws RunTimeException
     */
    private static function agentAuth($name, $mobile)
    {
        // 用户中心授权
        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_op', 'app_secret_op']);
        $userCenter = new UserCenter($appId, $appSecret);
        $authResult = $userCenter->agentAuthorization(UserCenter::AUTH_APP_ID_OP_AGENT, $mobile, $name);
        if (empty($authResult["uuid"])) {
            throw new RunTimeException(['agent_auth_fail']);
        }
        //返回数据
        return $authResult["uuid"];
    }

    /**
     * 绑定用户openid信息
     * @param $appId
     * @param $mobile
     * @param $openId
     * @param string $countryCode
     * @param null $userType
     * @param null $busiType
     * @return array
     * @throws RunTimeException
     */
    public static function bindAgentWechat($appId, $mobile, $openId, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE, $userType = null, $busiType = null)
    {
        $agentInfo = AgentModel::getByMobile($mobile, $countryCode);
        if (empty($agentInfo)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        if (empty($userType)) {
            $userType = UserWeiXinModel::USER_TYPE_AGENT;
        }
        if (empty($busiType)) {
            $busiType = UserWeiXinModel::BUSI_TYPE_AGENT_MINI;
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        UserWeiXinModel::batchUpdateRecord(
            [
                'status' => UserWeiXinModel::STATUS_DISABLE,
                'update_time' => time(),
            ],
            [
                'user_id' => $agentInfo['id'],
                'open_id[!]' => $openId
            ]
        );
        $data = [
            'user_id'   => $agentInfo['id'],
            'user_type' => $userType,
            'open_id'   => $openId,
            'status'    => UserWeiXinModel::STATUS_NORMAL,
            'busi_type' => $busiType,
            'app_id'    => $appId,
        ];
        $bindInfo = UserWeiXinModel::getRecord($data);
        if (empty($bindInfo)) {
            $data['create_time'] = time();
            $count = UserWeiXinModel::insertRecord($data);
            if (empty($count)) {
                $db->rollBack();
                throw new RunTimeException(['insert_failure']);
            }
        }
        $db->commit();
        $token = AgentMiniAppTokenService::generateToken($agentInfo['id'], $userType, $appId, $openId);
        return [$token, $agentInfo];
    }

    /**
     * 小程序退出登录(解绑)
     * @param $openId
     * @param $userId
     * @return int|null
     */
    public static function miniAppLogout($openId, $userId)
    {
        if (empty($openId) || empty($userId)) {
            return 0;
        }
        $db = MysqlDB::getDB();
        $where = [
            'user_id' => $userId,
            'open_id' => $openId,
            'status' => UserWeiXinModel::STATUS_NORMAL,
            'user_type' => UserWeiXinModel::USER_TYPE_AGENT,
            'busi_type' => UserWeiXinModel::BUSI_TYPE_AGENT_MINI,
            'app_id' => UserCenter::AUTH_APP_ID_OP_AGENT,
        ];
        $data = [
            'status' => UserWeiXinModel::STATUS_DISABLE,
            'update_time' => time(),
        ];
        return $db->updateGetCount(UserWeiXinModel::$table, $data, $where);
    }

    /**
     * 添加代理商申请
     * @param array $data
     * @return array|int|mixed|string|null
     * @throws RunTimeException
     */
    public static function addApplication($data = [])
    {
        $mobile = $data['mobile'] ?? '';
        $countryCode = $data['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
        if (empty($data)) {
            return [];
        }
        $insertData = [
            'name' => $data['name'],
            'mobile' => $mobile,
            'country_code' => $countryCode,
            'create_time' => time(),
            'update_time' => 0
        ];
        if (self::checkAgentExists($mobile, $countryCode)) {
            throw new RunTimeException(['agent_have_exist_login']);
        }
        if (self::checkAgentApplicationExists($mobile, $countryCode)) {
            throw new RunTimeException(['agent_application_exists']);
        }
        return AgentApplicationModel::insertRecord($insertData);
    }

    /**
     * 检查代理商是否已存在
     * @param $mobile
     * @param null $countryCode
     * @param int $excludeId
     * @return bool
     */
    public static function checkAgentExists($mobile, $countryCode = null, $excludeId = 0)
    {
        if (empty($mobile)) {
            return false;
        }
        if (empty($countryCode)) {
            $countryCode = NewSMS::DEFAULT_COUNTRY_CODE;
        }
        $where = [
            'mobile' => $mobile,
            'country_code' => $countryCode
        ];
        if (!empty($excludeId)) {
            $where['id[!]'] = $excludeId;
        }
        $agentInfo = AgentModel::getRecord($where);
        if (!empty($agentInfo)) {
            return true;
        }
        return false;
    }

    /**
     * 代理商冻结检查
     * @param $info
     * @return bool
     */
    public static function checkAgentFreeze($info)
    {
        if (empty($info)) {
            return true;
        }
        if (!empty($info['status'])
            && $info['status'] == AgentModel::STATUS_FREEZE
            && time() - $info['freeze_time'] >= Util::TIMESTAMP_ONEWEEK) {
            return true;
        }
        if (!empty($info['parent_id'])) {
            $agentInfo = AgentModel::getById($info['parent_id']);
            return self::checkAgentFreeze(['status' => $agentInfo['status'], 'freeze_time' => $agentInfo['freeze_time']]);
        }
        return false;
    }

    /**
     * 检查代理商申请是否已存在
     * @param $mobile
     * @param string $countryCode
     * @return bool
     */
    public static function checkAgentApplicationExists($mobile, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        if (empty($mobile)) {
            return false;
        }
        $agentInfo = AgentApplicationModel::getRecord(['mobile' => $mobile, 'country_code' => $countryCode]);
        if (!empty($agentInfo)) {
            return true;
        }
        return false;
    }

    /**
     * 获取代理绑定用户列表
     * @param $agentId
     * @param $type
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getBindUserList($agentId, $type, $page, $limit)
    {
        $agentNameArr = [];
        $returnData = [
            'bind_user_list' => [],
            'total' => 0,
        ];
        //获取直接绑定还是代理绑定
        if ($type == AgentModel::LEVEL_FIRST) {
            //获取直接绑定用户
            $agentIdArr = [$agentId];
        } else {
            //获取二级代理绑定用户
            $secondAgentList = AgentModel::getRecords(['parent_id' => $agentId], ['id', 'name']);
            $agentIdArr = [];
            array_map(function ($item) use (&$agentIdArr, &$agentNameArr) {
                $agentIdArr[] = $item['id'];
                $agentNameArr[$item['id']] = $item['name'];
            }, $secondAgentList);

            //没有下级代理，所以一定不会有代理所产生的的用户
            if (empty($agentIdArr)) {
                return $returnData;
            }
        }

        //获取绑定关系
        $sqlLimitArr = [
            ($page - 1) * $limit,
            $limit
        ];
        list($bindUserList,$bindUserTotal) = AgentUserModel::getListByAgentId($agentIdArr, $sqlLimitArr);

        //没有绑定用户直接返回空
        if (empty($bindUserList)) {
            return $returnData;
        }

        //获取总数
        $returnData['total'] = $bindUserTotal;


        $userIdArr = [];
        array_map(function ($item) use (&$userIdArr) {
            $userIdArr[] = $item['user_id'];
        }, $bindUserList);

        //获取用户手机号，并且手机号隐藏中间4位
        $mobileList = DssStudentModel::getRecords(['id' => $userIdArr], ['id', 'mobile']);
        $mobileList = is_array($mobileList) ? $mobileList : [];
        $encodeMobileArr = [];
        array_map(function ($item) use (&$encodeMobileArr) {
            $encodeMobileArr[$item['id']] = Util::hideUserMobile($item['mobile']);
        }, $mobileList);


        //获取头像和昵称
        $userNicknameArr = self::batchDssUserWxInfoByUserId($userIdArr);
        $dict = DictConstants::getSet(DictConstants::AGENT_BIND_STATUS);
        //组合数据
        foreach ($bindUserList as $key => $val) {
            $tmpUserInfo = $userNicknameArr[$val['user_id']] ?? [];
            $tmpBindStatus = self::getAgentUserBindStatus($val['deadline'], $val['stage']);

            $bindUserList[$key]['thumb'] = $tmpUserInfo['thumb'] ?? '';     //这里如果需要返回默认头像的话需要调整
            $bindUserList[$key]['nickname'] = $tmpUserInfo['nickname'] ?? '';
            $bindUserList[$key]['mobile'] = $encodeMobileArr[$val['user_id']] ?? '';
            $bindUserList[$key]['second_agent_name'] = $agentNameArr[$val['agent_id']] ?? '';
            $bindUserList[$key]['format_bind_time'] = date('Y-m-d H:i:s', $val['bind_time']);
            $bindUserList[$key]['bind_status'] = $tmpBindStatus;
            $bindUserList[$key]['bind_status_name'] = $dict[$tmpBindStatus];

        }

        $returnData['bind_user_list'] = $bindUserList;
        return $returnData;
    }

    /**
     * 批量获取用户头像和昵称，  如果缓存中不存在头像和昵称则批量请求微信接口获取最新消息
     * 第一优先级:取当前最新的用户微信信息
     * 第二优先级:取系统获取的用户最后一次微信信息
     * 第三优先级:取默认头像(当前系统里面的小叶子默认头像)
     * 缓存有效时间 24小时
     * @param array $userList   必须是相同的app_id, busi_type, user_type
     * @return array
     */
    public static function batchGetUserNicknameAndHead($appid, $busi_type, $userList)
    {
        $redis = RedisDB::getConn();
        $redisHashKey  = UserWeiXinInfoModel::REDIS_HASH_USER_WEIXIN_INFO_PREFIX.date("Y-m-d");

        //缓存中获取信息
        list($userNicknameAndHead,$wxRequestData) = self::getCacheUserWxInfo($appid,$busi_type,$userList);

        /** 向微信发起获取头像昵称的请求, 并记录获取成功的openid */
        $successOpenid = [];  //成功从微信获取头像和昵称的用户id
        if (!empty($wxRequestData)){
            $wechat = WeChatMiniPro::factory($appid,$busi_type);
            $wxUserList = $wechat->batchGetUserInfo(array_keys($wxRequestData));
            $wxUserList = $wxUserList['user_info_list'] ?? [];

            //组合微信接口拿到的用户头像和昵称
            foreach ($wxUserList as $wxVal) {
                $tmpOtherInfo = $wxRequestData[$wxVal['openid']] ?? []; //openid其他信息
                $tmpUserId = $tmpOtherInfo['user_id'] ?? 0;
                $userNicknameAndHead[$tmpUserId] = [
                    'nickname' => $wxVal['nickname'] ?? '',
                    'thumb' => $wxVal['headimgurl'] ?? '',
                ];

                //缓存信息 , 缓存app_id, busi_type, open_id, user_type
                $hashField = $appid . '_' . $busi_type . '_' . $wxVal['openid'];
                $redis->hset($redisHashKey, $hashField, json_encode($wxVal));
                $redis->expire($redisHashKey,86400*2);  //两天过期

                //记录成功从微信获取头像和昵称的用户id
                $successOpenid[] = $wxVal['openid'];
            }
        }


        /** 获取用户最后一次拉取的头像 */
        //获取缓存中不存在的open_id 对应的user_id
        $openidAndUserid = array_column($wxRequestData,'user_id','open_id');
        $getFailOpenidList = array_diff(array_keys($wxRequestData), $successOpenid); //两个数组的差集就是没有成功从微信拉取信息的用户id
        $getDbUserInfo = self::getUserWeiXinInfoNameAndHead($appid,$busi_type,$getFailOpenidList);
        foreach ($getDbUserInfo as $openid => $info) {
            $tmpUserId = $openidAndUserid[$openid];
            $userNicknameAndHead[$tmpUserId] = [
                'nickname' => $info['nickname'] ?? '',
                'thumb' => $info['head_url'] ?? '',
            ];
        }
        return $userNicknameAndHead;
    }

    /**
     * 获取缓存中用户微信头像和昵称
     * @param $appid
     * @param $busi_type
     * @param $userList
     * @return array[]
     */
    public static function getCacheUserWxInfo($appid, $busi_type, $userList){
        $redis = RedisDB::getConn();
        $noCacheData = [];
        $userNicknameAndHead = [];
        //缓存中获取信息
        $redisHashKey  = UserWeiXinInfoModel::REDIS_HASH_USER_WEIXIN_INFO_PREFIX.date("Y-m-d");
        $defaultNickname = DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_wx_nickname');
        $defaultThumb = AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::AGENT_CONFIG, 'default_thumb'));
        foreach ($userList as $uInfo) {
            $hashField = $appid . '_' . $busi_type . '_' . $uInfo['open_id'];
            //缓存不存在
            if (!$redis->hexists($redisHashKey, $hashField)) {
                //初始化用户头像默认值
                $userNicknameAndHead[$uInfo['user_id']] = [
                    'nickname' => $defaultNickname,
                    "thumb" => $defaultThumb,
                ];

                // open_id不为空放入临时数组，等待向微信发起请求
                if (!empty($uInfo['open_id'])) {
                    $noCacheData[$uInfo['open_id']] = [
                        'user_id' => $uInfo['user_id'],
                        'user_type' => $uInfo['user_type'],
                        'open_id' => $uInfo['open_id'],
                    ];
                }
                continue;
            }

            //缓存存在 - 从缓存获取用户的昵称和头像
            $hashVal = $redis->hget($redisHashKey, $hashField);
            $userWxInfo = json_decode($hashVal, true);
            $userNicknameAndHead[$uInfo['user_id']] = [
                'nickname' => $userWxInfo['nickname'],
                "thumb" => $userWxInfo['headimgurl'],
            ];
        }
        return [$userNicknameAndHead,$noCacheData];
    }

    /**
     * 根据openid获取用户数据表里的头像和昵称
     * @param $appid
     * @param $busi_type
     * @param $openIdList
     * @return array
     */
    public static function getUserWeiXinInfoNameAndHead ($appid,$busi_type,$openIdList) {
        $userNicknameAndHead = [];
        if (!empty($openIdList)) {
            //获取openid最后一次拉取微信信息数据
            $where = [
                'open_id' => $openIdList,
                'app_id' => $appid,
                'busi_type' => $busi_type,
            ];
            $userList = UserWeiXinInfoModel::getRecords($where);
            $userNickList = [];
            array_map(function ($item) use (&$userNickList){
                $userNickList[$item['open_id']] = $item;
            },$userList);

            //找到openid最后一次拉取的用户信息
            foreach ($userList as $openid => $info) {
                $tmpInfo = $userNickList[$info['open_id']] ?? [];
                if (empty($tmpInfo)) {
                    continue;
                }
                $userNicknameAndHead[$openid] = [
                    'nickname' => Util::textDecode($tmpInfo['nickname']),
                    'head_url' => AliOSS::replaceCdnDomainForDss($tmpInfo['head_url']),
                ];
            }
        }
        return $userNicknameAndHead;
    }

    /**
     * 获取用户的微信头像和昵称
     * @param array $userIdArr
     * @return array
     */
    public static function batchDssUserWxInfoByUserId(array $userIdArr){
        if (empty($userIdArr)) {
            return [];
        }
        //从dss读取用户信息
        $userList = DssUserWeiXinModel::getUserWeiXinListByUserid($userIdArr);
        return self::batchGetUserNicknameAndHead(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER, $userList);
    }

    /**
     * 获取代理商的 推广订单列表
     * @param $agentId
     * @param $level
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getAgentOrderList($agentId, $level, $page, $limit)
    {
        $agentNameArr = [];
        $returnData = [
            'order_list' => [],
            'total' => 0,
        ];
        //获取直接绑定还是代理绑定
        if ($level == AgentModel::LEVEL_FIRST) {
            //获取直接绑定用户
            $agentIdArr = [$agentId];
        } else {
            //获取二级代理绑定用户
            $secondAgentList = AgentModel::getRecords(['parent_id' => $agentId], ['id','name']);
            $agentIdArr = [];
            array_map(function ($item) use (&$agentIdArr, &$agentNameArr) {
                $agentIdArr[] = $item['id'];
                $agentNameArr[$item['id']] = $item['name'];
            }, $secondAgentList);

            //没有下级代理，所以一定不会有代理所产生的的用户
            if (empty($agentIdArr)) {
                return $returnData;
            }
        }
        $orderList = AgentAwardDetailModel::getAgentRecommendDuplicationBill(implode(',', $agentIdArr), false, $page, $limit);
        if (empty($orderList['count'])) {
            return $returnData;
        }
        $returnData['total'] = $orderList['count'];
        $userIdArr = [];
        $orderIdArr = [];
        $orderAgentIdArr = [];
        foreach ($orderList['list'] as $item) {
            if (!empty($item['student_id'])) {
                $userIdArr[$item['student_id']] = $item['student_id'];
            }
            if (!empty($item['parent_bill_id'])) {
                $orderIdArr[$item['parent_bill_id']] = $item['parent_bill_id'];
            }
            if (!empty($item['own_agent_id'])) {
                $orderAgentIdArr[$item['own_agent_id']] = $item['own_agent_id'];
            }
            if (!empty($item['signer_agent_id'])) {
                $orderAgentIdArr[$item['signer_agent_id']] = $item['signer_agent_id'];
            }
        }
        // 代理信息
        $allAgentInfo = [];
        if (!empty($orderAgentIdArr)) {
            $allAgentInfo = AgentModel::getRecords(['id' => $orderAgentIdArr], ['id', 'name']);
            if (!empty($allAgentInfo)) {
                $allAgentInfo = array_column($allAgentInfo, null, 'id');
            }
        }
        // 订单信息
        $giftCodeArr = [];
        if (!empty($orderIdArr)) {
            $giftCodeArr = DssGiftCodeModel::getGiftCodeDetailByBillId($orderIdArr);
            if (!empty($giftCodeArr)) {
                $giftCodeArr = array_column($giftCodeArr, null, 'parent_bill_id');
            }
        }
        $userNicknameArr = [];
        $mobileList = [];
        if (!empty($userIdArr)) {
            //获取用户昵称头像
            $userNicknameArr = self::batchDssUserWxInfoByUserId($userIdArr);
            //获取手机号
            $mobileList = DssStudentModel::getRecords(['id' => $userIdArr], ['id', 'mobile']);
        }

        $encodeMobileArr = [];
        array_map(function ($item) use (&$encodeMobileArr) {
            $encodeMobileArr[$item['id']] = Util::hideUserMobile($item['mobile']);
        }, $mobileList);

        //组合返回数据
        $dict = DictConstants::getSet(DictConstants::CODE_STATUS);
        foreach ($orderList['list'] as &$val) {
            $tmpUserInfo = $userNicknameArr[$val['student_id']] ?? [];

            $val['thumb'] = $tmpUserInfo['thumb'] ?? '';
            $val['nickname'] = $tmpUserInfo['nickname'] ?? '';
            $val['mobile'] = $encodeMobileArr[$val['student_id']] ?? '';
            if (in_array($val['signer_agent_id'], $agentIdArr)) {
                $val['second_agent_name'] = $allAgentInfo[$val['signer_agent_id']]['name'] ?? '';
            } else {
                $val['second_agent_name'] = $allAgentInfo[$val['own_agent_id']]['name'] ?? '';
            }
            $val['format_pay_time'] = date("Y-m-d H:i:s", $val['buy_time']);
            $val['bill_amount'] = Util::yuan($giftCodeArr[$val['parent_bill_id']]['bill_amount'], 2);
            $val['agent_id'] = $val['signer_agent_id'] ?? '';
            $val['bill_id'] = $val['parent_bill_id'] ?? '';
            $val['package_name'] = $giftCodeArr[$val['parent_bill_id']]['package_name'] ?? '';
            $val['code_status'] = $giftCodeArr[$val['parent_bill_id']]['code_status'] ?? '';
            $val['code_status_name'] = $dict[$giftCodeArr[$val['parent_bill_id']]['code_status']] ?? '';

        }

        $returnData['order_list'] = $orderList['list'];
        return $returnData;
    }

    /**
     * 根据时间判断代理和用户的绑定状态， 这里stage必须是年卡或体验
     * stage = 0 注册状态， deadline可能是0
     * @param $stage
     * @param $deadline
     * @return int
     */
    public static function getAgentUserBindStatus($deadline,$stage){
        //未绑定 - 注册状态不存在绑定和不绑定的关系
        if ($stage == AgentUserModel::STAGE_REGISTER) {
            return AgentUserModel::BIND_STATUS_PENDING;
        }
        switch ($deadline) {
            case 0:    //已购年卡 - 永久绑定中
                return AgentUserModel::BIND_STATUS_BIND;
            case $deadline >= time():   //已购体验
                return AgentUserModel::BIND_STATUS_BIND;
            default:    //解绑
                return AgentUserModel::BIND_STATUS_UNBIND;
        }
    }

    /**
     * 代理小程序首页
     * @param $agentId
     * @return mixed|null
     * @throws RunTimeException
     */
    public static function getMiniAppIndex($agentId)
    {
        if (empty($agentId)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        $agentInfo  = AgentModel::getById($agentId);
        if (empty($agentInfo)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        $ids = [$agentId];
        if (empty($agentInfo['parent_id'])) {
            $allSec = AgentModel::getRecords(['parent_id' => $agentId], ['id']);
            $ids = array_merge($ids, array_column($allSec, 'id'));
        }

        $agentInfo['users'] = AgentUserModel::getCount(
            [
                'agent_id' => $ids,
                'stage[!]' => AgentUserModel::STAGE_REGISTER,
            ]
        );
        $order = AgentAwardDetailModel::getAgentRecommendDuplicationBill(implode(',', $ids));
        $agentInfo['orders'] = $order['count'] ?? 0;
        $agentInfo['sec_agents'] = AgentModel::getCount(['parent_id' => $agentId]);
        $agentInfo['config']     = self::getPackageList($agentId);
        $agentInfo['parent']     = AgentModel::getRecord(['id' => $agentInfo['parent_id']]);
        $agentInfo['show_status'] = self::getAgentStatus($agentInfo);
        $agentInfo = self::formatFrontAgentData($agentInfo);
        return $agentInfo;
    }


    /**
     * 代理分享页数据
     * @param $packageId
     * @param $agentId
     * @return array
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getShareInfo($packageId, $agentId)
    {
        $package = ErpPackageV1Model::getById($packageId);
        if (empty($package)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($package['status'] != ErpPackageV1Model::STATUS_ON_SALE) {
            throw new RunTimeException(['package_not_available_for_sale']);
        }
        $logo = DictConstants::get(DictConstants::AGENT_CONFIG, 'share_card_logo');
        $agent = AgentModel::getById($agentId);
        $appId = Constants::SMART_APP_ID;

        $channel = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_distribution');
        if (!empty($agent['type'])) {
            $channel = self::getAgentChannel($agent['type']);
        }
        $paramInfo = [
            'app_id'  => $appId,
            'type'    => ParamMapModel::TYPE_AGENT,
            'user_id' => $agentId,
            'r'       => MiniAppQrService::AGENT_TICKET_PREFIX. RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], ParamMapModel::TYPE_AGENT . "_" . $agentId),
            'c'       => $channel
        ];
        $paramId = ReferralActivityService::getParamsId($paramInfo);
        $pageParams = [
            'package_id' => $package['id'],
            'param_id' => $paramId,
        ];

        $packageExt = GoodsResourceModel::getRecord(['package_id' => $package['id']], ['ext']);
        $resource = GoodsResourceModel::formatExt($packageExt['ext'], $agent, ['lt' => DssUserQrTicketModel::LANDING_TYPE_NORMAL, 'package_id' => $package['id']]);

        return [
            'buy_page'   => PosterService::getAgentLandingPageUrl($pageParams),
            'name'       => $package['name'] ?? '',
            'desc'       => $package['desc'] ?? '',
            'logo'       => AliOSS::replaceCdnDomainForDss($logo),
            'poster_url' => $resource['poster_agent_url'] ?? '',
            'text' => $resource['text'] ?? '',
        ];
    }

    /**
     * 获取代理商状态
     * @param $info
     * @return string
     */
    public static function getAgentStatus($info)
    {
        $status = $info['status'];
        if (!empty($info['parent_id']) && $info['status'] == AgentModel::STATUS_OK) {
            $agentInfo = AgentModel::getById($info['parent_id']);
            $status = $agentInfo['status'];
        }
        return $status;
    }

    /**
     * 添加二级代理
     * @param $agentId
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function secAgentAdd($agentId, $params = [])
    {
        if (self::checkAgentExists($params['mobile'], $params['country_code'])) {
            throw new RunTimeException(['agent_have_exist_front']);
        }
        $data = [
            'name'         => $params['name'],
            'mobile'       => $params['mobile'],
            'country_id'   => $params['country_id'] ?? 0,
            'parent_id'    => $agentId,
            'country_code' => $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE,
            'app_id'       => UserCenter::AUTH_APP_ID_OP_AGENT,
        ];
        return self::addAgent($data, 0);
    }

    /**
     * 更新二级代理
     * @param $agentId
     * @param array $params
     * @return int|null
     * @throws RunTimeException
     */
    public static function secAgentUpdate($agentId, $params = [])
    {
        $record = AgentModel::getById($agentId);
        if (empty($record)) {
            throw new RunTimeException(['record_not_found']);
        }
        if (self::checkAgentExists(
            $params['mobile'],
            $params['country_code'],
            $agentId
        )) {
            throw new RunTimeException(['agent_have_exist_front']);
        }
        $data = [
            'name'         => $params['name'],
            'mobile'       => $params['mobile'],
            'country_code' => $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE,
        ];
        return AgentModel::updateRecord($record['id'], $data);
    }

    /**
     * 二级代理详情
     * @param $agentId
     * @return array|mixed|null
     */
    public static function secAgentDetail($agentId)
    {
        if (empty($agentId)) {
            return [];
        }
        return self::formatFrontAgentData(AgentModel::getById($agentId));
    }

    /**
     * 前端用：格式化数据
     * @param $info
     * @return array
     */
    public static function formatFrontAgentData($info)
    {
        if (empty($info)) {
            return [];
        }
        $info['mobile'] = Util::hideUserMobile($info['mobile'] ?? '');
        $info['name'] = Util::textDecode($info['name'] ?? '');
        return $info;
    }

    /**
     * 我的上级代理
     * @param $agentId
     * @return mixed
     * @throws RunTimeException
     */
    public static function secAgentParent($agentId)
    {
        if (empty($agentId)) {
            throw new RunTimeException(['record_not_found']);
        }
        $record = AgentModel::getById($agentId);
        if (empty($record) || empty($record['parent_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $parent = AgentModel::getById($record['parent_id']);
        if (empty($parent)) {
            throw new RunTimeException(['record_not_found']);
        }
        return self::formatFrontAgentData($parent);
    }

    /**
     * 二级代理列表
     * @param $agentId
     * @param array $params
     * @return array
     */
    public static function secAgentList($agentId, $params = [])
    {
        $data = ['records' => [], 'total' => 0];
        if (empty($agentId)) {
            return $data;
        }
        list($page, $count) = Util::formatPageCount($params);
        $where = [
            'parent_id' => $agentId,
        ];
        $data['total'] = AgentModel::getCount($where);
        if (empty($data['total'])) {
            return $data;
        }
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        $records = AgentModel::getRecords($where);
        $data['records'] = self::formatMiniAppAgent($records);
        return $data;
    }

    /**
     * 格式化小程序代理列表
     * @param array $records
     * @return array|mixed
     */
    public static function formatMiniAppAgent($records = [])
    {
        if (empty($records)) {
            return [];
        }
        foreach ($records as &$record) {
            $record['create_time_show'] = Util::formatTimestamp($record['create_time']);
            $record['status_show']      = AgentModel::STATUS_DICT[$record['status']] ?? $record['status'];
            $record['name']             = Util::textDecode($record['name']);
        }
        return $records;
    }

    /**
     * 推广学员列表数据
     * @param $params
     * @param $employeeId
     * @return array|mixed
     */
    public static function recommendUsersList($params, $employeeId)
    {
        $recommendUserList = ['count' => 0, 'list' => []];
        $dssStudentWhere = [];
        $time = time();
        //dss数据表学生条件
        if (!empty($params['student_id'])) {
            $dssStudentWhere['id'] = $params['student_id'];
        }
        if (!empty($params['student_name'])) {
            $dssStudentWhere['name'] = $params['student_name'];
        }
        if (!empty($params['student_uuid'])) {
            $dssStudentWhere['uuid'] = $params['student_uuid'];
        }
        if (!empty($params['student_mobile'])) {
            $dssStudentWhere['mobile'] = $params['student_mobile'];
        }
        if (!empty($dssStudentWhere)) {
            $dssStudentList = StudentService::searchStudentList($dssStudentWhere);
            if (empty($dssStudentList)) {
                return $recommendUserList;
            }
            $whereStudentIds = array_column($dssStudentList, 'id');
        }

        //代理学生数据
        $agentUserWhere = 'au.stage>0 ';
        if (!empty($whereStudentIds)) {
            $agentUserWhere .= ' AND  au.user_id in( ' . implode(',', $whereStudentIds) . ')';
        }
        if (!empty($params['stage'])) {
            $agentUserWhere .= ' AND au.stage= ' . $params['stage'];
        }
        if (!empty($params['bind_start_time'])) {
            $agentUserWhere .= ' AND au.create_time>= ' . $params['bind_start_time'];
        }
        if (!empty($params['bind_end_time'])) {
            $agentUserWhere .= ' AND au.create_time<= ' . $params['bind_end_time'];
        }
        if (!empty($params['bind_status']) && $params['bind_status'] == AgentUserModel::BIND_STATUS_BIND) {
            $agentUserWhere .= ' AND au.deadline>= ' . $time;
        }
        if (!empty($params['bind_status']) && $params['bind_status'] == AgentUserModel::BIND_STATUS_UNBIND) {
            $agentUserWhere .= ' AND au.deadline< ' . $time;
        }
        //数据权限
        $agentWhere = '';
        if ($params['only_read_self']) {
            $employeeAgentIdList = self::getAgentIdByEmployeeId($employeeId);
            if (empty($employeeAgentIdList)) {
                return $recommendUserList;
            }
            $agentWhere = ' AND (a.id in(' . implode(',', $employeeAgentIdList) . '))';
        }
        //代理数据
        $searchAgentIdList = self::getSearchAgentIdList($params);
        if (is_array($searchAgentIdList) && !empty($searchAgentIdList)) {
            $agentUserWhere .= " AND au.agent_id in(" . implode(',', $searchAgentIdList) . ")";
        } elseif (is_array($searchAgentIdList) && empty($searchAgentIdList)) {
            return $recommendUserList;
        }
        if (!empty($params['agent_type'])) {
            $agentTypeData = AgentModel::getAgentParentData($searchAgentIdList);
            if (!in_array($params['agent_type'],array_column($agentTypeData,'agent_type'))) {
                return $recommendUserList;
            }
        }
        list($recommendUserList['count'], $recommendUserList['list']) = AgentUserModel:: agentRecommendUserList($agentUserWhere, $agentWhere, $params['page'], $params['count']);
        if (empty($recommendUserList['count'])) {
            return $recommendUserList;
        }
        //获取等级数据
        $firstAgentIdArr = array_column($recommendUserList['list'], 'first_agent_id');
        $secondAgentIdArr = array_column($recommendUserList['list'], 'second_agent_id');
        $agentData = AgentModel::getAgentParentData(array_unique(array_merge($firstAgentIdArr,$secondAgentIdArr)));
        if (!empty($agentData)) {
            $agentData = array_column($agentData, null, 'id');
        }
        foreach ($recommendUserList['list'] as $rsk => &$rsv) {
            $rsv['first_agent_name'] = !empty($agentData[$rsv['first_agent_id']]['parent_name']) ? $agentData[$rsv['first_agent_id']]['parent_name'] : $agentData[$rsv['first_agent_id']]['name'];
            $rsv['second_agent_name'] = !empty($agentData[$rsv['second_agent_id']]['parent_name']) ? $agentData[$rsv['second_agent_id']]['name'] : "";
            $rsv['second_agent_id_true'] = empty($rsv['second_agent_id']) ? '' : $rsv['second_agent_id'];
            $rsv['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
            $rsv['agent_type'] = $agentData[$rsv['first_agent_id']]['agent_type'];
        }
        return self::formatRecommendUsersData($recommendUserList);
    }

    /**
     * 格式化推广学员列表数据
     * @param $recommendUserData
     * @return mixed
     */
    private static function formatRecommendUsersData($recommendUserData)
    {
        //学生详细数据
        $studentListDetail = array_column(StudentService::searchStudentList(['id' => array_column($recommendUserData['list'], 'user_id')]), null, 'id');
        $dict = DictConstants::getTypesMap([DictConstants::AGENT_TYPE['type'], DictConstants::AGENT_USER_STAGE['type'], DictConstants::PACKAGE_APP_NAME['type'], DictConstants::AGENT_BIND_STATUS['type']]);

        array_walk($recommendUserData['list'], function (&$rv) use ($studentListDetail, $dict) {
            $rv['student_name'] = $studentListDetail[$rv['user_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['user_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['user_id']]['mobile']);
            //绑定关系状态
            $rv['bind_status'] = self::getAgentUserBindStatus($rv['deadline'], $rv['stage']);
            $rv['type_name'] = $dict[DictConstants::AGENT_TYPE['type']][$rv['agent_type']]['value'];
            $rv['stage_name'] = $dict[DictConstants::AGENT_USER_STAGE['type']][$rv['stage']]['value'];
            $rv['app_id_name'] = $dict[DictConstants::PACKAGE_APP_NAME['type']][$rv['app_id']]['value'];
            $rv['bind_status_name'] = $dict[DictConstants::AGENT_BIND_STATUS['type']][$rv['bind_status']]['value'];
            $rv['second_agent_id_true'] = empty($rv['second_agent_id']) ? '' : $rv['second_agent_id'];

        });
        return $recommendUserData;
    }

    /**
     * 格式化推荐订单列表查询条件
     * @param $params
     * @param $employeeId
     * @return array
     */
    private static function formatRecommendBillsListWhere($params, $employeeId)
    {
        $dssStudentWhere = [];
        //学员名称——姓名/ID/手机号
        //学员UUID
        //dss数据表学生条件
        if (!empty($params['student_id'])) {
            $dssStudentWhere['id'] = $params['student_id'];
        }
        if (!empty($params['student_name'])) {
            $dssStudentWhere['name'] = $params['student_name'];
        }
        if (!empty($params['student_uuid'])) {
            $dssStudentWhere['uuid'] = $params['student_uuid'];
        }
        if (!empty($params['student_mobile'])) {
            $dssStudentWhere['mobile'] = $params['student_mobile'];
        }
        if (!empty($dssStudentWhere)) {
            $dssStudentList = StudentService::searchStudentList($dssStudentWhere);
            if (empty($dssStudentList)) {
                return [];
            }
            $whereStudentIds = array_column($dssStudentList, 'id');
        }

        //订单状态 支付时间
        $giftCodeWhere = ' ';
        if (!empty($params['code_status'])) {
            if ($params['code_status'] != DssGiftCodeModel::CODE_STATUS_INVALID) {
                $giftCodeWhere .= ' AND gc.code_status !=' . DssGiftCodeModel::CODE_STATUS_INVALID;
            } else {
                $giftCodeWhere .= ' AND gc.code_status=' . DssGiftCodeModel::CODE_STATUS_INVALID;
            }
        }
        if (!empty($params['pay_start_time'])) {
            $giftCodeWhere .= ' AND gc.create_time>=' . $params['pay_start_time'];
        }
        if (!empty($params['pay_end_time'])) {
            $giftCodeWhere .= ' AND gc.create_time<=' . $params['pay_end_time'];
        }
        $agentBillWhere = ' ';
        //目标代理商ID列表
        $searchAgentIdList = self::getSearchAgentIdList($params);
        if (is_array($searchAgentIdList) && !empty($searchAgentIdList)) {
            $agentBillWhere .= " AND ab.agent_id in(" . implode(',', $searchAgentIdList) . ") AND ab.is_bind !=".AgentAwardDetailModel::IS_BIND_STATUS_NOT_HAVE_AGENT;
        } elseif (is_array($searchAgentIdList) && empty($searchAgentIdList)) {
            return [];
        }
        if (!empty($whereStudentIds)) {
            $agentBillWhere .= ' AND  ab.student_id in( ' . implode(',', $whereStudentIds) . ')';
        }
        $agentBillWhere .= ' AND ab.action_type != ' . AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER;
        //订单ID 购买产品包
        if (!empty($params['parent_bill_id'])) {
            $agentBillWhere .= ' AND ab.ext_parent_bill_id =\'' . $params['parent_bill_id'] . '\'';
        }
        if (!empty($params['package_id'])) {
            $agentBillWhere .= " AND ab.ext->>'$.package_id'=" . $params['package_id'];
        }
        if (isset($params['is_bind']) && is_numeric($params['is_bind'])) {
            $agentBillWhere .= " AND ab.is_bind=" . $params['is_bind'];
        }
        if (!empty($params['division_model'])) {
            $agentBillWhere .= " AND ab.ext->>'$.division_model'=" . $params['division_model'];
        }
        if (!empty($params['agent_type'])) {
            $agentBillWhere .= " AND ab.ext->>'$.agent_type'=" . $params['agent_type'];
        }
        //区分是否存在真实绑定关系
        if (!empty($firstAgentId) || !empty($secondAgentId)) {
            $agentBillWhere .= " AND ab.is_bind=" . AgentAwardDetailModel::IS_BIND_STATUS_YES;
        } else {
            $agentBillWhere .= " AND ab.is_bind!=" . AgentAwardDetailModel::IS_BIND_STATUS_NO;
        }
        //是否有推荐人:1有 2没有
        $agentAwardBillExtTable = 'bex';
        $agentAwardBillExtWhere = ' 1=1 ';
        if (!empty($params['is_referral_relation']) && ($params['is_referral_relation'] == AgentAwardBillExtModel::IS_HAVE_STUDENT_REFERRAL_YES)) {
            $agentAwardBillExtWhere .= " AND " . $agentAwardBillExtTable . ".student_referral_id>0";
        } elseif (!empty($params['is_referral_relation']) && ($params['is_referral_relation'] == AgentAwardBillExtModel::IS_HAVE_STUDENT_REFERRAL_NO)) {
            $agentAwardBillExtWhere .= " AND " . $agentAwardBillExtTable . ".student_referral_id=0";
        }

        if (!empty($params['is_hit_order'])) {
            $agentAwardBillExtWhere .= " AND bex.is_hit_order=" . $params['is_hit_order'];
        }

        //数据权限
        $agentWhere = '';
        $employeeAgentIdList = [];
        if ($params['only_read_self']) {
            $employeeAgentIdList = self::getAgentIdByEmployeeId($employeeId);
            if (empty($employeeAgentIdList)) {
                return [];
            }
        }
        //成单的代理——姓名/ID搜索，模糊搜索即可
        $signerAgentIdList = [];
        if (!empty($params['signer_agent_id'])) {
            $signerAgentIdList[] = $params['signer_agent_id'];
        }
        if (!empty($params['signer_agent_name'])) {
            $signerAgents = AgentModel::getRecords(['name[~]' => $params['signer_agent_name']], ['id']);
            if (empty($signerAgents)) {
                return [];
            }
            $signerAgentIdList = array_merge($signerAgentIdList, array_column($signerAgents, 'id'));
        }
        if (!empty($signerAgentIdList)) {
            $agentAwardBillExtWhere .= " AND " . $agentAwardBillExtTable . ".signer_agent_id in (" . implode(',', $signerAgentIdList) . ")";
        } elseif ($params['only_read_self']) {
            $agentAwardBillExtWhere .= " AND (" . $agentAwardBillExtTable . ".signer_agent_id in (" . implode(',', $employeeAgentIdList) . ") OR ". $agentAwardBillExtTable . ".own_agent_id in (" . implode(',', $employeeAgentIdList)."))";
        }
        $agentAwardBillExtWhere .= ' AND ((bex.own_agent_status=' . AgentModel::STATUS_OK . ' OR bex.signer_agent_status=' . AgentModel::STATUS_OK . '))';
        //是否首单
        if (!empty($params['is_first_order'])) {
            $signerAgentIdList[] = $params['is_first_order'];
            $agentAwardBillExtWhere .= " AND " . $agentAwardBillExtTable . ".is_first_order =" . $params['is_first_order'];
        }
        return [
            'gift_code_where' => $giftCodeWhere,
            'agent_bill_where' => $agentBillWhere,
            'award_bill_ext_where' => $agentAwardBillExtWhere,
            'agent_where' => $agentWhere,
        ];
    }

    /**
     * 推广订单列表数据:订单归属人角度，成单人角度
     * @param $params
     * @param $employeeId
     * @return array|mixed
     */

    public static function recommendBillsList($params, $employeeId)
    {
        $recommendBillsList = ['count' => 0, 'list' => []];
        //格式化查询条件
        $whereData = self::formatRecommendBillsListWhere($params, $employeeId);
        if (empty($whereData)) {
            return $recommendBillsList;
        }
        //获取推荐订单列表
        list($recommendBillsList['count'], $recommendBillsList['list']) = AgentAwardDetailModel:: agentBillsList(
            $whereData['agent_bill_where'], $whereData['gift_code_where'],
            $whereData['agent_where'], $whereData['award_bill_ext_where'],
            $params['page'], $params['count']);
        if (empty($recommendBillsList['count'])) {
            return $recommendBillsList;
        }
        //查询订单的详细数据
        $giftCodeDetail = array_column(DssGiftCodeModel::getGiftCodeDetailByBillId(array_column($recommendBillsList['list'], 'parent_bill_id')), null, 'parent_bill_id');
        //查询成单代理商的数据&查询归属代理商数据
        $recommendBillsSignerAgentIdArr = array_column($recommendBillsList['list'], 'signer_agent_id');
        $recommendBillsFirstAgentIdArr = array_column($recommendBillsList['list'], 'first_agent_id');
        $recommendBillsSecondAgentIdArr = array_column($recommendBillsList['list'], 'second_agent_id');
        $recommendBillsAgentData = AgentModel::getAgentParentData(array_unique(array_merge($recommendBillsSignerAgentIdArr, $recommendBillsSecondAgentIdArr, $recommendBillsFirstAgentIdArr)));
        if (!empty($recommendBillsAgentData)) {
            $recommendBillsAgentData = array_column($recommendBillsAgentData, null, 'id');
        }
        //组合课包数据以及成单人数据
        foreach ($recommendBillsList['list'] as $rsk => &$rsv) {
            $rsv['signer_first_agent_name'] = $rsv['signer_second_agent_name'] = $rsv['signer_first_agent_id'] = $rsv['signer_second_agent_id'] = $rsv['signer_agent_type'] = "";
            if (!empty($rsv['signer_agent_id'])) {
                $rsv['signer_first_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['name'];
                $rsv['signer_second_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['name'] : "";
                $rsv['signer_first_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['p_id'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['id'];
                $rsv['signer_second_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['id'] : "";
                $rsv['signer_agent_type'] = $recommendBillsAgentData[$rsv['signer_agent_id']]['agent_type'];
            }
            $rsv['bill_package_id'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_package_id'];
            $rsv['bill_amount'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_amount'];
            $rsv['code_status'] = $giftCodeDetail[$rsv['parent_bill_id']]['code_status'];
            $rsv['buy_time'] = $giftCodeDetail[$rsv['parent_bill_id']]['buy_time'];
            $rsv['package_name'] = $giftCodeDetail[$rsv['parent_bill_id']]['package_name'];
            //归属人
            $rsv['first_agent_name'] = !empty($recommendBillsAgentData[$rsv['first_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['first_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['first_agent_id']]['name'];
            $rsv['second_agent_name'] = !empty($recommendBillsAgentData[$rsv['second_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['second_agent_id']]['name'] : "";
            $rsv['second_agent_id_true'] = empty($rsv['second_agent_id']) ? '' : $rsv['second_agent_id'];
            $rsv['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        }
        return self::formatRecommendBillsData($recommendBillsList);
    }


    /**
     * 格式化推广学员列表数据
     * @param $recommendBillsData
     * @return mixed
     */
    public static function formatRecommendBillsData($recommendBillsData)
    {
        //学生详细数据
        $studentListDetail = array_column(StudentService::searchStudentList(['id' => array_column($recommendBillsData['list'], 'student_id')]), null, 'id');
        $dict = DictConstants::getTypesMap([DictConstants::AGENT_TYPE['type'], DictConstants::CODE_STATUS['type'], DictConstants::PACKAGE_APP_NAME['type'], DictConstants::YSE_OR_NO_STATUS['type'], DictConstants::AGENT_DIVISION_MODEL['type']]);
        array_walk($recommendBillsData['list'], function (&$rv) use ($studentListDetail, $dict) {
            $rv['student_name'] = $studentListDetail[$rv['student_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['student_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['student_id']]['mobile']);
            $rv['bill_amount'] = $rv['bill_amount'] / 100;
            $rv['signer_agent_type_name'] = empty($rv['signer_agent_type']) ? '' : $dict[DictConstants::AGENT_TYPE['type']][$rv['signer_agent_type']]['value'];
            $rv['code_status_name'] = ($rv['code_status'] == DssGiftCodeModel::CODE_STATUS_INVALID) ? "已退款" : "已支付";
            $rv['app_id_name'] = $dict[DictConstants::PACKAGE_APP_NAME['type']][$rv['app_id']]['value'];
            //这两项数据暂时隐藏不展示
            //$rv['is_bind_name'] = $dict[DictConstants::YSE_OR_NO_STATUS['type']][$rv['is_bind']]['value'];
            //$rv['division_model_name'] = $dict[DictConstants::AGENT_DIVISION_MODEL['type']][$rv['division_model']]['value'];
            //区分是否存在真正的绑定关系
            if ($rv['is_bind'] == AgentUserModel::BIND_STATUS_UNBIND) {
                $rv['first_agent_id'] = $rv['first_agent_name'] = $rv['second_agent_id'] = $rv['second_agent_name'] = $rv['second_agent_id_true'] = $rv['type_name'] = '';
            } else {
                $rv['type_name'] = $dict[DictConstants::AGENT_TYPE['type']][$rv['agent_type']]['value'];
            }
            $rv['student_referral_id'] = empty($rv['student_referral_id']) ? '' : $rv['student_referral_id'];
            $rv['is_first_order_name'] = ($rv['is_first_order']==AgentAwardBillExtModel::IS_FIRST_ORDER_YES) ? "是" : "否";

        });
        return $recommendBillsData;
    }

    /**
     * @param $params
     * @return array
     * 代理申请列表
     */
    public static function applyList($params)
    {
        list($page, $count) = Util::formatPageCount($params);
        $where = [];

        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }

        if (!empty($params['mobile'])) {
            $where['mobile'] = $params['mobile'];
        }

        if (!empty($params['start_time'])) {
            $where['create_time[>=]'] = $params['start_time'];
        }

        if (!empty($params['end_time'])) {
            $where['create_time[<=]'] = $params['end_time'];
        }

        $totalCount = AgentApplicationModel::getCount($where);
        if (empty($totalCount)) {
            return [
                'list'       => [],
                'totalCount' => 0
            ];
        }

        $where['ORDER'] = ['create_time' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $list = AgentApplicationModel::getRecords($where, ['id', 'name', 'mobile', 'create_time' => Medoo::raw('FROM_UNIXTIME(<create_time>)'), 'remark']);
        foreach ($list as &$item) {
            $item['name'] = Util::textDecode($item['name']);
        }
        return [
            'list'       => $list,
            'totalCount' => $totalCount
        ];
    }

    /**
     * @param $params
     * @return int|null
     * 添加备注
     */
    public static function applyRemark($params)
    {
        return AgentApplicationModel::updateRecord($params['id'],['remark'=>$params['remark']]);
    }

    /**
     * @param $type
     * @param $keyCode
     * @return mixed
     * 获取packageID公共方法
     */
    public static function getPackageId($type,$keyCode)
    {
        $where = [
            'type' => $type,
            'key_code' => $keyCode,
        ];
        $result = DssDictModel::getRecord($where,['key_value']);
        return $result['key_value'];
    }

    /**
     * @param $params
     * @return bool
     * 推广素材新增、编辑接口
     */
    public static function popularMaterial($params)
    {
        $time = time();
        $packageId = $params['package_id'];
        $exist = GoodsResourceModel::getRecord(['package_id' => $packageId], ['id', 'package_id', 'ext']);
        $ext = [
            [
                "key"   => "poster",
                "type"  => GoodsResourceModel::CONTENT_TYPE_POSTER,
                "value" => $params['poster']
            ],
            [
                "key"   => "text",
                "type"  => GoodsResourceModel::CONTENT_TYPE_TEXT,
                "value" => Util::textEncode($params['text']) ?? ''
            ]
        ];
        $jsonExt = json_encode($ext);
        if ($exist) {
            $updateData = [
                'ext'         => $jsonExt,
                'update_time' => $time,
            ];
            GoodsResourceModel::updateRecord($exist['id'], $updateData);
        } else {
            $insertData = [
                'package_id'  => $packageId,
                'ext'         => $jsonExt,
                'create_time' => $time,
                'update_time' => $time,
            ];
            GoodsResourceModel::insertRecord($insertData);
        }
        return true;
    }

    /**
     * 获取推广素材方法
     * @param int $agentId
     * @param int $packageId
     * @return array|mixed
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function popularMaterialInfo($agentId = 0, $packageId=0)
    {
        if (empty($packageId)) {
            $packageId = self::getPackageId('WEB_STUDENT_CONFIG', 'mini_package_id');
        }
        $result = GoodsResourceModel::getRecord(['package_id' => $packageId], ['id', 'package_id', 'ext']);
        if (empty($result)) {
            return $result;
        }
        $data = [];
        $agentInfo = [];
        if (!empty($agentId)) {
            $agentInfo = AgentModel::getById($agentId);
            if (!empty($agentInfo['parent_id'])) {
                $agentInfo = AgentModel::getById($agentInfo['parent_id']);
            }
        }
        $ext = json_decode($result['ext'], true);

        $posterConfig = PosterService::getPosterConfig();
        foreach ($ext as $item) {
            if ($item['type'] == GoodsResourceModel::CONTENT_TYPE_IMAGE) {
                $data[$item['key']] = $item['value'];
                $data[$item['key'] . '_url'] = AliOSS::signUrls($item['value']);
            } elseif ($item['type'] == GoodsResourceModel::CONTENT_TYPE_TEXT) {
                $data[$item['key']] = Util::textDecode($item['value']);
            } elseif ($item['type'] == GoodsResourceModel::CONTENT_TYPE_POSTER) {
                $data[$item['key']] = $item['value'];
                $data[$item['key'] . '_url'] = AliOSS::signUrls($item['value']);
                if (!empty($agentId)) {
                    $channel = self::getAgentChannel($agentInfo['type'] ?? 0);
                    $extParams = [
                        'p' => PosterModel::getIdByPath($item['value']),
                        'app_id' => UserCenter::AUTH_APP_ID_OP_AGENT,
                    ];
                    $posterUrl = PosterService::generateQRPosterAliOss(
                        $item['value'],
                        $posterConfig,
                        $agentId,
                        UserWeiXinModel::USER_TYPE_AGENT,
                        $channel,
                        $extParams
                    );
                    $data[$item['key'] . '_agent_url'] = $posterUrl['poster_save_full_path'] ?? '';
                }
            }
        }
        return $data;
    }

    /**
     * 检测当前代理商是否有效
     * @param $agentIds
     * @return array
     */
    public static function checkAgentStatusIsValid($agentIds)
    {
        $agentValidStatus = array_fill_keys($agentIds, AgentModel::STATUS_FREEZE);
        $agentData = AgentModel::getAgentParentData($agentIds);
        if (empty($agentData)) {
            return $agentValidStatus;
        }
        foreach ($agentData as $k => $v) {
            if (($v['status'] != AgentModel::STATUS_FREEZE) && ($v['p_status'] != AgentModel::STATUS_FREEZE)) {
                $agentValidStatus[$v['id']] = AgentModel::STATUS_OK;
            }
        }
        return $agentValidStatus;
    }

    /**
     * 代理奖励发放逻辑
     * @param $studentInfo
     * @param $parentBillId
     * @param $packageInfo
     * @return bool
     */
    public static function agentAwardLogic($studentInfo, $parentBillId, $packageInfo)
    {
        //真人赠送智能课包直接返回
        if ($packageInfo['app_id'] != Constants::SMART_APP_ID) {
            return false;
        }
        //检测当前订单是否已经有奖励发放记录
        $billIsValid = AgentAwardService::checkBillIsValid($parentBillId);
        if (empty($billIsValid)){
            return false;
        }
        //获取此次奖励订单归属以及代理商关系绑定数据
        $bindAndAwardCheckObj = new AgentDispatchService($parentBillId, $studentInfo['id'], $packageInfo);
        $awardData = $bindAndAwardCheckObj::getBindAndBillOwnAgentData();
        if (empty($awardData)) {
            return false;
        }
        AgentAwardService::agentReferralBillAward($awardData['bind_agent_id'], $studentInfo, $packageInfo['package_type'], $packageInfo, $parentBillId, $awardData['own_agent_id']);
        return true;
    }

    /**
     * 代理商状态操作日志事件触发器
     * @param $agentId
     * @param $operatorId
     * @param $status
     * @param $opType
     */
    private static function agentFreezeStatusOpLogEvent($agentId, $operatorId, $status, $opType)
    {
        //日志记录
        $agentOpEventObj = new AgentOpEvent(
            [
                'agent_id' => $agentId,
                'status' => $status,
                'operator_id' => $operatorId,
                'op_type' => $opType
            ]);
        $agentOpEventObj::fire($agentOpEventObj);
    }


    /**
     * 一级代理模糊搜索
     * @param $params
     * @return array
     */
    public static function agentFuzzySearch($params)
    {
        $where = [AgentModel::$table . '.parent_id' => 0];
        if (!empty($params['name'])) {
            $where[AgentModel::$table . '.name[~]'] = $params['name'];
        }
        $agentType = 0;
        if (!empty($params['channel_id'])) {
            $agentType = self::getAgentTypeByChannel($params['channel_id']);
        }
        if (!empty($params['type']) || !empty($agentType)) {
            $where[AgentModel::$table . '.type'] = [$params['type'], $agentType];
        }
        $agentList = AgentModel::getRecords($where, ['name', 'id']);
        return $agentList;
    }

    /**
     * 查询代理商代理模式通过渠道ID
     * @param $channelId
     * @return mixed
     */
    public static function getAgentTypeByChannel($channelId)
    {
        $channelTypeMap = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_dict');
        $config = json_decode($channelTypeMap, true);
        if (!empty($config)) {
            $config = array_flip($config);
        }
        return empty($config[$channelId]) ? 0 : (int)$config[$channelId];
    }

    /**
     * 根据分成模式不同获取推广课包列表
     * @param $divisionModel
     * @return array
     */
    public static function getAgentDivisionModelToPackage($divisionModel)
    {
        //根据分成模式，选择可推广的课包列表
        switch ($divisionModel) {
            case AgentModel::DIVISION_MODEL_LEADS:
                $packageIds = DssErpPackageV1Model::getTrailPackageIds();
                break;
            case AgentModel::DIVISION_MODEL_LEADS_AND_SALE:
                $packageIds = array_column(DssErpPackageV1Model::getPackageIds([DssCategoryV1Model::DURATION_TYPE_TRAIL, DssCategoryV1Model::DURATION_TYPE_NORMAL]), 'package_id');
                break;
            default:
                return [];
        }
        return ErpPackageV1Model::getPackageInfoByIdChannel($packageIds, ErpPackageV1Model::CHANNEL_OP_AGENT, ErpPackageV1Model::STATUS_ON_SALE);
    }

    /**
     * 获取代理推广产品列表
     * @param $agentId
     * @return array
     */
    public static function getPackageList($agentId)
    {
        if (empty($agentId)) {
            return [];
        }
        $agentInfo = AgentModel::getById($agentId);

        $list = AgentSalePackageModel::getPackageList(!empty($agentInfo['parent_id']) ? $agentInfo['parent_id'] : $agentId);
        if (empty($list)) {
            return [];
        }
        // 查询不可用产品包ID
        $allPackageIds = array_column($list, 'package_id');
        $notAvailable = DssErpPackageV1Model::getRecords(['id' => $allPackageIds, 'status[!]' => DssErpPackageV1Model::STATUS_ON_SALE], 'id');

        $data = [];
        foreach ($list as $value) {
            $oneItem = [
                'package_id' => $value['package_id']
            ];
            if (in_array($value['package_id'], $notAvailable)) {
                $oneItem['error_message'] = Lang::getWord('package_not_available_for_sale');
            }
            if (!empty($value['cover'])) {
                $oneItem['product_img'] = $value['cover'];
                $oneItem['product_img_url'] = AliOSS::replaceShopCdnDomain($value['cover']);
            }
            $data[] = $oneItem;
        }
        return $data;
    }

    /**
     * 产品包详情
     * @param $packageId
     * @return array|bool
     * @throws RunTimeException
     */
    public static function packageDetail($packageId)
    {
        if (empty($packageId)) {
            return [];
        }
        return PackageService::getPackageV1Detail($packageId);
    }

    /**
     * 查询代理商渠道
     * @param $type
     * @return array|mixed|null
     */
    public static function getAgentChannel($type)
    {
        $default = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_distribution');
        $config = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_dict');
        $config = json_decode($config, true);
        if (empty($type) || empty($config)) {
            return $default;
        }
        return $config[$type] ?? $default;
    }

    /**
     * 获取用户当前代理
     * @param $userId
     * @return array|mixed
     */
    public static function getUserAgent($userId)
    {
        if (empty($userId)) {
            return [];
        }
        $agentUser = AgentUserModel::getRecords(['user_id' => $userId], ['agent_id', 'user_id']);
        $data = [];
        foreach ($agentUser as $agent) {
            if ($agent['status'] == AgentUserModel::BIND_STATUS_PENDING) {
                $data = $agent;
            }
            if ($agent['status'] == AgentUserModel::BIND_STATUS_BIND && empty($data)) {
                $data = $agent;
            }
        }
        if (!empty($data['agent_id'])) {
            $data = array_merge($data, AgentModel::getById($data['agent_id']));
        }
        return $data;
    }

    /**
     * 检测代理奖励明细中，用户和代理商的绑定关系状态字段数据
     * @param $userId
     * @param $agentId
     * @param $bindAgentId
     * @return int
     */
    public static function checkAwardIsBindStatus($userId, $agentId, $bindAgentId)
    {
        $agentUser = AgentUserModel::getRecord(['agent_id' => $agentId, 'user_id' => $userId, 'stage[>=]' => AgentUserModel::STAGE_TRIAL], ['id', 'stage', 'deadline']);
        if (empty($agentUser) && empty($bindAgentId)) {
            //无绑定关系,且无需绑定
            return AgentAwardDetailModel::IS_BIND_STATUS_NOT_HAVE_AGENT;
        } elseif (empty($agentUser) && !empty($bindAgentId)) {
            //无绑定关系,且需绑定
            return AgentAwardDetailModel::IS_BIND_STATUS_YES;
        } elseif (($agentUser['stage'] == AgentUserModel::STAGE_TRIAL || $agentUser['stage'] == AgentUserModel::STAGE_FORMAL) && ($agentUser['deadline'] >= time())) {
            //绑定中
            return AgentAwardDetailModel::IS_BIND_STATUS_YES;
        } else {
            //已解绑
            return AgentAwardDetailModel::IS_BIND_STATUS_NO;
        }
    }

    /**
     * 通过订单ID获取映射代理商数据,检测社群分班条件
     * @param $parentBillId
     * @param $studentId
     * @return bool
     */
    public static function distributionClassCondition($parentBillId, $studentId)
    {
        //分班条件:代理渠道购买并且代理商分成模式为线索+售卖不分班，其余均可以分班
        //订单映射数据
        $mapData = BillMapModel::get($parentBillId, $studentId, BillMapModel::USER_TYPE_AGENT);
        if (empty($mapData)) {
            return true;
        }
        //代理商数据
        $agentData = AgentModel::getAgentParentData([$mapData['user_id']]);
        if ($agentData[0]['division_model'] == AgentModel::DIVISION_MODEL_LEADS_AND_SALE) {
            return false;
        }
        return true;
    }

    /**
     * 代理商数据更改操作日志事件触发器
     * @param $agentId
     * @param $operatorId
     * @param $updateData
     * @param $opType
     */
    private static function agentDataUpdateOpLogEvent($agentId, $operatorId, $updateData, $opType)
    {
        //日志记录
        $agentOpEventObj = new AgentOpEvent(
            [
                'agent_id' => $agentId,
                'update_data' => $updateData,
                'operator_id' => $operatorId,
                'op_type' => $opType
            ]);
        $agentOpEventObj::fire($agentOpEventObj);
    }

    /**
     * 获取代理商id列表通过一级代理以及二级代理搜索条件
     * @param $params
     * @return array|bool
     */
    private static function getSearchAgentIdList($params)
    {
        if (empty($params['first_agent_name']) &&
            empty($params['first_agent_id']) &&
            empty($params['second_agent_name']) &&
            empty($params['second_agent_id'])) {
            return false;
        }
        //一级代理数据
        $firstAgentId = [];
        $firstAgentWhere = [];
        if (!empty($params['first_agent_name'])) {
            $firstAgentWhere['name[~]'] = $params['first_agent_name'];
        }
        if (is_numeric($params['first_agent_id'])) {
            $firstAgentWhere['id'] = $params['first_agent_id'];
        }
        if (!empty($firstAgentWhere)) {
            $firstAgentWhere['parent_id'] = 0;
            $firstAgentData = AgentModel::getRecords($firstAgentWhere, ['id']);
            if (empty($firstAgentData)) {
                return [];
            }
            $firstAgentId = array_column($firstAgentData, 'id');
        }
        //一级代理和二级代理相等的：查询一级代理的直接推广数据
        if (!empty($params['first_agent_id']) && !empty($params['second_agent_id']) && $params['first_agent_id'] == $params['second_agent_id']) {
            return $firstAgentId;
        }
        //二级代理数据
        $secondAgentId = [];
        $secondAgentWhere = [];
        if (!empty($firstAgentId)) {
            $secondAgentWhere['parent_id'] = $firstAgentId;
        }
        if (!empty($params['second_agent_name'])) {
            $secondAgentWhere['name[~]'] = $params['second_agent_name'];
        }
        if (is_numeric($params['second_agent_id'])) {
            $secondAgentWhere['id'] = $params['second_agent_id'];
        }
        if (!empty($secondAgentWhere)) {
            $secondAgentWhere['parent_id[>]'] = 0;
            $secondAgentData = AgentModel::getRecords($secondAgentWhere, ['id']);
            if (!empty($secondAgentData)) {
                $secondAgentId = array_column($secondAgentData, 'id');
            }
        }
        //一级和二级同时搜索
        if (!empty($firstAgentId) && (!empty($secondAgentWhere['name[~]']) || !empty($secondAgentWhere['id']))) {
            if (empty($secondAgentId)) {
                return [];
            } else {
                return $secondAgentId;
            }
        } else {
            return array_merge($firstAgentId, $secondAgentId);
        }
    }

    /**
     * 通过负责人id获取此负责人下的代理id
     * @param $employeeId
     * @return array
     */
    public static function getAgentIdByEmployeeId($employeeId)
    {
        $parentIdData = AgentModel::getRecords(["OR" => ['service_employee_id' => $employeeId, 'employee_id' => $employeeId]], ['id']);
        if (empty($parentIdData)) {
            return [];
        }
        $parentIds = array_column($parentIdData, 'id');
        $secondAgent = AgentModel::agentSecondaryData($parentIds);
        $secondAgentIds = [];
        if (!empty($secondAgent)) {
            $secondAgentIds = array_column($secondAgent, 'id');
        }
        return array_merge($parentIds, $secondAgentIds);
    }

    /**
     * 是否绑定了线下代理
     * @param $studentId
     * @param string $parentBillId
     * @param string $type
     * @return bool
     */
    public static function isBindOffLine($studentId, $parentBillId = '', $type = '')
    {
        $agentIdArr = AgentUserModel::getRecords(['user_id' => $studentId, 'stage[!]' => AgentUserModel::STAGE_REGISTER], ['agent_id']);
        $agentId = [];
        if (!empty($agentIdArr)) {
            $agentId = array_column($agentIdArr, 'agent_id');
        } else {
            if (!empty($parentBillId)) {
                $bindAndAwardCheckObj = new AgentDispatchService($parentBillId, $studentId, ['package_type' => $type]);
                $awardData = $bindAndAwardCheckObj::getBindAndBillOwnAgentData();
                if (!empty($awardData)) {
                    $agentId = array($awardData['bind_agent_id']);
                }
            }
        }
        if (!empty($agentId)) {
            $agentInfo = AgentModel::getAgentParentData($agentId);
            if (in_array(AgentModel::TYPE_OFFLINE, array_column($agentInfo, 'agent_type'))) {
                return true;
            }
        }
        return  false;
    }

    /**
     * 检测两个代理是否是属于同一个团队
     * @param $firstAgentId
     * @param $secondAgentId
     * @return bool
     */
    public static function checkTwoAgentIsTeam($firstAgentId, $secondAgentId)
    {
        if (empty($firstAgentId) || empty($secondAgentId)) {
            return false;
        }
        //同一个代理商
        if ($firstAgentId == $secondAgentId) {
            return true;
        }
        $data = AgentModel::getAgentParentData([$firstAgentId, $secondAgentId]);
        //代理商均为顶级代理
        if (empty($data[0]['p_id']) && empty($data[1]['p_id'])) {
            return false;
        }
        //同一个父级
        if ($data[0]['p_id'] == $data[1]['p_id']) {
            return true;
        }
        //上下级关系
        if (($data[0]['p_id'] == $data[1]['id']) || ($data[1]['p_id'] == $data[0]['id'])) {
            return true;
        }
        return false;
    }

    /**
     * 产品包关联代理信息
     *
     * @param int $packageId
     * @param string $fuzzySearch
     * @return array
     */
    public static function getAgentRelationToPackage(int $packageId, string $fuzzySearch = ''): array
    {
        $data = [
            'relation_people_number' => 0,
            'agent_list' => [],
            'relation' => []
        ];

        if (empty($packageId)) {
            return $data;
        }

        $isYearCard = ErpPackageV1Model::packageIsYearCard($packageId);

        if (empty($fuzzySearch)) {
            $where = [];
        } elseif (is_numeric($fuzzySearch)) {
            $where['id'] = $fuzzySearch;
        } else {
            $where['name[~]'] = $fuzzySearch;
        }

        $where['parent_id'] = 0;

        //所有agent数据
        $agentList = AgentModel::getRecords($where, ['id', 'parent_id', 'division_model', 'name', 'type']);
        if (empty($agentList)) {
            return $data;
        }

        $type = [];
        $typeDict = AgentModel::TYPE_DICT;
        array_walk($typeDict, function ($value, $key) use (&$type) {
            $type[$key]['id'] = 'type_' . $key;
            $type[$key]['name'] = $value;
            $type[$key]['number'] = 0;
            $type[$key]['list'] = [];
            $type[$key]['open'] = false;
        });

        //所有关联的agentId
        $relationAgent = self::getRelationAgentIds($packageId);

        $data['relation_people_number'] = count($relationAgent);

        foreach ($agentList as $agent) {
            $agent['disabled'] = false;

            //若推广的商品为年卡，则只能关联到线索+售卖模式下的代理商，可置灰线索获量模式的代理商，不可选中
            if ($isYearCard && $agent['division_model'] != AgentModel::DIVISION_MODEL_LEADS_AND_SALE) {
                $agent['disabled'] = true;
            }

            if (in_array($agent['id'], $relationAgent)) {
                $type[$agent['type']]['open'] = true;
            }

            $type[$agent['type']]['list'][] = $agent;
        }

        $open = []; //用与前端tree是否展开
        array_walk($type, function (&$value, $key) use (&$type, &$open) {
            $value['number'] = count($value['list']);
            if (empty($value['number'])) {
                unset($type[$key]);
            }
            if ($value['open']) {
                $open[] = $value['id'];
            }
        });

        $data['agent_list'] = array_values($type);
        $data['relation'] = array_values($relationAgent);
        $data['open'] = $open;

        return $data;
    }

    /**
     * 课包关联代理批量更新操作
     *
     * @param int $packageId
     * @param array $agentIds
     * @return bool
     * @throws RunTimeException
     */
    public static function updateAgentRelationToPackage(int $packageId, array $agentIds = []): bool
    {

        if (!empty($agentIds)){
            //过滤前端非法标识数据
            array_walk($agentIds, function ($value, $key) use (&$agentIds){
                if (!is_numeric($value)){
                    unset($agentIds[$key]);
                }
            });
        }
        //所有关联的agentId
        $relationAgent = self::getRelationAgentIds($packageId);

        if (empty($relationAgent) && empty($agentIds)) {
            return true;
        } elseif (!empty($relationAgent) && empty($agentIds)) {
            $res = AgentSalePackageModel::batchUpdateRecord(['status' => AgentSalePackageModel::STATUS_DEL],
                [
                    'package_id' => $packageId,
                    'status' => AgentSalePackageModel::STATUS_OK,
                    'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT
                ]);
            if (empty($res)) {
                throw new RunTimeException(['update_failure']);
            }
            return true;
        }

        //取差集
        $updateDate = array_diff($relationAgent, $agentIds);
        $createDate = array_diff($agentIds, $relationAgent);

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        if (!empty($updateDate)) {
            $res = AgentSalePackageModel::batchUpdateRecord(['status' => AgentSalePackageModel::STATUS_DEL],
                [
                    'package_id' => $packageId,
                    'agent_id' => $updateDate,
                    'status' => AgentSalePackageModel::STATUS_OK,
                    'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT
                ]);
            if (empty($res)) {
                $db->rollBack();
                throw new RunTimeException(['update_failure']);
            }
        }
        if (!empty($createDate)) {
            $res = AgentSalePackageModel::addPackageRelationAgentRecord($packageId, $createDate,
                UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
            if (empty($res)) {
                $db->rollBack();
                throw new RunTimeException(['insert_failure']);
            }
        }

        $db->commit();
        return true;
    }


    /**
     * 获取产品包关联的代理ids
     *
     * @param int $packageId
     * @return array
     */
    private static function getRelationAgentIds(int $packageId): array
    {
        //所有关联的agentId
        return array_filter(array_unique(array_column(
            AgentSalePackageModel::getRecords([
                'package_id' => $packageId,
                'status' => AgentSalePackageModel::STATUS_OK,
                'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT
            ], ['agent_id']) ?? [],
            'agent_id'
        )));
    }
}
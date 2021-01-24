<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/26
 * Time: 上午11:34
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Models\AgentDivideRulesModel;
use App\Models\AgentInfoModel;
use App\Models\AgentModel;
use App\Models\AreaCityModel;
use App\Models\AreaProvinceModel;
use App\Models\StudentInviteModel;


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
            'service_employee_id' => empty($params['service_employee_id']) ? 0 : $params['service_employee_id'],
            'uuid' => self::agentAuth($params['name'], $params['mobile']),
            'mobile' => $params['mobile'],
            'type' => $params['agent_type'],
            'country_code' => $params['country_code'],
            'create_time' => $time,
        ];
        self::checkAddAgentData($agentInsertData);
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
            'country' => (int)$params['country_code'],
            'province' => (int)$params['province_code'],
            'city' => (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
            'name' => $params['name'],
            'create_time' => $time,
        ];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentModel::add($agentInsertData, $agentDivideRulesInsertData, $agentInfoInsertData);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        } else {
            $db->commit();
        }
        return true;
    }

    /**
     * 检测新增代理商数据
     * @param $agentData
     * @throws RunTimeException
     */
    private static function checkAddAgentData($agentData)
    {
        //检测账户是否存在
        $agentExists = AgentModel::getRecord(['mobile' => $agentData['mobile']], ['id']);
        if (!empty($agentExists)) {
            throw new RunTimeException(['agent_have_exist']);
        }
        //检测父类是否存在
        if (!empty($agentData['parent_id'])) {
            $parentAgent = AgentModel::getRecord(['id' => $agentData['parent_id'], 'status' => AgentModel::STATUS_OK], ['id']);
            if (empty($parentAgent)) {
                throw new RunTimeException(['agent_parent_freeze']);
            }
        }
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
        $agentExists = AgentModel::getRecords(['OR' => ['id' => $params['agent_id'], 'mobile' => $params['mobile']]], ['id', 'mobile']);
        if (empty($agentExists)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        if (count($agentExists) > 1) {
            throw new RunTimeException(['agent_mobile_is_repeat']);
        }
        //agent数据
        $agentUpdateData = [
            'mobile' => $params['mobile'],
            'type' => $params['agent_type'],
            'service_employee_id' => empty($params['service_employee_id']) ? 0 : (int)$params['service_employee_id'],
            'country_code' => (int)$params['country_code'],
            'update_time' => $time,
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
            'country' => (int)$params['country_code'],
            'province' => empty($params['province_code']) ? 0 : (int)$params['province_code'],
            'city' => empty($params['city_code']) ? 0 : (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
            'name' => $params['name'],
            'update_time' => $time,
        ];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentModel::update($params['agent_id'], $agentUpdateData, $agentDivideRulesInsertData, $agentInfoUpdateData);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        return true;
    }


    /**
     * 代理运营统计数据
     * @param $agentId
     * @param $appId
     * @return array
     */
    public static function agentStaticsData($agentId, $appId)
    {
        $staticsData = [
            'detail' => [],
            'spread' => [],
            'secondary_agent' => [],
        ];
        //详情
        $staticsData['detail'] = self::detailAgent($agentId, $appId);
        if (!empty($staticsData['detail'])) {
            //推广数据统计
            $staticsData['spread'] = self::agentSpreadData($agentId);
            //二级代理
            $staticsData['secondary_agent'] = self::formatAgentData(self::agentSecondaryData([$agentId])[$agentId]);
            $staticsData['detail'] = self::formatAgentData([$staticsData['detail']]);
        }
        return $staticsData;
    }


    /**
     * 获取一级代理的二级代理数据
     * @param array $agentIds
     * @return array
     */
    private static function agentSecondaryData($agentIds)
    {
        $data = [];
        $secondaryList = AgentModel::agentSecondaryData($agentIds);
        if (empty($secondaryList)) {
            return $data;
        }
        //二级代理学生介绍数据
        $agentReferralStudent = array_column(StudentInviteModel::getReferralStudentCount(
            implode(',', array_column($secondaryList, 'id')),
            StudentInviteModel::REFEREE_TYPE_AGENT), null, 'referee_id');
        //二级数据按照父类id分组
        array_map(function ($item) use (&$data, $agentReferralStudent) {
            $item['referral_student_count'] = empty($agentReferralStudent[$item['id']]['s_count']) ? 0 : (int)$agentReferralStudent[$item['id']]['s_count'];
            $data[$item['parent_id']][] = $item;
        }, $secondaryList);
        return $data;
    }

    /**
     * 推广数据统计
     * @param $agentId
     * @return array
     */
    private static function agentSpreadData($agentId)
    {
        //等待订单回调完成在完善具体逻辑
        return [
            'referral_student_count' => 0,
            'referral_bill_count' => 0,
            'direct_referral_student_count' => 0,
            'direct_referral_bill_count' => 0,
            'secondary_count' => 0,
        ];
    }


    /**
     * 获取代理账户详情
     * @param $agentId
     * @param $appId
     * @return array
     */
    public static function detailAgent($agentId, $appId)
    {
        //详情
        $detail = AgentModel::detail($agentId, $appId);
        //微信数据:是否绑定,昵称
        if (!empty($detail)) {
            $detail['wx_bind_status'] = '是否绑定';
            $detail['wx_nick_name'] = '昵称';
        }
        return $detail;
    }


    /**
     * 获取一级代理数据列表
     * @param $params
     * @return array
     */
    public static function listAgent($params)
    {
        $where = [AgentModel::$table . '.parent_id' => 0];

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
        if (!empty($params['employee_id'])) {
            $where[AgentModel::$table . '.employee_id'] = $params['employee_id'];
        }
        if (!empty($params['service_employee_id'])) {
            $where[AgentModel::$table . '.service_employee_id'] = $params['service_employee_id'];
        }
        if (!empty($params['name'])) {
            $where[AgentInfoModel::$table . '.name'] = $params['name'];
        }
        $agentList = AgentModel::list($where);
        if (empty($agentList['list'])) {
            return $agentList;
        }
        //二级代理数&&推广学员总数
        $agentSecondary = self::agentSecondaryData(array_column($agentList['list'], 'id'));
        array_walk($agentList['list'], function (&$agv) use ($agentSecondary) {
            //二级代理数量
            $agv['secondary_count'] = count($agentSecondary[$agv['id']]);
            //推广学员总数
            $agv['referral_student_count'] += array_sum(array_column($agentSecondary[$agv['id']], 'referral_student_count'));
            //推广订单总数
            $agv['referral_bill_count'] = self::agentSpreadData($agv['id'])['referral_bill_count'];

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
            $agv['agent_type_name'] = DictConstants::getSet(DictConstants::AGENT_TYPE)[$agv['type']];
            $agv['status_name'] = DictConstants::getSet(DictConstants::AGENT)[$agv['status']];
            $agv['app_id_name'] = '智能陪练';

        }
        return $agentData;
    }

    /**
     * 冻结代理商账户
     * @param $agentId
     * @return bool
     * @throws RunTimeException
     */
    public static function freezeAgent($agentId)
    {
        $agentData = AgentModel::getById($agentId);
        if (empty($agentData)) {
            throw new RunTimeException(['agent_not_exist']);
        }
        $res = AgentModel::updateRecord($agentId, ['status' => AgentModel::STATUS_FREEZE, 'update_time' => time()]);
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
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

}
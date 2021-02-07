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
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\RC4;
use App\Libs\RedisDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\AgentApplicationModel;
use App\Models\AgentAwardDetailModel;
use App\Models\AgentBillMapModel;
use App\Models\AgentDivideRulesModel;
use App\Models\AgentModel;
use App\Models\AgentUserModel;
use App\Models\AreaCityModel;
use App\Models\AreaProvinceModel;
use App\Models\Dss\DssDictModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Models\GoodsResourceModel;
use App\Models\UserWeiXinInfoModel;
use App\Models\PosterModel;
use App\Models\UserWeiXinModel;
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
            'country' => $params['country_id'],
            'province' => (int)$params['province_code'],
            'city' => (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
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
            'name' => $params['name'],
            'type' => $params['agent_type'],
            'service_employee_id' => empty($params['service_employee_id']) ? 0 : (int)$params['service_employee_id'],
            'country_code' => $params['country_code'],
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
            'country' => $params['country_id'],
            'province' => empty($params['province_code']) ? 0 : (int)$params['province_code'],
            'city' => empty($params['city_code']) ? 0 : (int)$params['city_code'],
            'address' => empty($params['address']) ? '' : $params['address'],
            'remark' => empty($params['remark']) ? '' : $params['remark'],
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
        //推广订单数量
        $referralBills = array_column(AgentAwardDetailModel::getAgentBillCount($agentIdStr), null, 'agent_id');

        array_walk($agentList, function ($item) use (&$dataTree, $referralStudents, $referralBills) {
            if ($item['parent_id'] == 0) {
                //一级代理直接推广数据
                $dataTree[$item['id']]['son_num'] = 0;
                $dataTree[$item['id']]['total']['s_count'] = $dataTree[$item['id']]['self']['s_count'] = $referralStudents[$item['id']]['s_count'] ?? 0;
                $dataTree[$item['id']]['total']['b_count'] = $dataTree[$item['id']]['self']['b_count'] = $referralBills[$item['id']]['b_count'] ?? 0;
            } else {
                //一级代理的下属二级推广数据
                $dataTree[$item['parent_id']]['son'][$item['id']]['s_count'] = $referralStudents[$item['id']]['s_count'] ?? 0;
                $dataTree[$item['parent_id']]['son'][$item['id']]['b_count'] = $referralBills[$item['id']]['b_count'] ?? 0;
                //推广数据汇总
                $dataTree[$item['parent_id']]['total']['s_count'] += $dataTree[$item['parent_id']]['son'][$item['id']]['s_count'];
                $dataTree[$item['parent_id']]['total']['b_count'] += $dataTree[$item['parent_id']]['son'][$item['id']]['b_count'];
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
            $serviceEmployeeId = EmployeeModel::getRecord(['name' => $params['service_employee_name']], ['id']);
            if (empty($serviceEmployeeId)) {
                return $data;
            }
            $where[AgentModel::$table . '.service_employee_id'] = $serviceEmployeeId['id'];
        }
        if (!empty($params['name'])) {
            $where[AgentModel::$table . '.name'] = $params['name'];
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
        $agentTypeDict = DictConstants::getSet(DictConstants::AGENT_TYPE);
        $agentStatusDict = DictConstants::getSet(DictConstants::AGENT);
        $appIdDict = DictConstants::getSet(DictConstants::PACKAGE_APP_NAME);
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
            $agv['agent_type_name'] = $agentTypeDict[$agv['type']];
            $agv['status_name'] = $agentStatusDict[$agv['status']];
            $agv['app_id_name'] = empty($agv['app_id']) ? '' : $appIdDict[$agv['app_id']];

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
        return true;
    }

    /**
     * 解除冻结
     * @param $agentId
     * @return bool
     * @throws RunTimeException
     */
    public static function unFreezeAgent($agentId)
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
            throw new RunTimeException(['record_not_found']);
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
        if (self::checkAgentApplicationExists($mobile, $countryCode)) {
            throw new RunTimeException(['agent_application_exists']);
        }
        if (self::checkAgentExists($mobile, $countryCode)) {
            throw new RunTimeException(['agent_have_exist']);
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
                $tmpUserType = $tmpOtherInfo['user_type'] ?? 0;
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
        $defaultThumb = AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
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
     * @param $type
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getPopularizeOrderList($agentId, $type, $page, $limit)
    {
        $agentNameArr = [];
        $returnData = [
            'order_list' => [],
            'total' => 0,
        ];
        //获取直接绑定还是代理绑定
        if ($type == AgentModel::LEVEL_FIRST) {
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

        //获取订单
        $sqlLimitArr = [
            ($page - 1) * $limit,
            $limit
        ];
        list($orderList,$orderTotal) = AgentAwardDetailModel::getListByAgentId($agentIdArr, $sqlLimitArr) ?? [];
        if (empty($orderList)) {
            return $returnData;
        }
        $userIdArr = [];
        $orderIdArr = [];
        array_map(function ($item) use (&$userIdArr, &$orderIdArr) {
            $userIdArr[] = $item['student_id'];
            $extInfo = json_decode($item['ext'], true);
            $orderIdArr[] = $extInfo['parent_bill_id'] ?? 0;
        }, $orderList);

        $returnData['total'] = $orderTotal;

        //获取用户昵称头像
        $userNicknameArr = self::batchDssUserWxInfoByUserId($userIdArr);

        //获取手机号
        $mobileList = DssStudentModel::getRecords(['id' => $userIdArr], ['id', 'mobile']);
        $mobileList = is_array($mobileList) ? $mobileList : [];
        $encodeMobileArr = [];
        array_map(function ($item) use (&$encodeMobileArr) {
            $encodeMobileArr[$item['id']] = Util::hideUserMobile($item['mobile']);
        }, $mobileList);

        //组合返回数据
        $dict = DictConstants::getSet(DictConstants::CODE_STATUS);
        foreach ($orderList as $key => $val) {
            $tmpUserInfo = $userNicknameArr[$val['student_id']] ?? [];

            $orderList[$key]['thumb'] = $tmpUserInfo['thumb'] ?? '';
            $orderList[$key]['nickname'] = $tmpUserInfo['nickname'] ?? '';
            $orderList[$key]['mobile'] = $encodeMobileArr[$val['student_id']] ?? '';
            $orderList[$key]['second_agent_name'] = $agentNameArr[$val['agent_id']] ?? '';
            $orderList[$key]['format_pay_time'] = date("Y-m-d H:i:s", $val['buy_time']);
            $orderList[$key]['bill_amount'] = $orderList[$key]['bill_amount']/100;  //单位元
            $orderList[$key]['code_status_name'] = $dict[$val['code_status']] ?? '';

        }

        $returnData['order_list'] = $orderList;
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
     * @return array
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
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
        $agentInfo['orders'] = AgentAwardDetailModel::getCount(
            [
                'agent_id' => $ids,
                'action_type[!]' => AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER,
                'in_bind' => AgentAwardDetailModel::IS_BIND_STATUS_YES,
            ]
        );
        $agentInfo['sec_agents'] = AgentModel::getCount(['parent_id' => $agentId]);
        $agentInfo['config']     = self::popularMaterialInfo($agentId);
        $agentInfo['parent']     = AgentModel::getRecord(['id' => $agentInfo['parent_id']]);
        $agentInfo['show_status'] = self::getAgentStatus($agentInfo);
        return $agentInfo;
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
        if (self::checkAgentExists($params['mobile'])) {
            throw new RunTimeException(['agent_have_exist']);
        }
        $data = [
            'name'         => $params['name'],
            'mobile'       => $params['mobile'],
            'parent_id'    => $agentId,
            'agent_type'   => AgentModel::TYPE_DISTRIBUTION,
            'country_code' => $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE,
            'app_id'       => UserCenter::AUTH_APP_ID_OP_AGENT,
            'divide_type'  => AgentDivideRulesModel::TYPE_LEADS,
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
            throw new RunTimeException(['agent_have_exist']);
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
        return AgentModel::getById($agentId);
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
        return $parent;
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
        }
        return $records;
    }

    /**
     * 推广学员列表数据
     * @param $params
     * @return array|mixed
     */
    public static function recommendUsersList($params)
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
            $agentUserWhere .= ' AND (au.deadline>= ' . $time . ' OR au.stage=' . AgentUserModel::STAGE_FORMAL . ') ';
        }
        if (!empty($params['bind_status']) && $params['bind_status'] == AgentUserModel::BIND_STATUS_UNBIND) {
            $agentUserWhere .= ' AND au.deadline< ' . $time . ' AND au.stage=' . AgentUserModel::STAGE_TRIAL . ' ';
        }

        //一级代理数据
        $firstAgentWhere = ' ';
        if (!empty($params['first_agent_name'])) {
            $firstAgentWhere .= " AND fa.name='" . $params['first_agent_name'] . "'";
        }
        if (!empty($params['first_agent_id'])) {
            $firstAgentWhere .= ' AND fa.id=' . $params['first_agent_id'];
        }
        if (!empty($params['agent_type'])) {
            $firstAgentWhere .= ' AND fa.type=' . $params['agent_type'];
        }
        //二级代理数据
        $secondAgentTable = 'sa';
        $secondAgentWhere = ' ';
        if (!empty($params['second_agent_id'])) {
            $secondAgentWhere .= ' AND ' . $secondAgentTable . '.id=' . $params['second_agent_id'];
        }
        if (!empty($params['second_agent_name'])) {
            $secondAgentWhere .= " AND " . $secondAgentTable . ".name='" . $params['second_agent_name'] . "'";
        }
        list($recommendUserList['count'], $recommendUserList['list']) = AgentUserModel:: agentRecommendUserList($agentUserWhere, $firstAgentWhere, $secondAgentWhere, $params['page'], $params['count']);
        if (empty($recommendUserList['count'])) {
            return $recommendUserList;
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
        $dict = DictService::getTypesMap([DictConstants::AGENT_TYPE['type'], DictConstants::AGENT_USER_STAGE['type'], DictConstants::PACKAGE_APP_NAME['type'], DictConstants::AGENT_BIND_STATUS['type']]);

        array_walk($recommendUserData['list'], function (&$rv) use ($studentListDetail, $dict) {
            $rv['student_name'] = $studentListDetail[$rv['user_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['user_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['user_id']]['mobile']);
            //绑定关系状态
            $rv['bind_status'] = self::getAgentUserBindStatus($rv['deadline'], $rv['stage']);

            $rv['type_name'] = $dict[DictConstants::AGENT_TYPE['type']][$rv['type']]['value'];
            $rv['stage_name'] = $dict[DictConstants::AGENT_USER_STAGE['type']][$rv['stage']]['value'];
            $rv['app_id_name'] = $dict[DictConstants::PACKAGE_APP_NAME['type']][$rv['app_id']]['value'];
            $rv['bind_status_name'] = $dict[DictConstants::AGENT_BIND_STATUS['type']][$rv['bind_status']]['value'];
            unset($rv['second_agent_id']);
        });
        return $recommendUserData;
    }

    /**
     * 推广订单列表数据
     * @param $params
     * @return array|mixed
     */
    public static function recommendBillsList($params)
    {
        $recommendUserList = ['count' => 0, 'list' => []];
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
                return $recommendUserList;
            }
            $whereStudentIds = array_column($dssStudentList, 'id');
        }

        //订单状态 支付时间
        $giftCodeWhere = ' ';
        if (!empty($params['code_status'])) {
            $giftCodeWhere .= ' AND gc.code_status=' . $params['code_status'];
        }
        if (!empty($params['pay_start_time'])) {
            $giftCodeWhere .= ' AND gc.create_time>=' . $params['pay_start_time'];
        }
        if (!empty($params['pay_end_time'])) {
            $giftCodeWhere .= ' AND gc.create_time<=' . $params['pay_end_time'];
        }
        //订单ID 购买产品包
        $agentBillWhere = ' ab.id>0 AND ab.action_type != ' . AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER;
        if (!empty($whereStudentIds)) {
            $agentBillWhere .= ' AND  ab.student_id in( ' . implode(',', $whereStudentIds) . ')';
        }
        if (!empty($params['parent_bill_id'])) {
            $agentBillWhere .= " AND ab.ext->>'$.parent_bill_id'=" . $params['parent_bill_id'];
        }
        if (!empty($params['package_id'])) {
            $agentBillWhere .= " AND ab.ext->>'$.package_id'=" . $params['package_id'];
        }
        if (isset($params['is_bind']) && is_numeric($params['is_bind'])) {
            $agentBillWhere .= " AND ab.is_bind=" . $params['is_bind'];
        }

        //一级代理数据
        $firstAgentWhere = ' ';
        if (!empty($params['first_agent_name'])) {
            $firstAgentWhere .= " AND fa.name='" . $params['first_agent_name'] . "'";
        }
        if (!empty($params['first_agent_id'])) {
            $firstAgentWhere .= ' AND fa.id=' . $params['first_agent_id'];
        }
        if (!empty($params['agent_type'])) {
            $firstAgentWhere .= ' AND fa.type=' . $params['agent_type'];
        }
        //二级代理数据
        $secondAgentTable = 'sa';
        $secondAgentWhere = ' ';
        if (!empty($params['second_agent_id'])) {
            $secondAgentWhere .= ' AND ' . $secondAgentTable . '.id=' . $params['second_agent_id'];
        }
        if (!empty($params['second_agent_name'])) {
            $secondAgentWhere .= " AND " . $secondAgentTable . ".name='" . $params['second_agent_name'] . "'";
        }
        list($recommendUserList['count'], $recommendUserList['list']) = AgentAwardDetailModel:: agentBillsList($agentBillWhere, $firstAgentWhere, $secondAgentWhere, $giftCodeWhere, $params['page'], $params['count']);
        if (empty($recommendUserList['count'])) {
            return $recommendUserList;
        }
        return self::formatRecommendBillsData($recommendUserList);
    }


    /**
     * 格式化推广学员列表数据
     * @param $recommendUserData
     * @return mixed
     */
    private static function formatRecommendBillsData($recommendUserData)
    {
        //学生详细数据
        $studentListDetail = array_column(StudentService::searchStudentList(['id' => array_column($recommendUserData['list'], 'student_id')]), null, 'id');
        $dict = DictService::getTypesMap([DictConstants::AGENT_TYPE['type'], DictConstants::CODE_STATUS['type'], DictConstants::PACKAGE_APP_NAME['type'], DictConstants::YSE_OR_NO_STATUS['type']]);
        array_walk($recommendUserData['list'], function (&$rv) use ($studentListDetail, $dict) {
            $rv['student_name'] = $studentListDetail[$rv['student_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['student_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['student_id']]['mobile']);
            $rv['bill_amount'] = $rv['bill_amount'] / 100;
            $rv['type_name'] = $dict[DictConstants::AGENT_TYPE['type']][$rv['type']]['value'];
            $rv['code_status_name'] = $dict[DictConstants::CODE_STATUS['type']][$rv['code_status']]['value'];
            $rv['app_id_name'] = $dict[DictConstants::PACKAGE_APP_NAME['type']][$rv['app_id']]['value'];
            $rv['is_bind_name'] = $dict[DictConstants::YSE_OR_NO_STATUS['type']][$rv['is_bind']]['value'];
            unset($rv['second_agent_id']);
        });
        return $recommendUserData;
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

        $where['ORDER'] = ['create_time' => 'DESC'];
        $where['LIMIT'] = [$page - 1, $count];
        $count = AgentApplicationModel::getCount($where);
        if (empty($count)) {
            return [
                'list'       => [],
                'totalCount' => 0
            ];
        }
        $list = AgentApplicationModel::getRecords($where, ['id', 'name', 'mobile', 'create_time' => Medoo::raw('FROM_UNIXTIME(<create_time>)'), 'remark']);
        return [
            'list'       => $list,
            'totalCount' => $count
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
        $packageId = self::getPackageId('WEB_STUDENT_CONFIG', 'mini_package_id');
        $exist = GoodsResourceModel::getRecord(['package_id' => $packageId], ['id', 'package_id', 'ext']);
        $ext = [
            [
                "key"   => "product_img",
                "type"  => GoodsResourceModel::CONTENT_TYPE_IMAGE,
                "value" => $params['product_img']
            ],
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
     * @param int $agentId
     * @return array|mixed
     * 获取推广素材方法
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function popularMaterialInfo($agentId = 0)
    {
        $packageId = self::getPackageId('WEB_STUDENT_CONFIG', 'mini_package_id');
        $result = GoodsResourceModel::getRecord(['package_id' => $packageId], ['id', 'package_id', 'ext']);
        if (empty($result)) {
            return $result;
        }
        $data = [];
        $agentInfo = [];
        if (!empty($agentId)) {
            $agentInfo = AgentModel::getById($agentId);
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
                    $channel = GoodsResourceModel::getAgentChannel($agentInfo['type'] ?? 0);
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
                    $data['share_data'] = '&param_id=' . ReferralActivityService::getParamsId(
                        array_merge(
                            [
                                'r'       => RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], UserWeiXinModel::USER_TYPE_AGENT . "_" . $agentId),
                                'c'       => $channel,
                                'type'    => UserWeiXinModel::USER_TYPE_AGENT,
                                'user_id' => $agentId,
                            ],
                            $extParams
                        )
                    );
                }
            }
        }
        return $data;
    }

    /**
     * 检测当前代理商是否有效
     * @param $agentId
     * @return bool
     */
    public static function checkAgentStatusIsValid($agentId)
    {
        $data = AgentModel::getAgentParentData($agentId);
        if (($data['status'] != AgentModel::STATUS_FREEZE) && ($data['p_status'] != AgentModel::STATUS_FREEZE)) {
            return true;
        }
        return false;
    }

    /**
     * 检测此订单是否为代理商转化而来
     * @param $studentId
     * @param $parentBillId
     * @param $packageType
     * @return int
     */
    public static function checkBillIsAgentReferral($studentId, $parentBillId, $packageType)
    {
        $agentId = 0;
        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
            //体验课:订单映射关系是否存在
            $billAgentMap = AgentBillMapModel::get($parentBillId, $studentId);
            if (!empty($billAgentMap)) {
                $agentId = $billAgentMap['agent_id'];
            }
        } elseif ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            //正式课:检测此学生是否存在绑定关系的代理数据
            $validAgentBind = AgentUserModel::getRecord(['user_id' => $studentId, 'stage[>=]' => AgentUserModel::STAGE_TRIAL], ['agent_id']);
            if (!empty($validAgentBind)) {
                $agentId = $validAgentBind['agent_id'];
            }
        }
        return $agentId;
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
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
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
use App\Models\StudentInviteModel;
use App\Models\UserWeiXinModel;


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
            'country' => (int)$params['country_code'],
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
     * @param array $agentIds
     * @param array $spreadData
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
     * @param $appId
     * @return array
     */
    public static function detailAgent($agentId, $appId)
    {
        //详情
        $detail = AgentModel::detail($agentId, $appId);
        //微信数据:是否绑定,昵称
        if (!empty($detail)) {
            $bindData = UserWeiXinModel::userBindData($agentId, UserWeiXinModel::USER_TYPE_AGENT, UserWeiXinModel::BUSI_TYPE_AGENT_MINI, $appId);
            $detail['wx_bind_status'] = empty($bindData) ? 0 : 1;
            $detail['wx_nick_name'] = self::batchGetUserNicknameAndHead([$agentId])['nickname'];
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
        $res = AgentModel::updateRecord($agentId, ['status' => AgentModel::STATUS_FREEZE, 'update_time' => time()]);
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
        $res = AgentModel::updateRecord($agentId, ['status' => AgentModel::STATUS_OK, 'update_time' => time()]);
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
     * @param $unionId
     * @param string $countryCode
     * @param null $userType
     * @param null $busiType
     * @return array
     * @throws RunTimeException
     */
    public static function bindAgentWechat($appId, $mobile, $openId, $unionId, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE, $userType = null, $busiType = null)
    {
        $agentInfo = AgentModel::getByMobile($mobile, $countryCode);
        if (empty($agentInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if (self::checkAgentFreeze($agentInfo)) {
            throw new RunTimeException(['agent_freeze']);
        }
        if (empty($userType)) {
            $userType = UserWeiXinModel::USER_TYPE_AGENT;
        }
        if (empty($busiType)) {
            $busiType = UserWeiXinModel::BUSI_TYPE_AGENT_MINI;
        }
        $data = [
            'user_id' => $agentInfo['id'],
            'user_type' => $userType,
            'open_id' => $openId,
            'union_id' => $unionId,
            'status' => UserWeiXinModel::STATUS_NORMAL,
            'busi_type' => $busiType,
            'app_id' => $appId,
        ];
        $bindInfo = UserWeiXinModel::getRecord($data);
        if (empty($bindInfo)) {
            $data['create_time'] = time();
            UserWeiXinModel::insertRecord($data);
        }
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
            'status' => UserWeiXinModel::STATUS_NORMAL,
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
     * @param string $countryCode
     * @return bool
     */
    public static function checkAgentExists($mobile, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        if (empty($mobile)) {
            return false;
        }
        $agentInfo = AgentModel::getRecord(['mobile' => $mobile, 'country_code' => $countryCode]);
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
            && time() - $info['update_time'] >= Util::TIMESTAMP_ONEWEEK) {
            return true;
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
            $secondAgentList = AgentModel::getRecords(['parent_id' => $agentId], ['id']);
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
        $bindUserList = AgentUserModel::getListByAgentId($agentIdArr, $sqlLimitArr);

        //没有绑定用户直接返回空
        if (empty($bindUserList)) {
            return $returnData;
        }

        //获取总数
        $returnData['total'] = AgentUserModel::getCount(['agent_id' => $agentIdArr, 'stage[!]' => AgentUserModel::STAGE_REGISTER]);


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
        $userNicknameArr = self::batchGetUserNicknameAndHead($userIdArr);

        //组合数据
        foreach ($bindUserList as $key => $val) {
            $tmpUserInfo = $userNicknameArr[$val['user_id']] ?? [];
            $bindUserList[$key]['thumb'] = $tmpUserInfo['thumb'] ?? '';     //这里如果需要返回默认头像的话需要调整
            $bindUserList[$key]['nickname'] = $tmpUserInfo['nickname'] ?? '';
            $bindUserList[$key]['mobile'] = $encodeMobileArr[$val['user_id']] ?? '';
            $bindUserList[$key]['second_agent_name'] = $agentNameArr[$val['user_id']] ?? '';
            $bindUserList[$key]['format_bind_time'] = date('Y-m-d H:i:s', $val['bind_time']);
            $bindUserList[$key]['bind_status'] = self::getAgentUserBindStatus($val['deadline'], $val['stage']);
        }

        $returnData['bind_user_list'] = $bindUserList;
        return $returnData;
    }

    /**
     * 批量获取用户头像和昵称，  如果缓存中不存在头像和昵称则批量请求微信接口获取最新消息
     * 缓存有效时间 24小时
     * @param array $userIdArr
     * @return array
     */
    public static function batchGetUserNicknameAndHead(array $userIdArr)
    {
        $userNicknameAndHead = [];
        // 获取redis缓存数据，如果缓存不存在，请求微信接口获取数据
        // 微信接口拿到数据后更新redis和数据表字段
        if (empty($userIdArr)) {
            return $userNicknameAndHead;
        }

        $notFindUser = [];
        $redis = RedisDB::getConn();
        foreach ($userIdArr as $_user_id) {
            $tmpUserInfo = $redis->get(UserWeixinModel::REDIS_KEY_USER_WX_INFO_PREFIX . $_user_id);
            if ($tmpUserInfo) {
                $tmpUserInfoArr = json_decode($tmpUserInfo, true);
                $userNicknameAndHead[$_user_id] = [
                    'nickname' => $tmpUserInfoArr['nickname'] ?? '',
                    'thumb' => $tmpUserInfoArr['thumb'] ?? '',
                ];
            } else {
                $notFindUser[] = $_user_id;
            }
        }

        //获取缓存已经过期的用户头像和昵称
        if (!empty($notFindUser)) {
            $userList = UserWeixinModel::getRecords(['user_id' => $notFindUser], ['user_id', 'open_id']);
            $openidArr = [];
            array_map(function ($item) use (&$openidArr) {
                $openidArr[$item['open_id']] = $item['user_id'];
            }, $userList);
            $wechat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_OP_AGENT, UserWeixinModel::BUSI_TYPE_AGENT_MINI);
            $wxUserList = $wechat->batchGetUserInfo(array_keys($openidArr));
            $wxUserList = $wxUserList['user_info_list'] ?? [];
            foreach ($wxUserList as $wxVal) {
                $tmpUserId = $openidArr[$wxVal['openid']] ?? 0;
                $userNicknameAndHead[$tmpUserId] = [
                    'nickname' => $wxVal['nickname'] ?? '',
                    'thumb' => $wxVal['headimgurl'] ?? '',
                ];
                //更新数据表
                // UserWeixinModel::updateWxInfoByUserid($tmpUserId, $userNicknameAndHead[$tmpUserId]);
            }
        }

        return $userNicknameAndHead;
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
            $secondAgentList = AgentModel::getRecords(['parent_id' => $agentId], ['id']);
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
        $orderList = AgentBillMapModel::getListByAgentId($agentIdArr, $sqlLimitArr);
        $userIdArr = [];
        $orderIdArr = [];
        array_map(function ($item) use (&$userIdArr, &$orderIdArr) {
            $userIdArr[] = $item['student_id'];
            $orderIdArr[] = $item['bill_id'];
        }, $orderList);

        $returnData['total'] = AgentBillMapModel::getCount(['agent_id' => $agentIdArr]);

        //获取用户昵称头像
        $userNicknameArr = self::batchGetUserNicknameAndHead($userIdArr);

        //获取手机号
        $mobileList = DssStudentModel::getRecords(['id' => $userIdArr], ['id', 'mobile']);
        $mobileList = is_array($mobileList) ? $mobileList : [];
        $encodeMobileArr = [];
        array_map(function ($item) use (&$encodeMobileArr) {
            $encodeMobileArr[$item['id']] = Util::hideUserMobile($item['mobile']);
        }, $mobileList);

        //获取订单详细信息， 商品名称，订单号，实付金额，商品类型
        $orderInfoList = DssGiftCodeModel::getOrderList(['order_id' => $orderIdArr], false);
        $orderInfoArr = [];
        array_map(function ($itemOrder) use (&$orderInfoArr) {
            $orderInfoArr[$itemOrder['parent_bill_id']] = $itemOrder;
        }, $orderInfoList['list']);

        //组合返回数据
        foreach ($orderList as $key => $val) {
            $tmpUserInfo = $userNicknameArr[$val['user_id']] ?? [];
            $tmpOrderInfo = $orderInfoArr[$val['bill_id']] ?? [];

            $orderList[$key]['thumb'] = $tmpUserInfo['thumb'] ?? '';
            $orderList[$key]['nickname'] = $tmpUserInfo['nickname'] ?? '';
            $orderList[$key]['mobile'] = $encodeMobileArr[$val['user_id']] ?? '';
            $orderList[$key]['second_agent_name'] = $agentNameArr[$val['user_id']] ?? '';
            $orderList[$key]['format_pay_time'] = date("Y-m-d H:i:s", $val['pay_time']);
            //订单信息
            $orderList[$key]['package_name'] = $tmpOrderInfo['package_name'] ?? '';
            $orderList[$key]['bill_amount'] = $tmpOrderInfo['bill_amount'] ?? 0;
            $orderList[$key]['code_status'] = $tmpOrderInfo['code_status'] ?? 0;
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
    public static function getAgentUserBindStatus($deadline, $stage)
    {
        //未绑定 - 注册状态不存在绑定和不绑定的关系
        if ($stage == 0) {
            return AgentUserModel::BIND_STATUS_UNBIND;
        }
        switch ($deadline) {
            case 0:    //已购年卡 - 永久绑定中
                return AgentUserModel::BIND_STATUS_BIND;
            case $deadline >= time():   //已购体验
                return AgentUserModel::BIND_STATUS_BIND;
            default:    //解绑
                return AgentUserModel::BIND_STATUS_DEL_BIND;
        }
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
        if (!empty($params['bind_status']) && $params['bind_status'] == AgentUserModel::BIND_STATUS_DEL_BIND) {
            $agentUserWhere .= ' AND au.deadline< ' . $time . 'AND au.stage=' . AgentUserModel::STAGE_TRIAL . ' ';
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
        array_walk($recommendUserData['list'], function (&$rv) use ($studentListDetail) {
            $rv['student_name'] = $studentListDetail[$rv['user_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['user_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['user_id']]['mobile']);
            //绑定关系状态
            $rv['bind_status'] = self::getAgentUserBindStatus($rv['deadline'], $rv['stage']);
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
        array_walk($recommendUserData['list'], function (&$rv) use ($studentListDetail) {
            $rv['student_name'] = $studentListDetail[$rv['student_id']]['name'];
            $rv['student_uuid'] = $studentListDetail[$rv['student_id']]['uuid'];
            $rv['student_mobile'] = Util::hideUserMobile($studentListDetail[$rv['student_id']]['mobile']);
            $rv['bill_amount'] = $rv['bill_amount'] / 100;
            unset($rv['second_agent_id']);
        });
        return $recommendUserData;
    }
}
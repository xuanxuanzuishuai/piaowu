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
use App\Models\AgentBillMapModel;
use App\Models\AgentDivideRulesModel;
use App\Models\AgentInfoModel;
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
        $data = array_fill_keys($agentIds, []);
        $secondaryList = AgentModel::agentSecondaryData($agentIds);
        if (empty($secondaryList)) {
            return $data;
        }
        //二级代理学生介绍数据
        $agentReferralStudent = array_column(StudentInviteModel::getReferralStudentCount(
            implode(',', array_column($secondaryList, 'id')),
            StudentInviteModel::REFEREE_TYPE_AGENT), null, 'referee_id');
        //二级代理订单推广数据
        //todo
        $agentReferralBill = [];
        //二级数据按照父类id分组
        array_map(function ($item) use (&$data, $agentReferralStudent, $agentReferralBill) {
            $item['referral_student_count'] = empty($agentReferralStudent[$item['id']]['s_count']) ? 0 : (int)$agentReferralStudent[$item['id']]['s_count'];
            $item['referral_bill_count'] = empty($agentReferralBill[$item['id']]) ? 0 : $agentReferralBill[$item['id']]['b_count'];
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
            'user_id'     => $agentInfo['id'],
            'user_type'   => $userType,
            'open_id'     => $openId,
            'union_id'    => $unionId,
            'status'      => UserWeiXinModel::STATUS_NORMAL,
            'busi_type'   => $busiType,
            'app_id'      => $appId,
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
            'user_id'   => $userId,
            'open_id'   => $openId,
            'status'    => UserWeiXinModel::STATUS_NORMAL,
            'user_type' => UserWeiXinModel::USER_TYPE_AGENT,
            'busi_type' => UserWeiXinModel::BUSI_TYPE_AGENT_MINI,
            'app_id'    => UserCenter::AUTH_APP_ID_OP_AGENT,
        ];
        $data = [
            'status'      => UserWeiXinModel::STATUS_NORMAL,
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
            'name'         => $data['name'],
            'mobile'       => $mobile,
            'country_code' => $countryCode,
            'create_time'  => time(),
            'update_time'  => 0
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
            && time()-$info['update_time'] >= Util::TIMESTAMP_ONEWEEK) {
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
        $returnData['total'] = AgentUserModel::getCount(['agent_id' => $agentIdArr,'stage[!]' => AgentUserModel::STAGE_REGISTER]);


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
            $bindUserList[$key]['bind_status'] = self::getAgentUserBindStatus($val['deadline'],$val['stage']);
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

        $returnData['total'] = AgentBillMapModel::getCount(['agent_id'=>$agentIdArr]);

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
     * @param $deadline
     * @return int
     */
    public static function getAgentUserBindStatus($deadline,$stage){
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
}
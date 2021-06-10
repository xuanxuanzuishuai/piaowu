<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;

class AgentModel extends Model
{
    public static $table = "agent";
    //状态：1正常 2冻结
    const STATUS_OK = 1;
    const STATUS_FREEZE = 2;
    const LEVEL_FIRST = 1; //一级代理

    const STATUS_DICT = [
        self::STATUS_FREEZE => '已冻结',
        self::STATUS_OK => '正常'
    ];

    // 1分销渠道 2个人家长代理 3线下代理 4个人老师代理
    const TYPE_DISTRIBUTION = 1;
    const TYPE_INDIVIDUAL_PARENT = 2;
    const TYPE_OFFLINE = 3;
    const TYPE_INDIVIDUAL_TEACHER = 4;
    const TYPE_DICT = [
        self::TYPE_DISTRIBUTION => '分销渠道',
        self::TYPE_INDIVIDUAL_PARENT => '个人家长代理',
        self::TYPE_OFFLINE => '线下代理',
        self::TYPE_INDIVIDUAL_TEACHER => '个人老师代理',
    ];

    //分成模式:1线索获量 2线索获量+售卖模式
    const DIVISION_MODEL_LEADS = 1;
    const DIVISION_MODEL_LEADS_AND_SALE = 2;

    /**
     * 新增代理账户
     * @param $agentData
     * @param $agentDivideRulesInsertData
     * @param $agentInfoInsertData
     * @param $packageIds
     * @param $appId
     * @param $organizationInsertData
     * @return bool
     */
    public static function add($agentData, $agentDivideRulesInsertData, $agentInfoInsertData, $packageIds, $appId, $organizationInsertData)
    {
        //记录代理商基础数据
        $agentId = self::insertRecord($agentData);
        if (empty($agentId)) {
            SimpleLogger::error('insert agent base data error', $agentData);
            return false;
        }
        //记录代理商分成规则数据
        $agentDivideRulesInsertData['agent_id'] = $agentId;
        $ruleId = AgentDivideRulesModel::insertRecord($agentDivideRulesInsertData);
        if (empty($ruleId)) {
            SimpleLogger::error('insert agent divide rule data error', $agentDivideRulesInsertData);
            return false;
        }
        //记录代理商扩展数据
        $agentInfoInsertData['agent_id'] = $agentId;
        $infoId = AgentInfoModel::insertRecord($agentInfoInsertData);
        if (empty($infoId)) {
            SimpleLogger::error('insert agent info data error', $agentInfoInsertData);
            return false;
        }
        //记录代理商售卖商品包列表
        if (!empty($packageIds) && is_array($packageIds)) {
            $salePackageId = AgentSalePackageModel::addRecord($packageIds, $agentId, $appId);
            if (empty($salePackageId)) {
                return false;
            }
        }
        //线下代理机构扩展信息
        if (!empty($organizationInsertData)) {
            $organizationInsertData['agent_id'] = $agentId;
            $orgId = AgentOrganizationModel::insertRecord($organizationInsertData);
            if (empty($orgId)) {
                SimpleLogger::error('insert agent organization data error', $organizationInsertData);
                return false;
            }
        }
        return true;
    }

    /**
     * 编辑代理账户
     * @param $agentId
     * @param $agentUpdateData
     * @param $agentDivideRulesInsertData
     * @param $agentInfoUpdateData
     * @param $packageIds
     * @param $appId
     * @param $organizationUpdateData
     * @return bool
     */
    public static function update($agentId, $agentUpdateData, $agentDivideRulesInsertData, $agentInfoUpdateData, $packageIds, $appId, $organizationUpdateData)
    {
        //编辑代理商基础数据
        $baseUpdateRes = AgentModel::updateRecord($agentId, $agentUpdateData);
        if (empty($baseUpdateRes)) {
            SimpleLogger::error('update agent base data error', []);
            return false;
        }
        //编辑代理商分成规则数据
        $ruleUpdateRes = AgentDivideRulesModel::batchUpdateRecord(
            ['status' => AgentDivideRulesModel::STATUS_DEL],
            [
                'agent_id' => $agentId,
                'app_id' => $agentDivideRulesInsertData['app_id'],
            ]);
        if (empty($ruleUpdateRes)) {
            SimpleLogger::error('update agent divide rule data error', []);
            return false;
        }
        //记录代理商分成规则数据
        $ruleId = AgentDivideRulesModel::insertRecord($agentDivideRulesInsertData);
        if (empty($ruleId)) {
            SimpleLogger::error('insert agent divide rule data error', $agentDivideRulesInsertData);
            return false;
        }
        //编辑代理商扩展数据
        $infoId = AgentInfoModel::batchUpdateRecord($agentInfoUpdateData, ['agent_id' => $agentId]);
        if (empty($infoId)) {
            SimpleLogger::error('update agent info data error', $agentInfoUpdateData);
            return false;
        }
        //编辑代理商售卖课包数据
        AgentSalePackageModel::batchUpdateRecord(
            ['status' => AgentSalePackageModel::STATUS_DEL],
            [
                'agent_id' => $agentId,
                'app_id' => $agentDivideRulesInsertData['app_id'],
            ]);
        //记录代理商售卖课包数据
        if (!empty($packageIds) && is_array($packageIds)) {
            $salePackageId = AgentSalePackageModel::addRecord($packageIds, $agentId, $appId);
            if (empty($salePackageId)) {
                return false;
            }
        }
        //线下代理机构扩展信息
        $orgId = true;
        if (!empty($organizationUpdateData)) {
            if (!empty($organizationUpdateData['update_time'])) {
                $orgId = AgentOrganizationModel::updateRecord($agentId, $organizationUpdateData);
            } else {
                $orgId = AgentOrganizationModel::insertRecord($organizationUpdateData);
            }
        }
        if (empty($orgId)) {
            SimpleLogger::error('update agent organization data error', $organizationUpdateData);
            return false;
        }
        return true;
    }

    /**
     * 获取代理账户详情
     * @param $agentId
     * @return array
     */
    public static function detail($agentId)
    {
        $db = MysqlDB::getDB();
        $data = $db->select(
            self::$table,
            [
                "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
                "[><]" . AgentDivideRulesModel::$table => ['id' => 'agent_id'],
                "[>]" . AgentOrganizationModel::$table => ['id' => 'agent_id'],
                "[><]" . EmployeeModel::$table => ['employee_id' => 'id'],
                "[>]" . EmployeeModel::$table . '(EA)' => ['service_employee_id' => 'id'],

            ],
            [
                self::$table . '.id',
                self::$table . '.mobile',
                self::$table . '.service_employee_id',
                self::$table . '.employee_id',
                self::$table . '.type',
                self::$table . '.status',
                self::$table . '.name',
                self::$table . '.country_code',
                self::$table . '.division_model',
                AgentOrganizationModel::$table . '.name(organization)',
                AgentInfoModel::$table . '.country',
                AgentInfoModel::$table . '.province',
                AgentInfoModel::$table . '.city',
                AgentInfoModel::$table . '.district',
                AgentInfoModel::$table . '.address',
                AgentInfoModel::$table . '.remark',
                AgentDivideRulesModel::$table . '.app_id',
                AgentDivideRulesModel::$table . '.rule',
                EmployeeModel::$table . '.name(e_name)',
                'EA.name(e_s_name)',
            ],
            [
                self::$table . '.id' => $agentId,
                AgentDivideRulesModel::$table . '.status' => AgentDivideRulesModel::STATUS_OK,
            ]
        );
        return $data[0];
    }

    /**
     * 获取代理账户列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     */
    public static function list($where, $page, $limit)
    {
        $data = ['count' => 0, 'list' => [],];
        $db = MysqlDB::getDB();
        $data['count'] = $db->count(self::$table, [
            "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
        ], [self::$table . '.id'], $where);
        if (empty($data['count'])) {
            return $data;
        }
        $offset = ($page - 1) * $limit;
        $where[AgentDivideRulesModel::$table.'.status'] = AgentDivideRulesModel::STATUS_OK;
        $data['list'] = $db->select(
            self::$table,
            [
                "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
                "[><]" . EmployeeModel::$table => ['employee_id' => 'id'],
                "[>]" . AgentOrganizationModel::$table => ['id' => 'agent_id'],
                "[>]" . EmployeeModel::$table . "(e)" => ['service_employee_id' => 'id'],
                "[>]" . AgentDivideRulesModel::$table => ['id' => 'agent_id'],
            ],
            [
                self::$table . '.id',
                self::$table . '.mobile',
                self::$table . '.status',
                self::$table . '.create_time',
                self::$table . '.employee_id',
                self::$table . '.type',
                self::$table . '.service_employee_id',
                self::$table . '.name',
                self::$table . '.division_model',
                AgentInfoModel::$table . '.country',
                AgentInfoModel::$table . '.province',
                AgentInfoModel::$table . '.city',
                AgentInfoModel::$table . '.district',
                AgentInfoModel::$table . '.address',
                AgentOrganizationModel::$table . '.quantity',
                AgentOrganizationModel::$table . '.amount',
                EmployeeModel::$table . '.name(e_name)',
                AgentDivideRulesModel::$table . '.app_id',
                'e.name(e_s_name)'
            ],
            [
                "AND" => $where,
                "ORDER" => [self::$table . ".id" => 'DESC'],
                "LIMIT" => [$offset, $limit],
            ]
        );
        return $data;
    }

    /**
     * 获取二级代理列表
     * @param $parentIds
     * @return array|null
     */
    public static function agentSecondaryData($parentIds)
    {
        $db = MysqlDB::getDB();
        $secondaryData = $db->select(
            self::$table,
            ['[><]' . AgentInfoModel::$table => ['id' => 'agent_id']],
            [
                self::$table . '.id', self::$table . '.mobile', self::$table . '.status',
                self::$table . '.parent_id', self::$table . '.create_time', self::$table . '.employee_id',
                self::$table . '.type', AgentModel::$table . '.name'
            ],
            [
                self::$table . '.parent_id' => $parentIds
            ]);
        return $secondaryData;
    }

    /**
     * 根据手机号查询代理
     * @param $mobile
     * @param $countryCode
     * @return array|mixed
     */
    public static function getByMobile($mobile, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        if (empty($mobile)) {
            return [];
        }
        return self::getRecord(['mobile' => $mobile, 'country_code' => $countryCode]);
    }

    /**
     * 根据openid查询代理
     * @param $openId
     * @return array|mixed
     */
    public static function getByOpenid($openId)
    {
        if (empty($openId)) {
            return [];
        }
        $db = MysqlDB::getDB();
        $field = [
            self::$table.'.id',
            self::$table.'.parent_id',
            self::$table.'.employee_id',
            self::$table.'.uuid',
            self::$table.'.mobile',
            self::$table.'.name',
            self::$table.'.country_code',
            self::$table.'.status',
            self::$table.'.create_time',
            self::$table.'.update_time',
            self::$table.'.type',
            self::$table.'.service_employee_id'
        ];
        $res = $db->select(
            self::$table,
            [
                '[><]' . UserWeiXinModel::$table => ['id' => 'user_id'],
            ],
            $field,
            [
                UserWeiXinModel::$table . '.open_id' => $openId,
                UserWeiXinModel::$table . '.user_type' => UserWeiXinModel::USER_TYPE_AGENT,
                UserWeiXinModel::$table . '.status' => UserWeiXinModel::STATUS_NORMAL
            ]
        );
        return $res[0] ?? [];
    }

    /**
     * 获取代理商以及其父级信息
     * @param $agentIds
     * @return array
     */
    public static function getAgentParentData($agentIds)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                    a.id,
                    a.status,
                    a.name,
                    b.name as 'parent_name',
                    b.id AS p_id,
                    b.status AS p_status,
                    IF ( b.division_model IS NULL, a.division_model, b.division_model ) AS division_model, 
                    IF ( b.type IS NULL, a.type, b.type ) AS agent_type
                FROM
                    agent AS a
                    LEFT JOIN agent AS b ON a.parent_id = b.id 
                WHERE
                    a.id in(".implode(',',$agentIds).");";
        $data = $db->queryAll($sql);
        return $data;
    }
}
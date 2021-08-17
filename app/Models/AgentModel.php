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
use Medoo\Medoo;

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
        self::TYPE_OFFLINE => '商家代理',
        self::TYPE_INDIVIDUAL_TEACHER => '个人老师代理',
    ];

    //线上代理
    const ONLINE_TYPE_MAP = [
        self::TYPE_DISTRIBUTION => '分销代理',
        self::TYPE_INDIVIDUAL_PARENT => '个人家长代理',
        self::TYPE_INDIVIDUAL_TEACHER => '个人老师代理',
    ];

    //线下代理
    const OFFLINE_TYPE_MAP = [
        self::TYPE_OFFLINE => '商家代理',
    ];

    //分成模式:1线索获量 2线索获量+售卖模式
    const DIVISION_MODEL_LEADS = 1;
    const DIVISION_MODEL_LEADS_AND_SALE = 2;

    //线索分配类型：1自动分配 2不分配 3分配助教
    const LEADS_ALLOT_TYPE_AUTOMATION = 1;
    const LEADS_ALLOT_TYPE_STOP = 2;
    const LEADS_ALLOT_TYPE_ASSISTANT = 3;

    /**
     * 新增代理账户
     * @param $agentData
     * @param $agentDivideRulesInsertData
     * @param $agentInfoInsertData
     * @param $packageIds
     * @param $appId
     * @param $organizationInsertData
     * @param $serviceEmployeeInsertData
     * @return bool
     */
    public static function add($agentData, $agentDivideRulesInsertData, $agentInfoInsertData, $packageIds, $appId, $organizationInsertData, $serviceEmployeeInsertData)
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
        //代理商与负责员工关联
        if (!empty($serviceEmployeeInsertData)) {
            foreach ($serviceEmployeeInsertData as &$sv) {
                $sv['agent_id'] = $agentId;
            }
            $seId = AgentServiceEmployeeModel::batchInsert($serviceEmployeeInsertData);
            if (empty($seId)) {
                SimpleLogger::error('insert agent service employee data error', $serviceEmployeeInsertData);
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
     * @param $serviceEmployeeUpdateData
     * @return bool
     */
    public static function update($agentId, $agentUpdateData, $agentDivideRulesInsertData, $agentInfoUpdateData, $packageIds, $appId, $organizationUpdateData, $serviceEmployeeUpdateData)
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
        if (!empty($organizationUpdateData)) {
            if (!empty($organizationUpdateData['id'])) {
                $orgId = AgentOrganizationModel::updateRecord($organizationUpdateData['id'], $organizationUpdateData['data']);
            } else {
                $orgId = AgentOrganizationModel::insertRecord($organizationUpdateData['data']);
            }
            if (empty($orgId)) {
                SimpleLogger::error('update agent organization data error', $organizationUpdateData);
                return false;
            }
        }
        //代理商与负责员工关联
        $seId = true;
        if (!empty($serviceEmployeeUpdateData['del'])) {
            foreach ($serviceEmployeeUpdateData['del'] as $dev) {
                $seId = AgentServiceEmployeeModel::updateRecord($dev['id'], $dev['update_data']);
                if (empty($seId)) {
                    return false;
                }
            }
        }
        if (!empty($serviceEmployeeUpdateData['add'])) {
            $seId = AgentServiceEmployeeModel::batchInsert($serviceEmployeeUpdateData['add']);
        }
        if (empty($seId)) {
            SimpleLogger::error('insert agent service employee data error', $serviceEmployeeUpdateData);
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
        $sql = 'SELECT
                    a.`id`,
                    a.`mobile`,
                    a.`employee_id`,
                    a.`type`,
                    a.`status`,
                    a.`name`,
                    a.`country_code`,
                    a.`division_model`,
                    a.`division_model`,
                    a.`leads_allot_type`,
                    a.`assistant_id`,
                    ao.`name` AS `organization`,
                    ai.`country`,
                    ai.`province`,
                    ai.`city`,
                    ai.`district`,
                    ai.`address`,
                    ai.`remark`,
                    ad.`app_id`,
                    ad.`rule`,
                    e.`name` AS `e_name`,
                    GROUP_CONCAT( `ea`.`name` ) AS `e_s_name`,
                    GROUP_CONCAT( `ea`.`id` ) AS `service_employee_id` 
                FROM
                    '.self::$table.' as a
                    INNER JOIN '.AgentInfoModel::$table.' as ai ON a.`id` = ai.`agent_id`
                    INNER JOIN '.AgentDivideRulesModel::$table.' as ad ON a.`id` = ad.`agent_id`
                    LEFT JOIN '.AgentOrganizationModel::$table.' as ao ON a.`id` = ao.`agent_id`
                    INNER JOIN '.EmployeeModel::$table.' as e ON a.`employee_id` = e.`id`
                    LEFT JOIN ' . AgentServiceEmployeeModel::$table . ' as `ase` ON a.`id` = `ase`.`agent_id` AND ase.status = ' . AgentServiceEmployeeModel::STATUS_OK . '
                    LEFT JOIN '.EmployeeModel::$table.' as `ea` ON `ase`.`employee_id` = `ea`.`id` 
                WHERE
                    a.id = ' . $agentId . ' 
                    AND ad.status = ' . AgentDivideRulesModel::STATUS_OK . ' 
                GROUP BY
                    a.id;';
        $data = $db->queryAll($sql);
        return $data[0];
    }

    /**
     * 获取代理账户列表
     * @param $where
     * @param $order
     * @param $page
     * @param $limit
     * @return array
     */
    public static function list($where, $order, $page, $limit)
    {
        $data = ['count' => 0, 'list' => [],];
        $db = MysqlDB::getDB();
        $countData = $db->select(self::$table, [
            "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
            "[>]" . AgentServiceEmployeeModel::$table => ['id' => 'agent_id'],
            "[>]" . AgentOrganizationModel::$table => ['id' => 'agent_id'],
        ], [self::$table . '.id'], ["AND" => $where, "GROUP" => self::$table . ".id"]);



        if (empty($countData)) {
            return $data;
        }
        $data['count'] = count($countData);
        $offset = ($page - 1) * $limit;
        $data['list'] = $db->select(
            self::$table,
            [
                "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
                "[><]" . EmployeeModel::$table => ['employee_id' => 'id'],
                "[>]" . AgentOrganizationModel::$table => ['id' => 'agent_id'],
                "[>]" . AgentServiceEmployeeModel::$table => ['id'=>'agent_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.mobile',
                self::$table . '.status',
                self::$table . '.create_time',
                self::$table . '.employee_id',
                self::$table . '.type',
                self::$table . '.name',
                self::$table . '.division_model',
                self::$table . '.leads_allot_type',
                self::$table . '.total_rsc',
                self::$table . '.total_rbc',
                self::$table . '.sec_agent_count',
                AgentInfoModel::$table . '.country',
                AgentInfoModel::$table . '.province',
                AgentInfoModel::$table . '.city',
                AgentInfoModel::$table . '.district',
                AgentInfoModel::$table . '.address',
                AgentOrganizationModel::$table . '.quantity',
                AgentOrganizationModel::$table . '.amount',
                AgentOrganizationModel::$table . '.name(organization)',
                EmployeeModel::$table . '.name(e_name)',
            ],
            [
                "AND" => $where,
                "GROUP" => self::$table . ".id",
                "ORDER" => $order,
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
            self::$table . '.id',
            self::$table . '.parent_id',
            self::$table . '.employee_id',
            self::$table . '.uuid',
            self::$table . '.mobile',
            self::$table . '.name',
            self::$table . '.country_code',
            self::$table . '.status',
            self::$table . '.create_time',
            self::$table . '.update_time',
            self::$table . '.type',
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
                    IF ( b.type IS NULL, a.type, b.type ) AS agent_type,
                    IF ( b.leads_allot_type IS NULL, a.leads_allot_type, b.leads_allot_type ) AS allot_type,
                    IF ( b.assistant_id IS NULL, a.assistant_id, b.assistant_id ) AS assistant
                FROM
                    agent AS a
                    LEFT JOIN agent AS b ON a.parent_id = b.id 
                WHERE
                    a.id in(" . implode(',', $agentIds) . ");";
        $data = $db->queryAll($sql);
        return $data;
    }

    /**
     * 获取某个员工可以看到的代理商ID列表
     * @param $employeeId
     * @return array
     */
    public static function getAgentByEmployee($employeeId)
    {
        $db = MysqlDB::getDB();
        return $db->select(
            self::$table,
            [
                '[>]' . AgentServiceEmployeeModel::$table => ['id' => 'agent_id']
            ],
            [self::$table . '.id'],
            [
                'OR' => [
                    self::$table . '.employee_id' => $employeeId,
                    'AND' => [
                        AgentServiceEmployeeModel::$table . '.employee_id' => $employeeId,
                        AgentServiceEmployeeModel::$table . '.status' => AgentServiceEmployeeModel::STATUS_OK,
                    ]
                ],
                'GROUP' => self::$table . '.id',
            ]
        );
    }

    /**
     * 统计一级代理商已及其下级的代理运营汇总数据
     * @param array $agentId
     * @param array $spreadData
     * @return bool
     */
    public static function updateAgentOperationSummaryData($agentId, $spreadData)
    {
        //一级代理商数据
        $sql = 'UPDATE agent 
                        SET 
                            direct_rsc = ' . $spreadData['self']['s_count'] . ',
                            sec_rsc = ' . ($spreadData['total']['s_count'] - $spreadData['self']['s_count']) . ',
                            total_rsc = ' . $spreadData['total']['s_count'] . ',
                            direct_rbc = ' . $spreadData['self']['b_count'] . ',
                            sec_rbc = ' . ($spreadData['total']['b_count'] - $spreadData['self']['b_count']) . ',
                            total_rbc = ' . $spreadData['total']['b_count'] . ',
                            sec_agent_count = ' . $spreadData['son_num'] . '
                        WHERE
                            id=' . $agentId . ';';
        $delWhere['id'][] = $agentId;
        //二级代理数据
        if (isset($spreadData['son']) && !empty($spreadData['son'])) {
            foreach ($spreadData['son'] as $sk => $sv) {
                $sql .= 'UPDATE agent 
                        SET 
                            direct_rsc = ' . $sv['s_count'] . ',
                            sec_rsc = 0,
                            total_rsc = ' . $sv['s_count'] . ',
                            direct_rbc = ' . $sv['b_count'] . ',
                            sec_rbc = 0,
                            total_rbc = ' . $sv['b_count'] . ',
                            sec_agent_count = 0
                        WHERE
                            id=' . $sk . ';';
                $delWhere['id'][] = $sk;
            }
        }
        $db = MysqlDB::getDB();
        $db->queryAll($sql);
        SimpleLogger::info('agent statics query result', ['agent_id' => $agentId, 'db_error_msg' => $db->error()]);
        //删除缓存
        self::batchDelCache($delWhere);
        return true;
    }
}